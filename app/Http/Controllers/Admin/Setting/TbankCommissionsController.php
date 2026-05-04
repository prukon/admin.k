<?php

namespace App\Http\Controllers\Admin\Setting;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\PaymentSystem;
use App\Models\Setting;
use App\Models\TinkoffCommissionRule;
use App\Models\TinkoffPayout;
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
            'payoutAutoDelayHours' => Setting::getTinkoffPayoutAutoDelayHours(),
            'payoutScheduledIntervalMinutes' => Setting::getTinkoffPayoutScheduledIntervalMinutes(),
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
                // 6–7: автовыплата и выплат за 30 дн. — из смежных таблиц/агрегатов, сортировка отключена на клиенте
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

        $csrf = csrf_token();
        $data = $rules->map(function (TinkoffCommissionRule $rule) use ($maps, $csrf) {
            $partnerCell = view('admin.setting.tbank_commissions._partner_cell', [
                'r' => $rule,
                'tbankConnectedByPartnerId' => $maps['tbankConnectedByPartnerId'],
            ])->render();

            $acquiringPercent = (float) ($rule->acquiring_percent ?? 2.49);
            $acquiringMin = (float) ($rule->acquiring_min_fixed ?? 3.49);
            $payoutPercent = (float) ($rule->payout_percent ?? 0.10);
            $payoutMin = (float) ($rule->payout_min_fixed ?? 0.00);
            $platformPercent = (float) ($rule->platform_percent ?? $rule->percent ?? 0);
            $platformMin = (float) ($rule->platform_min_fixed ?? $rule->min_fixed ?? 0.00);

            $acquiringHtml = $this->formatCommissionPercentCell($acquiringPercent, $acquiringMin);
            $payoutHtml = $this->formatCommissionPercentCell($payoutPercent, $payoutMin);
            $platformHtml = $this->formatCommissionPercentCell($platformPercent, $platformMin);

            $partnerId = (int) ($rule->partner_id ?? 0);
            if ($partnerId > 0) {
                $autoOn = (bool) ($maps['autoPayoutByPartnerId'][$partnerId] ?? false);
                $autoPayoutHtml = $autoOn
                    ? '<span class="badge text-bg-success">да</span>'
                    : '<span class="badge text-bg-secondary">нет</span>';

                $statsRow = $maps['autoPayoutStatsByPartnerId']->get($partnerId);
                $cnt = (int) (($statsRow['count'] ?? 0));
                $payoutListUrl = e(url('/admin/tinkoff/payouts?partner_id=' . $partnerId . '&source=auto'));
                $payouts30dHtml = '<a href="' . $payoutListUrl . '" class="link-primary fw-semibold" target="_blank" title="Выплаты (авто) за 30 дней">' . $cnt . '</a>';
            } else {
                $autoPayoutHtml = '—';
                $payouts30dHtml = '—';
            }

            $enabledOn = (bool) $rule->is_enabled;
            $enabledHtml = $enabledOn
                ? '<span class="badge text-bg-success">on</span>'
                : '<span class="badge text-bg-secondary">off</span>';

            $editUrl = e(route('admin.setting.tbankCommissions.edit', ['id' => $rule->id]));
            $destroyUrl = e(route('admin.setting.tbankCommissions.destroy', ['id' => $rule->id]));

            $actionsHtml = <<<HTML
<div class="text-start text-nowrap">
    <a class="btn btn-outline-primary btn-sm" href="{$editUrl}">Изменить</a>
    <form action="{$destroyUrl}" method="post" class="d-inline-block ms-1" onsubmit="return confirm('Удалить правило?');">
        <input type="hidden" name="_token" value="{$csrf}">
        <input type="hidden" name="_method" value="DELETE">
        <button type="submit" class="btn btn-outline-danger btn-sm">Удалить</button>
    </form>
</div>
HTML;

            return [
                'partner_cell' => $partnerCell,
                'method' => $rule->method ?? '—',
                'acquiring_html' => $acquiringHtml,
                'payout_html' => $payoutHtml,
                'platform_html' => $platformHtml,
                'auto_payout_html' => $autoPayoutHtml,
                'payouts_30d_html' => $payouts30dHtml,
                'enabled_html' => $enabledHtml,
                'actions_html' => $actionsHtml,
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
     * Карты для отображения автовыплат и связи Т‑Банк по партнёрам (список и AJAX).
     *
     * @return array{
     *     autoPayoutByPartnerId: \Illuminate\Support\Collection,
     *     tbankConnectedByPartnerId: \Illuminate\Support\Collection,
     *     autoPayoutStatsByPartnerId: \Illuminate\Support\Collection
     * }
     */
    private function tinkoffCommissionRulesAuxiliaryMaps(): array
    {
        $paymentSystems = PaymentSystem::query()
            ->where('name', 'tbank')
            ->get(['id', 'partner_id', 'name', 'settings', 'test_mode']);

        $autoPayoutByPartnerId = $paymentSystems
            ->keyBy(fn ($ps) => (int) $ps->partner_id)
            ->map(function ($ps) {
                $settings = $ps->settings ?: [];

                return (bool) ($settings['auto_payout_enabled'] ?? false);
            });

        $tbankConnectedByPartnerId = $paymentSystems
            ->keyBy(fn ($ps) => (int) $ps->partner_id)
            ->map(fn ($ps) => (bool) $ps->is_connected);

        $partnerIds = $paymentSystems->pluck('partner_id')->map(fn ($id) => (int) $id)->filter(fn ($id) => $id > 0)->unique()->values()->all();
        $autoPayoutStatsByPartnerId = collect();
        if (!empty($partnerIds)) {
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
            'autoPayoutByPartnerId' => $autoPayoutByPartnerId,
            'tbankConnectedByPartnerId' => $tbankConnectedByPartnerId,
            'autoPayoutStatsByPartnerId' => $autoPayoutStatsByPartnerId,
        ];
    }

    private function formatCommissionPercentCell(float $percent, float $minFixed): string
    {
        $p = number_format($percent, 2, ',', ' ');
        $m = number_format($minFixed, 2, ',', ' ');

        return '<div>' . e($p) . '%</div><div class="text-muted small">мин ' . e($m) . ' ₽</div>';
    }

    /**
     * Сохранение глобальных настроек выплат (задержка автовыплаты, интервал джобы).
     */
    public function updatePayoutSettings(Request $r)
    {
        $validated = $r->validate([
            'payout_auto_delay_hours' => ['required', 'integer', 'min:0', 'max:720'],
            'payout_scheduled_interval_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
        ]);

        Setting::setTinkoffPayoutAutoDelayHours((int) $validated['payout_auto_delay_hours']);
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
        $data = $r->validate([
            'partner_id' => ['nullable', 'integer'],
            'method' => ['nullable', Rule::in(['card', 'sbp', 'tpay'])],

            // 3 комиссии: банк-эквайринг, банк-выплата, платформа
            'acquiring_percent' => ['required', 'numeric', 'min:0'],
            'acquiring_min_fixed' => ['required', 'numeric', 'min:0'],
            'payout_percent' => ['required', 'numeric', 'min:0'],
            'payout_min_fixed' => ['required', 'numeric', 'min:0'],
            'platform_percent' => ['required', 'numeric', 'min:0'],
            'platform_min_fixed' => ['required', 'numeric', 'min:0'],

            // в таблице есть min_fixed NOT NULL default 0.00
            'min_fixed' => ['sometimes', 'numeric', 'min:0'],

            'is_enabled' => ['sometimes', 'boolean'],
        ]);

        $data['is_enabled'] = $r->boolean('is_enabled');
        $data['min_fixed'] = (float) ($data['min_fixed'] ?? 0);

        TinkoffCommissionRule::create($data);

        return redirect()
            ->route('admin.setting.tbankCommissions')
            ->with('status', 'Правило создано');
    }

    public function edit(int $id)
    {
        $rule = TinkoffCommissionRule::findOrFail($id);
        $partners = Partner::orderBy('title')->get(['id', 'title']);

        // is_connected — аксессор, а не колонка
        $paymentSystems = PaymentSystem::query()
            ->where('name', 'tbank')
            ->get(['id', 'partner_id', 'name', 'settings', 'test_mode']);

        $autoPayoutByPartnerId = $paymentSystems
            ->keyBy(fn ($ps) => (int) $ps->partner_id)
            ->map(function ($ps) {
                $settings = $ps->settings ?: [];
                return (bool) ($settings['auto_payout_enabled'] ?? false);
            });

        $tbankConnectedByPartnerId = $paymentSystems
            ->keyBy(fn ($ps) => (int) $ps->partner_id)
            ->map(fn ($ps) => (bool) $ps->is_connected);

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
            'autoPayoutByPartnerId' => $autoPayoutByPartnerId,
            'tbankConnectedByPartnerId' => $tbankConnectedByPartnerId,
            'autoPayoutStatsByPartnerId' => $autoPayoutStatsByPartnerId,
        ]);
    }

    public function update(Request $r, int $id)
    {
        \Log::info('TBANK UPDATE DEBUG - START', [
            'rule_id' => $id,
            'request_all' => $r->all(),
            'request_method' => $r->method(),
            'url' => $r->fullUrl(),
            'user_id' => optional(auth()->user())->id,
            'session_current_partner' => session('current_partner'),
        ]);

        $rule = TinkoffCommissionRule::findOrFail($id);

        $data = $r->validate([
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
        ]);

        \Log::info('TBANK UPDATE DEBUG - AFTER VALIDATE', [
            'rule_id' => $id,
            'validated_data' => $data,
            'has_partner_id' => array_key_exists('partner_id', $data),
            'partner_id_value' => $data['partner_id'] ?? null,
            'has_auto_payout_key' => array_key_exists('auto_payout_enabled', $data),
            'auto_payout_value' => $data['auto_payout_enabled'] ?? null,
        ]);

        $data['is_enabled'] = $r->boolean('is_enabled');
        $data['min_fixed'] = (float) ($data['min_fixed'] ?? 0);

        \Log::info('TBANK UPDATE DEBUG - NORMALIZED', [
            'rule_id' => $id,
            'is_enabled_normalized' => $data['is_enabled'],
            'min_fixed_normalized' => $data['min_fixed'],
            'auto_payout_from_request_boolean' => $r->boolean('auto_payout_enabled'),
        ]);

        // Обновляем правило
        $ruleData = $data;
        unset($ruleData['auto_payout_enabled']);
        $rule->update($ruleData);

        \Log::info('TBANK UPDATE DEBUG - RULE UPDATED', [
            'rule_id' => $id,
            'rule_partner_id_after' => $rule->partner_id,
            'rule_method_after' => $rule->method,
        ]);

        // Автовыплата: сохраняем как настройку T-Bank в разрезе партнёра правила.
        // ВАЖНО: partner_id может быть null/0/"" => блок не выполняется.
        if (!empty($data['partner_id'])) {
            $partnerId = (int) $data['partner_id'];

            // чекбокс может не прийти — значит false
//            $enabled = (bool) ($data['auto_payout_enabled'] ?? false);
            $enabled = $r->boolean('auto_payout_enabled');

            \Log::info('TBANK UPDATE DEBUG - ENTER AUTO PAYOUT BLOCK', [
                'rule_id' => $id,
                'partner_id_raw' => $data['partner_id'],
                'partner_id_casted' => $partnerId,
                'auto_payout_raw_from_data' => $data['auto_payout_enabled'] ?? null,
                'enabled_calculated' => $enabled,
                'enabled_from_request_boolean' => $r->boolean('auto_payout_enabled'),
            ]);

            $ps = PaymentSystem::firstOrCreate(
                ['partner_id' => $partnerId, 'name' => 'tbank'],
                // не пустой массив, чтобы мутатор не превратил в NULL
                ['settings' => ['auto_payout_enabled' => $enabled], 'test_mode' => false]
            );

            \Log::info('TBANK UPDATE DEBUG - PAYMENT SYSTEM FOUND/CREATED', [
                'ps_id' => $ps->id,
                'ps_partner_id' => $ps->partner_id,
                'ps_name' => $ps->name,
                'ps_settings_before' => $ps->settings,
                'ps_settings_raw_before' => $ps->getRawOriginal('settings'),
            ]);

            $settings = $ps->settings ?: [];
            $settings['auto_payout_enabled'] = $enabled;

            \Log::info('TBANK UPDATE DEBUG - PAYMENT SYSTEM SETTINGS TO SAVE', [
                'ps_id' => $ps->id,
                'settings_to_save' => $settings,
            ]);

            $ps->settings = $settings;
            $ps->save();

            // перечитываем свежую версию
            $ps->refresh();

            \Log::info('TBANK UPDATE DEBUG - PAYMENT SYSTEM AFTER SAVE', [
                'ps_id' => $ps->id,
                'ps_settings_after' => $ps->settings,
                'ps_settings_raw_after' => $ps->getRawOriginal('settings'),
            ]);
        } else {
            \Log::info('TBANK UPDATE DEBUG - SKIP AUTO PAYOUT BLOCK (empty partner_id)', [
                'rule_id' => $id,
                'partner_id_in_data' => $data['partner_id'] ?? null,
            ]);
        }

        \Log::info('TBANK UPDATE DEBUG - END', [
            'rule_id' => $id,
        ]);

        return redirect()
            ->route('admin.setting.tbankCommissions')
            ->with('status', 'Правило обновлено');
    }

    public function destroy(int $id)
    {
        // Текущее поведение: не найдено — просто удалится 0 строк и будет redirect back.
        // Если захочешь 404 на несуществующий id — поменяем на findOrFail()->delete().
        TinkoffCommissionRule::whereKey($id)->delete();

        return back()->with('status', 'Правило удалено');
    }
}