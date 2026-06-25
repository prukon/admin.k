<?php

namespace App\Http\Controllers\Admin\Setting;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\Setting;
use App\Models\TinkoffCommissionRule;
use App\Models\TinkoffPayout;
use App\Services\Tinkoff\TbankTerminalConfig;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TbankCommissionsController extends Controller
{
    public function index()
    {
        $maps = $this->tinkoffCommissionRulesAuxiliaryMaps();

        return view('admin.setting.index', array_merge([
            'activeTab' => 'tbankCommissions',
            'mode' => 'list',
            'partners' => Partner::orderBy('title')->get(['id', 'title']),
            'payoutScheduledIntervalMinutes' => Setting::getTinkoffPayoutScheduledIntervalMinutes(),
            'tbankGloballyConnected' => TbankTerminalConfig::isGloballyActive(),
        ], $maps));
    }

    /**
     * Данные для DataTables: серверная пагинация, поиск, сортировка, фильтры.
     */
    public function data(Request $request)
    {
        $request->validate([
            'draw' => 'nullable|integer',
            'start' => 'nullable|integer',
            'length' => 'nullable|integer',
            'filter_partner_id' => ['nullable', 'integer', Rule::exists('partners', 'id')],
            'filter_method' => ['nullable', Rule::in(['card', 'sbp', 'tpay'])],
        ]);

        $maps = $this->tinkoffCommissionRulesAuxiliaryMaps();

        $base = TinkoffCommissionRule::query()
            ->leftJoin('partners', 'partners.id', '=', 'tinkoff_commission_rules.partner_id')
            ->select('tinkoff_commission_rules.*');

        $filtered = clone $base;

        if ($request->filled('filter_partner_id')) {
            $filtered->where('tinkoff_commission_rules.partner_id', (int) $request->input('filter_partner_id'));
        }

        if ($request->filled('filter_method')) {
            $filtered->where('tinkoff_commission_rules.method', $request->input('filter_method'));
        }

        $searchTerm = trim((string) $request->input('search.value', ''));
        if ($searchTerm !== '') {
            $like = '%' . addcslashes($searchTerm, '%_\\') . '%';
            $filtered->where(function ($q) use ($like) {
                $q->where('partners.title', 'like', $like)
                    ->orWhere('tinkoff_commission_rules.method', 'like', $like)
                    ->orWhereRaw('CAST(tinkoff_commission_rules.acquiring_percent AS CHAR) LIKE ?', [$like])
                    ->orWhereRaw('CAST(tinkoff_commission_rules.acquiring_min_fixed AS CHAR) LIKE ?', [$like])
                    ->orWhereRaw('CAST(tinkoff_commission_rules.payout_percent AS CHAR) LIKE ?', [$like])
                    ->orWhereRaw('CAST(tinkoff_commission_rules.payout_min_fixed AS CHAR) LIKE ?', [$like])
                    ->orWhereRaw('CAST(tinkoff_commission_rules.platform_percent AS CHAR) LIKE ?', [$like])
                    ->orWhereRaw('CAST(tinkoff_commission_rules.platform_min_fixed AS CHAR) LIKE ?', [$like]);
            });
        }

        $totalRecords = TinkoffCommissionRule::query()->count();
        $recordsFiltered = (clone $filtered)->count();

        $orderColumnIndex = $request->input('order.0.column');
        $orderDir = strtolower((string) $request->input('order.0.dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        if ($orderColumnIndex !== null) {
            match ((int) $orderColumnIndex) {
                1 => $filtered->orderBy('partners.title', $orderDir)
                    ->orderBy('tinkoff_commission_rules.id', 'asc'),
                2 => $filtered->orderBy('tinkoff_commission_rules.method', $orderDir)
                    ->orderBy('tinkoff_commission_rules.id', 'asc'),
                3 => $filtered->orderBy('tinkoff_commission_rules.acquiring_percent', $orderDir)
                    ->orderBy('tinkoff_commission_rules.acquiring_min_fixed', $orderDir)
                    ->orderBy('tinkoff_commission_rules.id', 'asc'),
                4 => $filtered->orderBy('tinkoff_commission_rules.payout_percent', $orderDir)
                    ->orderBy('tinkoff_commission_rules.payout_min_fixed', $orderDir)
                    ->orderBy('tinkoff_commission_rules.id', 'asc'),
                5 => $filtered->orderBy('tinkoff_commission_rules.platform_percent', $orderDir)
                    ->orderBy('tinkoff_commission_rules.platform_min_fixed', $orderDir)
                    ->orderBy('tinkoff_commission_rules.id', 'asc'),
                8 => $filtered->orderBy('tinkoff_commission_rules.is_enabled', $orderDir)
                    ->orderBy('tinkoff_commission_rules.id', 'asc'),
                default => $filtered->orderByRaw('tinkoff_commission_rules.partner_id IS NULL DESC')
                    ->orderByRaw('tinkoff_commission_rules.method IS NULL DESC')
                    ->orderBy('tinkoff_commission_rules.id', 'asc'),
            };
        } else {
            $filtered->orderByRaw('tinkoff_commission_rules.partner_id IS NULL DESC')
                ->orderByRaw('tinkoff_commission_rules.method IS NULL DESC')
                ->orderBy('tinkoff_commission_rules.id', 'asc');
        }

        $start = max(0, (int) $request->input('start', 0));
        $length = (int) $request->input('length', 20);
        if ($length <= 0) {
            $length = 20;
        }
        if ($length > 100) {
            $length = 100;
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, TinkoffCommissionRule> $rules */
        $rules = $filtered
            ->clone()
            ->with('partner')
            ->skip($start)
            ->take($length)
            ->get();

        $tbankGloballyConnected = TbankTerminalConfig::isGloballyActive();

        $data = $rules->map(function (TinkoffCommissionRule $rule) use ($maps, $tbankGloballyConnected) {
            $acquiringPercent = (float) ($rule->acquiring_percent ?? 2.49);
            $acquiringMin = (float) ($rule->acquiring_min_fixed ?? 3.49);
            $payoutPercent = (float) ($rule->payout_percent ?? 0.10);
            $payoutMin = (float) ($rule->payout_min_fixed ?? 0.00);
            $platformPercent = (float) ($rule->platform_percent ?? $rule->percent ?? 0);
            $platformMin = (float) ($rule->platform_min_fixed ?? $rule->min_fixed ?? 0.00);

            $partnerId = (int) ($rule->partner_id ?? 0);
            $partnerTitle = $partnerId > 0
                ? (string) (optional($rule->partner)->title ?? ('#'.$partnerId))
                : '(глобально)';

            $autoPayoutEnabled = null;
            $autoPayoutDelayHours = null;
            $autoPayoutLabel = null;
            $payouts30dCount = null;
            $payouts30dUrl = null;
            if ($partnerId > 0) {
                $autoPayoutEnabled = (bool) $rule->auto_payout_enabled;
                $autoPayoutDelayHours = (int) $rule->auto_payout_delay_hours;
                $autoPayoutLabel = $autoPayoutEnabled
                    ? ('да, '.$autoPayoutDelayHours.' ч')
                    : 'нет';
                $statsRow = $maps['autoPayoutStatsByPartnerId']->get($partnerId);
                $payouts30dCount = (int) ($statsRow['count'] ?? 0);
                $payouts30dUrl = url('/admin/tinkoff/payouts?partner_id='.$partnerId.'&source=auto');
            }

            $enabledOn = (bool) $rule->is_enabled;

            return [
                'id' => (int) $rule->id,
                'partner_title' => $partnerTitle,
                'partner_id' => $partnerId > 0 ? $partnerId : null,
                'tbank_keys_connected' => $partnerId > 0 ? $tbankGloballyConnected : null,
                'method' => $rule->method ?? '—',
                'acquiring_percent' => $acquiringPercent,
                'acquiring_min_fixed' => $acquiringMin,
                'payout_percent' => $payoutPercent,
                'payout_min_fixed' => $payoutMin,
                'platform_percent' => $platformPercent,
                'platform_min_fixed' => $platformMin,
                'auto_payout_enabled' => $autoPayoutEnabled,
                'auto_payout_delay_hours' => $autoPayoutDelayHours,
                'auto_payout_label' => $autoPayoutLabel,
                'payouts_30d_count' => $payouts30dCount,
                'payouts_30d_url' => $payouts30dUrl,
                'is_enabled' => $enabledOn,
                'enabled_label' => $enabledOn ? 'on' : 'off',
            ];
        })->values()->all();

        return response()->json([
            'draw' => (int) $request->input('draw', 0),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    /**
     * @return array{autoPayoutStatsByPartnerId: \Illuminate\Support\Collection}
     */
    private function tinkoffCommissionRulesAuxiliaryMaps(): array
    {
        $partnerIds = TinkoffCommissionRule::query()
            ->whereNotNull('partner_id')
            ->pluck('partner_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $autoPayoutStatsByPartnerId = collect();
        if (! empty($partnerIds)) {
            foreach ($partnerIds as $pid) {
                $autoPayoutStatsByPartnerId[$pid] = ['count' => 0, 'last_at' => null];
            }
            $stats = TinkoffPayout::query()
                ->where('source', 'auto')
                ->whereIn('partner_id', $partnerIds)
                ->where('created_at', '>=', now()->subDays(30))
                ->selectRaw('partner_id, count(*) as cnt, max(created_at) as last_at')
                ->groupBy('partner_id')
                ->get();
            foreach ($stats as $row) {
                $autoPayoutStatsByPartnerId[(int) $row->partner_id] = [
                    'count' => (int) $row->cnt,
                    'last_at' => $row->last_at ? Carbon::parse($row->last_at) : null,
                ];
            }
        }

        return [
            'autoPayoutStatsByPartnerId' => $autoPayoutStatsByPartnerId,
        ];
    }

    /**
     * Сохранение глобальных настроек выплат (интервал джобы).
     */
    public function updatePayoutSettings(Request $r)
    {
        $validated = $r->validate([
            'payout_scheduled_interval_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
        ]);

        Setting::setTinkoffPayoutScheduledIntervalMinutes((int) $validated['payout_scheduled_interval_minutes']);

        return redirect()
            ->route('admin.setting.tbankCommissions')
            ->with('status', 'Настройки выплат сохранены. Изменение интервала джобы применится после перезапуска планировщика.');
    }

    public function create()
    {
        return redirect()->route('admin.setting.tbankCommissions', ['open_create' => 1]);
    }

    public function store(Request $r)
    {
        $data = $this->validateCommissionRulePayload($r);

        TinkoffCommissionRule::create($data);

        return redirect()
            ->route('admin.setting.tbankCommissions')
            ->with('status', 'Правило создано');
    }

    public function edit(int $id)
    {
        $rule = TinkoffCommissionRule::findOrFail($id);
        $partners = Partner::orderBy('title')->get(['id', 'title']);

        $rulePartnerId = (int) ($rule->partner_id ?? 0);
        $autoPayoutStatsByPartnerId = collect();
        if ($rulePartnerId > 0) {
            $row = TinkoffPayout::query()
                ->where('source', 'auto')
                ->where('partner_id', $rulePartnerId)
                ->where('created_at', '>=', now()->subDays(30))
                ->selectRaw('count(*) as cnt, max(created_at) as last_at')
                ->first();
            $autoPayoutStatsByPartnerId = collect([$rulePartnerId => [
                'count' => (int) ($row->cnt ?? 0),
                'last_at' => isset($row->last_at) && $row->last_at ? Carbon::parse($row->last_at) : null,
            ]]);
        }

        return view('admin.setting.index', [
            'activeTab' => 'tbankCommissions',
            'mode' => 'edit',
            'rule' => $rule,
            'partners' => $partners,
            'tbankGloballyConnected' => TbankTerminalConfig::isGloballyActive(),
            'autoPayoutStatsByPartnerId' => $autoPayoutStatsByPartnerId,
        ]);
    }

    public function update(Request $r, int $id)
    {
        $rule = TinkoffCommissionRule::findOrFail($id);
        $data = $this->validateCommissionRulePayload($r);
        $rule->update($data);

        return redirect()
            ->route('admin.setting.tbankCommissions')
            ->with('status', 'Правило обновлено');
    }

    public function destroy(int $id)
    {
        TinkoffCommissionRule::whereKey($id)->delete();

        return back()->with('status', 'Правило удалено');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateCommissionRulePayload(Request $r): array
    {
        $rules = [
            'partner_id' => ['nullable', 'integer'],
            'method' => ['nullable', Rule::in(['card', 'sbp', 'tpay'])],
            'acquiring_percent' => ['required', 'numeric', 'min:0'],
            'acquiring_min_fixed' => ['required', 'numeric', 'min:0'],
            'payout_percent' => ['required', 'numeric', 'min:0'],
            'payout_min_fixed' => ['required', 'numeric', 'min:0'],
            'platform_percent' => ['required', 'numeric', 'min:0'],
            'platform_min_fixed' => ['required', 'numeric', 'min:0'],
            'min_fixed' => ['sometimes', 'numeric', 'min:0'],
            'is_enabled' => ['sometimes', 'boolean'],
            'auto_payout_enabled' => ['sometimes', 'boolean'],
            'auto_payout_delay_hours' => ['nullable', 'integer', 'min:0', 'max:720'],
        ];

        if ($r->filled('partner_id') && (int) $r->input('partner_id') > 0) {
            $rules['auto_payout_delay_hours'] = ['required', 'integer', 'min:0', 'max:720'];
        }

        $data = $r->validate($rules);

        $data['is_enabled'] = $r->boolean('is_enabled');
        $data['min_fixed'] = (float) ($data['min_fixed'] ?? 0);

        if (! empty($data['partner_id']) && (int) $data['partner_id'] > 0) {
            $data['auto_payout_enabled'] = $r->boolean('auto_payout_enabled');
            $data['auto_payout_delay_hours'] = (int) $data['auto_payout_delay_hours'];
        } else {
            $data['auto_payout_enabled'] = false;
            $data['auto_payout_delay_hours'] = 0;
        }

        if (empty($data['partner_id'])) {
            $data['partner_id'] = null;
        }

        if (empty($data['method'])) {
            $data['method'] = null;
        }

        return $data;
    }
}
