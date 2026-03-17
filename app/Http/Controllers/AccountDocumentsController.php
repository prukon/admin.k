<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class AccountDocumentsController extends Controller
{
    /**
     * Вкладка "Учетная запись" -> "Мои документы" (текущий пользователь).
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $partners = $user->partner ? collect([$user->partner]) : collect();

        // фильтр по статусу (опционально: ?status=signed и т.п.)
        $status = $request->string('status')->toString();

        $contracts = Contract::query()
            ->where('user_id', $user->id)
            ->with(['user', 'team', 'lastSignRequest'])
            ->when($status, fn($q) => $q->where('status', $status))
            ->orderByDesc('id')
            ->paginate(12);

        // для удобного рендера бейджей
        $statusMap = [
            'draft'   => ['label' => 'Черновик',        'class' => 'secondary'],
            'sent'    => ['label' => 'Отправлено',      'class' => 'info'],
            'opened'  => ['label' => 'Открыт',          'class' => 'warning'],
            'signed'  => ['label' => 'Подписан',        'class' => 'success'],
            'expired' => ['label' => 'Истёк срок',      'class' => 'dark'],
            'revoked' => ['label' => 'Отозван',         'class' => 'dark'],
            'failed'  => ['label' => 'Ошибка',          'class' => 'danger'],
        ];

        return view('account.index', [
            'activeTab' => 'myDocuments',
            'user'      => $user,
            'partners'  => $partners,
            'contracts' => $contracts,
            'statusMap' => $statusMap,
            'currentStatus' => $status,
        ]);
    }

    /**
     * История отправок (AJAX) для договора текущего пользователя.
     */
    public function requests(Contract $contract)
    {
        // Anti-enumeration: скрываем существование чужого договора
        abort_unless((int)$contract->user_id === (int)Auth::id(), 404);

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
        abort_unless((int)$contract->user_id === (int)Auth::id(), 404);
        abort_unless($contract->source_pdf_path, 404);
        return Storage::download($contract->source_pdf_path, 'contract-' . $contract->id . '.pdf');
    }

    /**
     * Скачивание подписанного (только текущий пользователь).
     */
    public function downloadSigned(Contract $contract)
    {
        // Anti-enumeration: скрываем существование чужого договора
        abort_unless((int)$contract->user_id === (int)Auth::id(), 404);
        abort_unless($contract->signed_pdf_path, 404);
        return Storage::download($contract->signed_pdf_path, 'contract-' . $contract->id . '-signed.pdf');
    }
}

