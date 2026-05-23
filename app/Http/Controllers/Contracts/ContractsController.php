<?php

namespace App\Http\Controllers\Contracts;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contracts\ContractCheckBalanceRequest;
use App\Http\Requests\Contracts\ContractStoreRequest;
use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\Partner;
use App\Models\Team;
use App\Models\User;
use App\Services\Contracts\ContractBillingService;
use App\Services\Contracts\ContractCreationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ContractsController extends Controller
{
    public function __construct(
        private readonly ContractCreationService $creationService,
        private readonly ContractBillingService $billing,
    ) {
    }

    private function partner(): Partner
    {
        $p = app('current_partner');
        abort_unless($p, 403, 'Партнёр не выбран.');
        return $p;
    }

    private function partnerId(): int
    {
        return $this->partner()->id;
    }

    public function index(Request $request)
    {
        $partnerId = $this->partnerId();

        $allTeams = Team::where('partner_id', $partnerId)
            ->orderBy('order_by', 'asc')
            ->get();

        return view('contracts.index', compact('allTeams'));
    }

    public function create(Request $request)
    {
        $partner = $this->partner();
        $partnerId = $partner->id;

        $preselectedUser = null;
        $userId = $request->integer('user_id');

        if ($userId > 0) {
            $preselectedUser = User::query()
                ->where('id', $userId)
                ->where('partner_id', $partnerId)
                ->where('is_enabled', 1)
                ->first(['id', 'name', 'lastname', 'team_id']);
        }

        if ($preselectedUser) {
            $teamTitle = null;
            if ($preselectedUser->team_id) {
                $teamTitle = Team::query()
                    ->where('id', $preselectedUser->team_id)
                    ->where('partner_id', $partnerId)
                    ->value('title');
            }

            $preselectedUser = [
                'id'         => $preselectedUser->id,
                'text'       => trim(($preselectedUser->lastname ?? '') . ' ' . ($preselectedUser->name ?? '')),
                'team_id'    => $preselectedUser->team_id,
                'team_title' => $teamTitle,
            ];
        }

        $contractTemplates = ContractTemplate::query()
            ->forPartner($partnerId)
            ->active()
            ->whereNotNull('current_version_id')
            ->orderBy('title')
            ->get(['id', 'title']);

        return view('contracts.create', compact('partner', 'partnerId', 'preselectedUser', 'contractTemplates'));
    }

    public function store(ContractStoreRequest $request)
    {
        $validated = $request->validated();

        try {
            $contract = $this->creationService->create($this->partner(), $validated);
        } catch (ValidationException $e) {
            return back()
                ->withErrors($e->errors())
                ->withInput()
                ->with('error', 'Не удалось создать договор.');
        }

        $message = $contract->isTemplateMode()
            ? 'Договор создан. С баланса списано 70 ₽. Клиенту отправлено уведомление в личный кабинет (и на email, если указан).'
            : 'Договор создан. С баланса списано 70 ₽. Теперь можно отправить на подпись.';

        return redirect()->route('contracts.show', $contract->id)->with('success', $message);
    }

    public function show(Contract $contract)
    {
        $contract->load('templateVersion.template');

        $events = $contract->events()->orderBy('id', 'desc')->get();
        $requests = $contract->signRequests()->orderBy('id', 'desc')->get();

        $student = User::select(
            'id',
            'name',
            'lastname',
            'phone',
            'email',
            'team_id',
            'parent_lastname',
            'parent_firstname',
            'parent_middlename',
        )->find($contract->user_id);

        $teamTitle = null;
        if ($student && $student->team_id) {
            $teamTitle = DB::table('teams')
                ->where('id', $student->team_id)
                ->value('title');
        }

        return view('contracts.show', compact('contract', 'events', 'requests', 'student', 'teamTitle'));
    }

    public function downloadOriginal(Contract $contract)
    {
        if (!$contract->source_pdf_path) {
            return back()->withErrors([
                'file' => 'Исходный файл договора не найден.',
            ]);
        }

        return $this->downloadContractFile(
            $contract,
            $contract->source_pdf_path,
            'contract-' . $contract->id . '.pdf',
            'original'
        );
    }

    public function downloadSigned(Contract $contract)
    {
        if (!$contract->signed_pdf_path) {
            return back()->withErrors([
                'file' => 'Подписанный файл договора не найден.',
            ]);
        }

        return $this->downloadContractFile(
            $contract,
            $contract->signed_pdf_path,
            'contract-' . $contract->id . '-signed.pdf',
            'signed'
        );
    }

    private function downloadContractFile(Contract $contract, string $path, string $downloadName, string $kind)
    {
        try {
            if (!Storage::exists($path)) {
                return back()->withErrors([
                    'file' => 'Файл договора не найден в хранилище.',
                ]);
            }

            return Storage::download($path, $downloadName);
        } catch (\Throwable $e) {
            Log::error('[contracts.download] fail', [
                'contract_id' => $contract->id,
                'partner_id' => $this->partnerId(),
                'user_id' => Auth::id(),
                'kind' => $kind,
                'path' => $path,
                'disk' => config('filesystems.default'),
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'file' => 'Не удалось скачать файл договора. Попробуйте позже.',
            ]);
        }
    }

    public function checkBalance(ContractCheckBalanceRequest $request)
    {
        $partnerId = $this->partnerId();
        $fee = $this->billing->createFee();

        $balance = Partner::whereKey($partnerId)->value('wallet_balance');

        if ($balance === null) {
            return response()->json([
                'ok'      => false,
                'message' => 'Партнёр не найден.',
            ], 404);
        }

        if ((float)$balance >= $fee) {
            return response()->json([
                'ok'      => true,
                'balance' => (float)$balance,
                'fee'     => $fee,
            ]);
        }

        return response()->json([
            'ok'      => false,
            'message' => 'Недостаточно средств для создания договора.',
            'balance' => (float)$balance,
            'fee'     => $fee,
        ], 422);
    }
}
