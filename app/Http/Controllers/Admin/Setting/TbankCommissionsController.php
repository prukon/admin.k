<?php

namespace App\Http\Controllers\Admin\Setting;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\PaymentSystem;
use App\Models\TinkoffCommissionRule;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TbankCommissionsController extends Controller
{
    public function index()
    {
        $rules = TinkoffCommissionRule::orderByRaw('partner_id IS NULL DESC, method IS NULL DESC')->paginate(30);
        $partners = Partner::orderBy('title')->get(['id', 'title']);

        // is_connected — НЕ колонка, а аксессор модели => не выбираем в select
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

        return view('admin.setting.index', [
            'activeTab' => 'tbankCommissions',
            'mode' => 'list',
            'rules' => $rules,
            'partners' => $partners,
            'autoPayoutByPartnerId' => $autoPayoutByPartnerId,
            'tbankConnectedByPartnerId' => $tbankConnectedByPartnerId,
        ]);
    }

    public function create()
    {
        $partners = Partner::orderBy('title')->get(['id', 'title']);

        return view('admin.setting.index', [
            'activeTab' => 'tbankCommissions',
            'mode' => 'create',
            'partners' => $partners,
            'rule' => null,
        ]);
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

        return view('admin.setting.index', [
            'activeTab' => 'tbankCommissions',
            'mode' => 'edit',
            'rule' => $rule,
            'partners' => $partners,
            'autoPayoutByPartnerId' => $autoPayoutByPartnerId,
            'tbankConnectedByPartnerId' => $tbankConnectedByPartnerId,
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