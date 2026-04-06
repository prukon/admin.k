<?php

namespace App\Http\Controllers;

use App\Models\Partner;
use App\Models\PaymentSystem;
use App\Models\TinkoffPayout;
use App\Services\Tinkoff\TinkoffPayoutsService;
use App\Http\Requests\Tinkoff\Admin\TinkoffPayoutsDataTableRequest;
use App\Http\Requests\Tinkoff\Admin\TinkoffPayoutsSelect2PartnersSearchRequest;
use App\Http\Requests\Tinkoff\Admin\TinkoffPayoutsSelect2UsersSearchRequest;
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
        $currentPartnerId = (int) app('current_partner')->id;

        $partners = $isSuperadmin
            ? Partner::orderBy('title')->get(['id', 'title'])
            : Partner::query()->whereKey($currentPartnerId)->get(['id', 'title']);

        $paymentSystems = PaymentSystem::query()
            ->where('name', 'tbank')
            ->get(['partner_id', 'settings']);
        $autoPayoutByPartnerId = $paymentSystems->keyBy(fn ($ps) => (int) $ps->partner_id)
            ->map(fn ($ps) => (bool) (($ps->settings ?: [])['auto_payout_enabled'] ?? false));
        $partnersWithAuto = $partners->filter(fn ($p) => $autoPayoutByPartnerId[(int) $p->id] ?? false);
        $scheduledIntervalMinutes = \App\Models\Setting::getTinkoffPayoutScheduledIntervalMinutes();

        $overdueScheduledBase = TinkoffPayout::query()->overdueScheduled();
        if (!$isSuperadmin) {
            $overdueScheduledBase->where('partner_id', (int) app('current_partner')->id);
        }
        $overdueScheduledPayoutsCount = (int) (clone $overdueScheduledBase)->count();
        $overdueScheduledPayouts = (clone $overdueScheduledBase)
            ->with(['partner:id,title'])
            ->orderBy('when_to_run')
            ->limit(25)
            ->get();

        $toolbarTotals = $this->computeToolbarTotals(request(), [], $isSuperadmin, $currentPartnerId);

        return view('tinkoff.payouts.index', compact(
            'partners',
            'isSuperadmin',
            'autoPayoutByPartnerId',
            'partnersWithAuto',
            'scheduledIntervalMinutes',
            'overdueScheduledPayoutsCount',
            'overdueScheduledPayouts',
            'toolbarTotals'
        ));
    }

    /**
     * Select2: поиск партнёров по названию (только для superadmin).
     */
    public function partnersSearch(TinkoffPayoutsSelect2PartnersSearchRequest $request)
    {
        $actor = auth()->user();
        $isSuperadmin = $actor instanceof User && $actor->hasRole('superadmin');
        if (!$isSuperadmin) {
            abort(403);
        }

        $q = (string) ($request->validated()['q'] ?? '');

        $partners = Partner::query()
            ->when($q !== '', fn ($qq) => $qq->where('title', 'like', '%'.$q.'%'))
            ->orderBy('title')
            ->limit(50)
            ->get(['id', 'title']);

        $results = $partners->map(static function (Partner $p) {
            return [
                'id' => $p->id,
                'text' => (string) ($p->title ?? ''),
            ];
        });

        return response()->json(['results' => $results]);
    }

    /**
     * Select2: поиск плательщиков (users) по ФИО/почте/телефону, в рамках партнёра (или выбранного partner_id).
     */
    public function payersSearch(TinkoffPayoutsSelect2UsersSearchRequest $request)
    {
        $actor = auth()->user();
        $isSuperadmin = $actor instanceof User && $actor->hasRole('superadmin');
        $currentPartnerId = (int) app('current_partner')->id;

        $v = $request->validated();
        $q = (string) ($v['q'] ?? '');

        $partnerId = (int) ($v['partner_id'] ?? 0);
        if (!$isSuperadmin) {
            $partnerId = $currentPartnerId;
        } elseif ($partnerId <= 0) {
            $partnerId = $currentPartnerId;
        }

        $users = User::query()
            ->where('partner_id', $partnerId)
            ->when($q !== '', function ($qq) use ($q) {
                $needle = '%'.$q.'%';
                $qq->where(function ($w) use ($needle) {
                    $w->where('name', 'like', $needle)
                        ->orWhere('lastname', 'like', $needle)
                        ->orWhere('email', 'like', $needle)
                        ->orWhere('phone', 'like', $needle);
                });
            })
            ->orderBy('lastname')
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'name', 'lastname', 'email', 'phone', 'partner_id']);

        $results = $users->map(function (User $u) {
            $fio = trim(($u->lastname ?? '').' '.($u->name ?? ''));
            $tail = trim(implode(' · ', array_filter([
                $u->email ? (string) $u->email : null,
                $u->phone ? (string) $u->phone : null,
            ])));
            $text = $fio !== '' ? $fio : ('#'.$u->id);
            if ($tail !== '') {
                $text .= ' ('.$tail.')';
            }
            return [
                'id' => $u->id,
                'text' => $text,
                'partner_id' => (int) $u->partner_id,
            ];
        });

        return response()->json(['results' => $results]);
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
            ->leftJoin('payment_intents as pi_tbank', function ($join) {
                $join->on('pi_tbank.tbank_order_id', '=', 'tinkoff_payments.order_id')
                    ->whereColumn('pi_tbank.partner_id', 'tinkoff_payments.partner_id')
                    ->where('pi_tbank.provider', 'tbank');
            })
            ->leftJoin('users as payer', 'payer.id', '=', 'tinkoff_payouts.payer_user_id')
            ->leftJoin('users as initiator', 'initiator.id', '=', 'tinkoff_payouts.initiated_by_user_id')
            ->select([
                'tinkoff_payouts.*',
                'partners.title as partner_title',
                'tinkoff_payments.order_id as payment_order_id',
                'tinkoff_payments.status as payment_status',
                'tinkoff_payments.tinkoff_payment_id as tbank_payment_id',
                'tinkoff_payments.amount as payment_amount',
                'pi_tbank.provider_inv_id as payment_provider_inv_id',
                DB::raw("TRIM(CONCAT(COALESCE(payer.lastname,''),' ',COALESCE(payer.name,''))) as payer_name"),
                'payer.email as payer_email',
                'payer.phone as payer_phone',
                'payer.id as payer_id',
                DB::raw("TRIM(CONCAT(COALESCE(initiator.lastname,''),' ',COALESCE(initiator.name,''))) as initiator_name"),
                'initiator.email as initiator_email',
                'initiator.phone as initiator_phone',
                'initiator.id as initiator_id',
            ]);

        $this->applyPayoutsFilters($baseQuery, $request, $validated, $isSuperadmin, $currentPartnerId);

        // Общее количество (без фильтров) в рамках доступа
        $totalQuery = $this->basePayoutsTotalsQuery($isSuperadmin, $currentPartnerId, $validated);
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
                case 8: // provider_inv_id (payment_intents)
                    $baseQuery->orderBy('pi_tbank.provider_inv_id', $orderDir);
                    break;
                case 9: // deal_id
                    $baseQuery->orderBy('tinkoff_payouts.deal_id', $orderDir);
                    break;
                case 10: // gross
                    $baseQuery->orderByRaw('COALESCE(tinkoff_payouts.gross_amount, tinkoff_payments.amount) ' . $orderDir);
                    break;
                case 11: // bank fee (приём + выплата)
                    $baseQuery->orderByRaw(
                        '(COALESCE(tinkoff_payouts.bank_accept_fee, 0) + COALESCE(tinkoff_payouts.bank_payout_fee, 0)) ' . $orderDir
                    );
                    break;
                case 12: // platform fee
                    $baseQuery->orderBy('tinkoff_payouts.platform_fee', $orderDir);
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
                case 17: // tinkoff_payout_payment_id
                    $baseQuery->orderBy('tinkoff_payouts.tinkoff_payout_payment_id', $orderDir);
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

        $fmtRubWhole = function (?int $cents): string {
            if ($cents === null) {
                return '';
            }

            return (string) (int) round($cents / 100) . ' руб';
        };

        $data = $rows->map(function ($row) use ($fmtRubWhole) {
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

            $bankFeeCents = null;
            if ($row->bank_accept_fee !== null || $row->bank_payout_fee !== null) {
                $bankFeeCents = (int) ($row->bank_accept_fee ?? 0) + (int) ($row->bank_payout_fee ?? 0);
            }

            return [
                'id' => (int) $row->id,
                'status' => (string) $row->status,
                'source' => (string) ($row->source ?? ''),
                'partner' => (string) ($row->partner_title ?? ('#' . $row->partner_id)),
                'payer' => $payerLabel,
                'initiator' => $initLabel,
                'payment_id' => (int) ($row->payment_id ?? 0),
                'provider_inv_id' => $row->payment_provider_inv_id !== null && $row->payment_provider_inv_id !== ''
                    ? (string) $row->payment_provider_inv_id
                    : '—',
                'deal_id' => (string) ($row->deal_id ?? ''),
                'gross' => $fmtRubWhole($row->gross_amount ?? $row->payment_amount ?? null),
                'bank_fee' => $bankFeeCents === null ? '' : $fmtRubWhole($bankFeeCents),
                'platform_fee' => $fmtRubWhole($row->platform_fee ?? null),
                'net' => $fmtRubWhole($row->net_amount ?? $row->amount ?? null),
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

    /**
     * AJAX: сумма выплат по текущим фильтрам (как в отчётах).
     * Возвращает сумму в рублях (целые), чтобы на фронте анимация совпадала с “Платежи”.
     */
    public function total(TinkoffPayoutsDataTableRequest $request)
    {
        $validated = $request->validated();

        $actor = auth()->user();
        $isSuperadmin = $actor instanceof User && $actor->hasRole('superadmin');
        $currentPartnerId = (int) app('current_partner')->id;

        $totals = $this->computeToolbarTotals($request, $validated, $isSuperadmin, $currentPartnerId);

        return response()->json([
            'payments_total_formatted' => $totals['payments']['formatted'],
            'payments_total_raw' => $totals['payments']['raw'],
            'payouts_total_formatted' => $totals['payouts']['formatted'],
            'payouts_total_raw' => $totals['payouts']['raw'],
            'platform_fee_total_formatted' => $totals['platform_fee']['formatted'],
            'platform_fee_total_raw' => $totals['platform_fee']['raw'],
            // Совместимость: раньше «общая сумма» = сумма выплат (net)
            'total_formatted' => $totals['payouts']['formatted'],
            'total_raw' => $totals['payouts']['raw'],
        ]);
    }

    /**
     * Итоги для тулбара: сумма платежей (gross), сумма выплат (net), комиссия платформы — в копейках из БД, в ответе — целые рубли.
     *
     * @param  array<string, mixed>  $validated
     * @return array{payments: array{formatted: string, raw: float}, payouts: array{formatted: string, raw: float}, platform_fee: array{formatted: string, raw: float}}
     */
    private function computeToolbarTotals(Request $request, array $validated, bool $isSuperadmin, int $currentPartnerId): array
    {
        $q = $this->payoutsListQueryWithJoins();
        $this->applyPayoutsFilters($q, $request, $validated, $isSuperadmin, $currentPartnerId);

        $row = (clone $q)->selectRaw('
            COALESCE(SUM(COALESCE(tinkoff_payouts.gross_amount, tinkoff_payments.amount)), 0) as gross_cents,
            COALESCE(SUM(COALESCE(tinkoff_payouts.net_amount, tinkoff_payouts.amount)), 0) as net_cents,
            COALESCE(SUM(COALESCE(tinkoff_payouts.platform_fee, 0)), 0) as platform_cents
        ')->first();

        $fmt = static function (int $cents): array {
            $rub = (float) round($cents / 100);

            return [
                'formatted' => number_format($rub, 0, '', ' '),
                'raw' => $rub,
            ];
        };

        return [
            'payments' => $fmt((int) ($row->gross_cents ?? 0)),
            'payouts' => $fmt((int) ($row->net_cents ?? 0)),
            'platform_fee' => $fmt((int) ($row->platform_cents ?? 0)),
        ];
    }

    private function payoutsListQueryWithJoins()
    {
        return TinkoffPayout::query()
            ->leftJoin('partners', 'partners.id', '=', 'tinkoff_payouts.partner_id')
            ->leftJoin('tinkoff_payments', 'tinkoff_payments.id', '=', 'tinkoff_payouts.payment_id')
            ->leftJoin('users as payer', 'payer.id', '=', 'tinkoff_payouts.payer_user_id')
            ->leftJoin('users as initiator', 'initiator.id', '=', 'tinkoff_payouts.initiated_by_user_id');
    }

    private function basePayoutsTotalsQuery(bool $isSuperadmin, int $currentPartnerId, array $validated = [])
    {
        $q = TinkoffPayout::query();

        if (!$isSuperadmin) {
            $q->where('partner_id', $currentPartnerId);
        } elseif (!empty($validated['partner_id']) && ctype_digit((string) $validated['partner_id'])) {
            $q->where('partner_id', (int) $validated['partner_id']);
        }

        return $q;
    }

    private function applyPayoutsFilters($q, Request $request, array $validated, bool $isSuperadmin, int $currentPartnerId): void
    {
        if (!$isSuperadmin) {
            $q->where('tinkoff_payouts.partner_id', $currentPartnerId);
        } else {
            if ($request->filled('partner_id') && ctype_digit((string) ($validated['partner_id'] ?? ''))) {
                $q->where('tinkoff_payouts.partner_id', (int) $validated['partner_id']);
            }
        }

        if (!empty($validated['status'])) {
            $q->where('tinkoff_payouts.status', $validated['status']);
        }
        if (!empty($validated['source'])) {
            $q->where('tinkoff_payouts.source', $validated['source']);
        }
        if (!empty($validated['deal_id'])) {
            $q->where('tinkoff_payouts.deal_id', $validated['deal_id']);
        }
        if (!empty($validated['tinkoff_payout_payment_id'])) {
            $q->where('tinkoff_payouts.tinkoff_payout_payment_id', $validated['tinkoff_payout_payment_id']);
        }
        if (!empty($validated['tinkoff_payment_id'])) {
            $q->where('tinkoff_payouts.payment_id', (int) $validated['tinkoff_payment_id']);
        }

        if (!empty($validated['payer_id'])) {
            $q->where('tinkoff_payouts.payer_user_id', (int) $validated['payer_id']);
        } else {
            $payerQ = trim((string) ($validated['payer_query'] ?? ''));
            if ($payerQ !== '') {
                $q->where(function ($w) use ($payerQ) {
                    if (ctype_digit($payerQ)) {
                        $w->orWhere('payer.id', (int) $payerQ);
                    }
                    $like = '%' . $payerQ . '%';
                    $w->orWhere('payer.name', 'like', $like)
                        ->orWhere('payer.lastname', 'like', $like)
                        ->orWhere('payer.email', 'like', $like)
                        ->orWhere('payer.phone', 'like', $like);
                });
            }
        }

        $initQ = trim((string) ($validated['initiator_query'] ?? ''));
        if ($initQ !== '') {
            $q->where(function ($w) use ($initQ) {
                if (ctype_digit($initQ)) {
                    $w->orWhere('initiator.id', (int) $initQ);
                }
                $like = '%' . $initQ . '%';
                $w->orWhere('initiator.name', 'like', $like)
                    ->orWhere('initiator.lastname', 'like', $like)
                    ->orWhere('initiator.email', 'like', $like)
                    ->orWhere('initiator.phone', 'like', $like);
            });
        }

        if (!empty($validated['created_from'])) {
            $q->where('tinkoff_payouts.created_at', '>=', Carbon::parse($validated['created_from'])->startOfDay());
        }
        if (!empty($validated['created_to'])) {
            $q->where('tinkoff_payouts.created_at', '<=', Carbon::parse($validated['created_to'])->endOfDay());
        }
        if (!empty($validated['run_from'])) {
            $q->where('tinkoff_payouts.when_to_run', '>=', Carbon::parse($validated['run_from'])->startOfDay());
        }
        if (!empty($validated['run_to'])) {
            $q->where('tinkoff_payouts.when_to_run', '<=', Carbon::parse($validated['run_to'])->endOfDay());
        }
        if (!empty($validated['completed_from'])) {
            $q->where('tinkoff_payouts.completed_at', '>=', Carbon::parse($validated['completed_from'])->startOfDay());
        }
        if (!empty($validated['completed_to'])) {
            $q->where('tinkoff_payouts.completed_at', '<=', Carbon::parse($validated['completed_to'])->endOfDay());
        }

        if (isset($validated['gross_min'])) {
            $min = (int) round(((float) $validated['gross_min']) * 100);
            $q->whereRaw('COALESCE(tinkoff_payouts.gross_amount, tinkoff_payments.amount) >= ?', [$min]);
        }
        if (isset($validated['gross_max'])) {
            $max = (int) round(((float) $validated['gross_max']) * 100);
            $q->whereRaw('COALESCE(tinkoff_payouts.gross_amount, tinkoff_payments.amount) <= ?', [$max]);
        }
        if (isset($validated['net_min'])) {
            $min = (int) round(((float) $validated['net_min']) * 100);
            $q->whereRaw('COALESCE(tinkoff_payouts.net_amount, tinkoff_payouts.amount) >= ?', [$min]);
        }
        if (isset($validated['net_max'])) {
            $max = (int) round(((float) $validated['net_max']) * 100);
            $q->whereRaw('COALESCE(tinkoff_payouts.net_amount, tinkoff_payouts.amount) <= ?', [$max]);
        }

        if ($request->boolean('stuck_only')) {
            $minutes = (int) ($validated['stuck_minutes'] ?? 60);
            $q->whereIn('tinkoff_payouts.status', [
                'INITIATED', 'NEW', 'AUTHORIZING', 'CHECKING', 'CREDIT_CHECKING', 'COMPLETING',
            ])->where('tinkoff_payouts.updated_at', '<=', now()->subMinutes($minutes));
        }
    }
}

