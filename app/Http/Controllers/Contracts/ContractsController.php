<?php

namespace App\Http\Controllers\Contracts;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contracts\ContractCheckBalanceRequest;
use App\Http\Requests\Contracts\ContractStoreRequest;
use App\Models\Contract;
use App\Models\ContractEvent;
use App\Models\MyLog;
use App\Models\Partner;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ContractsController extends Controller
{
    // единая точка входа
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

    public function create()
    {
        $partner = $this->partner();
        $partnerId = $partner->id;

        return view('contracts.create', compact('partner', 'partnerId'));
    }

    public function store(ContractStoreRequest $request)
    {
        $partner = $this->partner();
        $partnerId = $partner->id;

        $validated = $request->validated();

        /** @var User|null $student */
        $student = User::query()
            ->where('id', $validated['user_id'])
            ->where('partner_id', $partnerId)
            ->where('is_enabled', 1)
            ->first();

        abort_unless($student, 422, 'Ученик не найден у текущего партнёра.');

        $fee = $this->createContractFee();

        try {
            $contract = DB::transaction(function () use ($request, $partnerId, $student, $fee) {
                /** @var Partner $partner */
                $partner = app('current_partner');

                if ($partner->wallet_balance < $fee) {
                    throw ValidationException::withMessages([
                        'wallet' => 'Недостаточно средств для создания договора.',
                    ]);
                }

                $partner->wallet_balance = $partner->wallet_balance - $fee;
                $partner->save();
                Cache::forget("partner_balance_{$partner->id}");

                $groupId = $student->team_id;

                $path = $request->file('pdf')->store('documents/' . date('Y/m'));
                $sha = hash_file('sha256', Storage::path($path));

                $contract = Contract::create([
                    'school_id'        => $partnerId,
                    'user_id'          => $student->id,
                    'group_id'         => $groupId,
                    'source_pdf_path'  => $path,
                    'source_sha256'    => $sha,
                    'status'           => Contract::STATUS_DRAFT,
                    'provider'         => 'podpislon',
                ]);

                ContractEvent::create([
                    'contract_id'  => $contract->id,
                    'author_id'    => Auth::id(),
                    'type'         => 'Списание баланса за создание договора',
                    'payload_json' => json_encode([
                        'amount'        => number_format($fee, 2, '.', ''),
                        'currency'      => 'RUB',
                        'partner_id'    => $partnerId,
                        'balance_after' => number_format($partner->wallet_balance, 2, '.', ''),
                    ], JSON_UNESCAPED_UNICODE),
                ]);

                ContractEvent::create([
                    'contract_id'  => $contract->id,
                    'author_id'    => Auth::id(),
                    'type'         => 'created',
                    'payload_json' => null,
                ]);

                MyLog::create([
                    'type'         => 500,
                    'action'       => 500,
                    'user_id'      => $student->id,
                    'target_type'  => Contract::class,
                    'target_id'    => $partner->id,
                    'target_label' => $partner->title,
                    'description'  => 'Договор создан: № ' . $contract->id,
                    'created_at'   => now(),
                ]);

                return $contract;
            });
        } catch (ValidationException $e) {
            return back()
                ->withErrors($e->errors())
                ->withInput()
                ->with('error', 'Недостаточно средств для создания договора.');
        }

        return redirect()->route('contracts.show', $contract->id)
            ->with('success', 'Договор создан. С баланса списано 70 ₽. Теперь можно отправить на подпись.');
    }

    public function show(Contract $contract)
    {
        $events = $contract->events()->orderBy('id', 'desc')->get();
        $requests = $contract->signRequests()->orderBy('id', 'desc')->get();

        $student = User::select('id', 'name', 'lastname', 'phone', 'email', 'team_id')
            ->find($contract->user_id);

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
        return Storage::download($contract->source_pdf_path, 'contract-' . $contract->id . '.pdf');
    }

    public function downloadSigned(Contract $contract)
    {
        abort_unless($contract->signed_pdf_path, 404);
        return Storage::download($contract->signed_pdf_path, 'contract-' . $contract->id . '-signed.pdf');
    }

    private function createContractFee(): float
    {
        return (float)(config('billing.contract_create_fee') ?? 70.00);
    }

    public function checkBalance(ContractCheckBalanceRequest $request)
    {
        $partnerId = $this->partnerId();
        $fee = (float)(config('billing.contract_create_fee') ?? 70.00);

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

