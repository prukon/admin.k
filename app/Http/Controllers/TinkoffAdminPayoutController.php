<?php

namespace App\Http\Controllers;

use App\Models\Partner;
use App\Models\TinkoffPayout;
use App\Services\Tinkoff\TinkoffPayoutsService;
use App\Http\Requests\Tinkoff\Admin\TinkoffPayoutsDataTableRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\User;

class TinkoffAdminPayoutController extends Controller
{
    public function index(Request $r)
    {
        $actor = auth()->user();
        $isSuperadmin = $actor instanceof User && $actor->hasRole('superadmin');

        $partners = $isSuperadmin
            ? Partner::orderBy('title')->get(['id', 'title'])
            : Partner::query()->whereKey((int) app('current_partner')->id)->get(['id', 'title']);

        return view('tinkoff.payouts.index', compact('partners', 'isSuperadmin'));
    }

    public function show(int $id, TinkoffPayoutsService $svc)
    {
        $payout = TinkoffPayout::with(['partner', 'payment', 'payer', 'initiator'])->findOrFail($id);

        $actor = auth()->user();
        $isSuperadmin = $actor instanceof User && $actor->hasRole('superadmin');
        if (!$isSuperadmin && (int) $payout->partner_id !== (int) app('current_partner')->id) {
            abort(404);
        }

        $breakdown = [
            'gross' => $payout->gross_amount,
            'bankAccept' => $payout->bank_accept_fee,
            'bankPayout' => $payout->bank_payout_fee,
            'platformFee' => $payout->platform_fee,
            'net' => $payout->net_amount ?? $payout->amount,
            'is_snapshot' => true,
        ];

        if ($breakdown['gross'] === null && $payout->payment) {
            // Для исторических выплат без snapshot — вычисляем по текущим правилам (только для отображения).
            $b = $svc->breakdownForPayment($payout->payment);
            $breakdown = [
                'gross' => $b['gross'],
                'bankAccept' => $b['bankAccept'],
                'bankPayout' => $b['bankPayout'],
                'platformFee' => $b['platformFee'],
                'net' => $b['net'],
                'is_snapshot' => false,
            ];
        }

        return view('tinkoff.payouts.show', compact('payout', 'breakdown'));
    }

    /**
     * DataTables server-side endpoint.
     */
    public function data(TinkoffPayoutsDataTableRequest $request)
    {
        $validated = $request->validated();

        $actor = auth()->user();
        $isSuperadmin = $actor instanceof User && $actor->hasRole('superadmin');
        $currentPartnerId = (int) app('current_partner')->id;

        $baseQuery = TinkoffPayout::query()
            ->leftJoin('partners', 'partners.id', '=', 'tinkoff_payouts.partner_id')
            ->leftJoin('tinkoff_payments', 'tinkoff_payments.id', '=', 'tinkoff_payouts.payment_id')
            ->leftJoin('users as payer', 'payer.id', '=', 'tinkoff_payouts.payer_user_id')
            ->leftJoin('users as initiator', 'initiator.id', '=', 'tinkoff_payouts.initiated_by_user_id')
            ->select([
                'tinkoff_payouts.*',
                'partners.title as partner_title',
                'tinkoff_payments.order_id as payment_order_id',
                'tinkoff_payments.status as payment_status',
                'tinkoff_payments.tinkoff_payment_id as tbank_payment_id',
                'tinkoff_payments.amount as payment_amount',
                DB::raw("TRIM(CONCAT(COALESCE(payer.lastname,''),' ',COALESCE(payer.name,''))) as payer_name"),
                'payer.email as payer_email',
                'payer.phone as payer_phone',
                'payer.id as payer_id',
                DB::raw("TRIM(CONCAT(COALESCE(initiator.lastname,''),' ',COALESCE(initiator.name,''))) as initiator_name"),
                'initiator.email as initiator_email',
                'initiator.phone as initiator_phone',
                'initiator.id as initiator_id',
            ]);

        if (!$isSuperadmin) {
            $baseQuery->where('tinkoff_payouts.partner_id', $currentPartnerId);
        } else {
            // фильтр по партнёру доступен только супер-админу
            if ($request->filled('partner_id') && ctype_digit((string) $validated['partner_id'])) {
                $baseQuery->where('tinkoff_payouts.partner_id', (int) $validated['partner_id']);
            }
        }

        if (!empty($validated['status'])) {
            $baseQuery->where('tinkoff_payouts.status', $validated['status']);
        }
        if (!empty($validated['source'])) {
            $baseQuery->where('tinkoff_payouts.source', $validated['source']);
        }
        if (!empty($validated['deal_id'])) {
            $baseQuery->where('tinkoff_payouts.deal_id', $validated['deal_id']);
        }
        if (!empty($validated['tinkoff_payout_payment_id'])) {
            $baseQuery->where('tinkoff_payouts.tinkoff_payout_payment_id', $validated['tinkoff_payout_payment_id']);
        }
        if (!empty($validated['tinkoff_payment_id'])) {
            $baseQuery->where('tinkoff_payouts.payment_id', (int) $validated['tinkoff_payment_id']);
        }

        // Плательщик
        $payerQ = trim((string) ($validated['payer_query'] ?? ''));
        if ($payerQ !== '') {
            $baseQuery->where(function ($q) use ($payerQ) {
                if (ctype_digit($payerQ)) {
                    $q->orWhere('payer.id', (int) $payerQ);
                }
                $like = '%' . $payerQ . '%';
                $q->orWhere('payer.name', 'like', $like)
                    ->orWhere('payer.lastname', 'like', $like)
                    ->orWhere('payer.email', 'like', $like)
                    ->orWhere('payer.phone', 'like', $like);
            });
        }

        // Инициатор
        $initQ = trim((string) ($validated['initiator_query'] ?? ''));
        if ($initQ !== '') {
            $baseQuery->where(function ($q) use ($initQ) {
                if (ctype_digit($initQ)) {
                    $q->orWhere('initiator.id', (int) $initQ);
                }
                $like = '%' . $initQ . '%';
                $q->orWhere('initiator.name', 'like', $like)
                    ->orWhere('initiator.lastname', 'like', $like)
                    ->orWhere('initiator.email', 'like', $like)
                    ->orWhere('initiator.phone', 'like', $like);
            });
        }

        // Даты
        if (!empty($validated['created_from'])) {
            $baseQuery->where('tinkoff_payouts.created_at', '>=', Carbon::parse($validated['created_from'])->startOfDay());
        }
        if (!empty($validated['created_to'])) {
            $baseQuery->where('tinkoff_payouts.created_at', '<=', Carbon::parse($validated['created_to'])->endOfDay());
        }
        if (!empty($validated['run_from'])) {
            $baseQuery->where('tinkoff_payouts.when_to_run', '>=', Carbon::parse($validated['run_from'])->startOfDay());
        }
        if (!empty($validated['run_to'])) {
            $baseQuery->where('tinkoff_payouts.when_to_run', '<=', Carbon::parse($validated['run_to'])->endOfDay());
        }
        if (!empty($validated['completed_from'])) {
            $baseQuery->where('tinkoff_payouts.completed_at', '>=', Carbon::parse($validated['completed_from'])->startOfDay());
        }
        if (!empty($validated['completed_to'])) {
            $baseQuery->where('tinkoff_payouts.completed_at', '<=', Carbon::parse($validated['completed_to'])->endOfDay());
        }

        // Диапазоны сумм (в рублях) -> копейки
        if (isset($validated['gross_min'])) {
            $min = (int) round(((float) $validated['gross_min']) * 100);
            $baseQuery->whereRaw('COALESCE(tinkoff_payouts.gross_amount, tinkoff_payments.amount) >= ?', [$min]);
        }
        if (isset($validated['gross_max'])) {
            $max = (int) round(((float) $validated['gross_max']) * 100);
            $baseQuery->whereRaw('COALESCE(tinkoff_payouts.gross_amount, tinkoff_payments.amount) <= ?', [$max]);
        }
        if (isset($validated['net_min'])) {
            $min = (int) round(((float) $validated['net_min']) * 100);
            $baseQuery->whereRaw('COALESCE(tinkoff_payouts.net_amount, tinkoff_payouts.amount) >= ?', [$min]);
        }
        if (isset($validated['net_max'])) {
            $max = (int) round(((float) $validated['net_max']) * 100);
            $baseQuery->whereRaw('COALESCE(tinkoff_payouts.net_amount, tinkoff_payouts.amount) <= ?', [$max]);
        }

        // "Застрявшие"
        if ($request->boolean('stuck_only')) {
            $minutes = (int) ($validated['stuck_minutes'] ?? 60);
            $baseQuery->whereIn('tinkoff_payouts.status', [
                'INITIATED', 'NEW', 'AUTHORIZING', 'CHECKING', 'CREDIT_CHECKING', 'COMPLETING',
            ])->where('tinkoff_payouts.updated_at', '<=', now()->subMinutes($minutes));
        }

        // Общее количество (без фильтров) в рамках доступа
        $totalQuery = TinkoffPayout::query();
        if (!$isSuperadmin) {
            $totalQuery->where('partner_id', $currentPartnerId);
        } elseif ($request->filled('partner_id') && ctype_digit((string) $validated['partner_id'])) {
            $totalQuery->where('partner_id', (int) $validated['partner_id']);
        }
        $totalRecords = (clone $totalQuery)->count();

        $recordsFiltered = (clone $baseQuery)->count();

        // Sorting
        $orderColumnIndex = $request->input('order.0.column');
        $orderDir = $request->input('order.0.dir', 'desc');
        $orderDir = in_array($orderDir, ['asc', 'desc'], true) ? $orderDir : 'desc';

        if ($orderColumnIndex !== null) {
            switch ((int) $orderColumnIndex) {
                case 0: // rownum
                    $baseQuery->orderByDesc('tinkoff_payouts.id');
                    break;
                case 1: // payout id
                    $baseQuery->orderBy('tinkoff_payouts.id', $orderDir);
                    break;
                case 2: // status
                    $baseQuery->orderBy('tinkoff_payouts.status', $orderDir);
                    break;
                case 3: // source
                    $baseQuery->orderBy('tinkoff_payouts.source', $orderDir);
                    break;
                case 4: // partner
                    $baseQuery->orderBy('partners.title', $orderDir);
                    break;
                case 5: // payer
                    $baseQuery->orderBy('payer.lastname', $orderDir)->orderBy('payer.name', $orderDir);
                    break;
                case 6: // initiator
                    $baseQuery->orderBy('initiator.lastname', $orderDir)->orderBy('initiator.name', $orderDir);
                    break;
                case 7: // payment id
                    $baseQuery->orderBy('tinkoff_payouts.payment_id', $orderDir);
                    break;
                case 8: // deal_id
                    $baseQuery->orderBy('tinkoff_payouts.deal_id', $orderDir);
                    break;
                case 9: // gross
                    $baseQuery->orderByRaw('COALESCE(tinkoff_payouts.gross_amount, tinkoff_payments.amount) ' . $orderDir);
                    break;
                case 13: // net
                    $baseQuery->orderByRaw('COALESCE(tinkoff_payouts.net_amount, tinkoff_payouts.amount) ' . $orderDir);
                    break;
                case 14: // when_to_run
                    $baseQuery->orderBy('tinkoff_payouts.when_to_run', $orderDir);
                    break;
                case 15: // created_at
                    $baseQuery->orderBy('tinkoff_payouts.created_at', $orderDir);
                    break;
                case 16: // completed_at
                    $baseQuery->orderBy('tinkoff_payouts.completed_at', $orderDir);
                    break;
                default:
                    $baseQuery->orderByDesc('tinkoff_payouts.id');
            }
        } else {
            $baseQuery->orderByDesc('tinkoff_payouts.id');
        }

        $start = (int) ($validated['start'] ?? 0);
        $length = (int) ($validated['length'] ?? 20);

        $rows = $baseQuery->skip($start)->take($length)->get();

        $fmtMoney = function (?int $cents): string {
            if ($cents === null) return '';
            return number_format($cents / 100, 2, ',', ' ');
        };

        $data = $rows->map(function ($row) use ($fmtMoney) {
            $payerLabel = trim((string) ($row->payer_name ?? ''));
            if ($payerLabel === '') {
                $payerLabel = $row->payer_id ? ('#' . $row->payer_id) : '—';
            }

            $initLabel = trim((string) ($row->initiator_name ?? ''));
            if ($initLabel === '' && !empty($row->initiator_id)) {
                $initLabel = '#' . $row->initiator_id;
            }
            if (empty($row->initiator_id) && in_array((string) $row->source, ['auto', 'scheduled'], true)) {
                $initLabel = 'Система';
            }
            if ($initLabel === '') {
                $initLabel = '—';
            }

            return [
                'id' => (int) $row->id,
                'status' => (string) $row->status,
                'source' => (string) ($row->source ?? ''),
                'partner' => (string) ($row->partner_title ?? ('#' . $row->partner_id)),
                'payer' => $payerLabel,
                'initiator' => $initLabel,
                'payment_id' => (int) ($row->payment_id ?? 0),
                'deal_id' => (string) ($row->deal_id ?? ''),
                'gross' => $fmtMoney($row->gross_amount ?? $row->payment_amount ?? null),
                'bank_accept_fee' => $fmtMoney($row->bank_accept_fee ?? null),
                'bank_payout_fee' => $fmtMoney($row->bank_payout_fee ?? null),
                'platform_fee' => $fmtMoney($row->platform_fee ?? null),
                'net' => $fmtMoney($row->net_amount ?? $row->amount ?? null),
                'when_to_run' => $row->when_to_run ? Carbon::parse($row->when_to_run)->format('d.m.Y H:i') : '',
                'created_at' => $row->created_at ? Carbon::parse($row->created_at)->format('d.m.Y H:i') : '',
                'completed_at' => $row->completed_at ? Carbon::parse($row->completed_at)->format('d.m.Y H:i') : '',
                'tinkoff_payout_payment_id' => (string) ($row->tinkoff_payout_payment_id ?? ''),
            ];
        })->toArray();

        return response()->json([
            'draw' => (int) ($validated['draw'] ?? 0),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }
}

