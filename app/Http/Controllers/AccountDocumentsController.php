<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\User;
use App\Services\Users\FamilyStudentContextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class AccountDocumentsController extends Controller
{
    public function __construct(
        private readonly FamilyStudentContextService $familyContext,
    ) {
    }

    /**
     * Вкладка "Учетная запись" -> "Мои документы" (активный ребёнок семейного кабинета).
     */
    public function index(Request $request)
    {
        $actor = Auth::user();
        abort_unless($actor instanceof User, 401);

        $this->applyEmailStudentContext($request, $actor);

        $student = $this->familyContext->activeStudent($actor);
        $partners = $actor->partner ? collect([$actor->partner]) : collect();

        // фильтр по статусу (опционально: ?status=signed и т.п.)
        $status = $request->string('status')->toString();

        $contracts = Contract::query()
            ->where('user_id', $student->id)
            ->with(['user', 'team', 'lastSignRequest', 'templateVersion.template'])
            ->when($status, fn($q) => $q->where('status', $status))
            ->orderByDesc('id')
            ->paginate(12);

        // для удобного рендера бейджей
        $statusMap = [
            'draft'   => ['label' => 'Черновик',        'class' => 'secondary'],
            Contract::STATUS_AWAITING_CLIENT_FILL => ['label' => 'Требуется заполнение', 'class' => 'primary'],
            Contract::STATUS_GENERATING_PDF       => ['label' => 'Формируется PDF', 'class' => 'info'],
            'sent'    => ['label' => 'Отправлено',      'class' => 'info'],
            'opened'  => ['label' => 'Открыт',          'class' => 'warning'],
            'signed'  => ['label' => 'Подписан',        'class' => 'success'],
            'expired' => ['label' => 'Истёк срок',      'class' => 'dark'],
            'revoked' => ['label' => 'Отозван',         'class' => 'dark'],
            'failed'  => ['label' => 'Ошибка',          'class' => 'danger'],
        ];

        $openFillContractId = $request->integer('fill');
        $openFillMode = $request->string('mode')->toString() === 'edit' ? 'edit' : null;

        return view('account.index', [
            'activeTab' => 'myDocuments',
            'user'      => $actor,
            'partners'  => $partners,
            'contracts' => $contracts,
            'statusMap' => $statusMap,
            'currentStatus' => $status,
            'openFillContractId' => $openFillContractId > 0 ? $openFillContractId : null,
            'openFillMode'       => $openFillMode,
        ]);
    }

    /**
     * История отправок (AJAX) для договора текущего пользователя.
     */
    public function requests(Contract $contract)
    {
        // Anti-enumeration: скрываем существование чужого договора
        $this->abortUnlessFamilyContract($contract);

        $contract->load([
            'signRequests' => fn($q) => $q->orderByDesc('id'),
        ]);

        return response()->json([
            'requests' => $contract->signRequests->map(fn($r) => [
                'id'        => $r->id,
                'signer'    => $r->signer_name,
                'phone'     => $r->signer_phone,
                'status'    => $r->status_ru,
                'badge'     => $r->status_badge_class,
                'created'   => $r->created_at?->format('d.m.Y H:i'),
            ]),
        ]);
    }

    /**
     * Скачивание оригинала (только текущий пользователь).
     */
    public function downloadOriginal(Contract $contract)
    {
        // Anti-enumeration: скрываем существование чужого договора
        $this->abortUnlessFamilyContract($contract);
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

    /**
     * Скачивание подписанного (только текущий пользователь).
     */
    public function downloadSigned(Contract $contract)
    {
        // Anti-enumeration: скрываем существование чужого договора
        $this->abortUnlessFamilyContract($contract);
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
            Log::error('[account.documents.download] failed', [
                'contract_id' => $contract->id,
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

    /**
     * Ссылка из письма: ?student={user_id} переключает семейный контекст.
     * Чужой или недоступный id молча игнорируется (anti-enumeration).
     */
    private function applyEmailStudentContext(Request $request, User $actor): void
    {
        $studentId = $request->integer('student');
        if ($studentId <= 0) {
            return;
        }
        if (!$this->familyContext->canAccessStudent($actor, $studentId)) {
            return;
        }

        $this->familyContext->setActiveStudent($actor, $studentId);
    }

    private function abortUnlessFamilyContract(Contract $contract): void
    {
        $actor = Auth::user();
        abort_unless($actor instanceof User, 404);
        abort_unless($this->familyContext->canAccessContract($actor, $contract), 404);
    }
}

