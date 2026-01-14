<?php

namespace App\Http\Controllers\Admin\Setting;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\PaymentSystem;
use App\Models\TinkoffCommissionRule;
use Illuminate\Http\Request;

class TbankCommissionsController extends Controller
{
    public function index()
    {
        $rules = TinkoffCommissionRule::orderByRaw('partner_id IS NULL DESC, method IS NULL DESC')->paginate(30);
        $partners = Partner::orderBy('title')->get(['id', 'title']);

        // Настройка автовыплаты — в разрезе партнёра (sup-admin настраивает для любого партнёра).
        $paymentSystems = PaymentSystem::query()
            ->where('name', 'tbank')
            ->get(['id', 'partner_id', 'name', 'settings', 'test_mode']);

        $autoPayoutByPartnerId = $paymentSystems
            ->keyBy(fn ($ps) => (int) $ps->partner_id)
            ->map(fn ($ps) => (bool) ($ps->settings['auto_payout_enabled'] ?? false));

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
            'partner_id' => 'nullable|integer',
            'method' => 'nullable|in:card,sbp,tpay',
            // 3 комиссии: банк-эквайринг, банк-выплата, платформа
            'acquiring_percent' => 'required|numeric|min:0',
            'acquiring_min_fixed' => 'required|numeric|min:0',
            'payout_percent' => 'required|numeric|min:0',
            'payout_min_fixed' => 'required|numeric|min:0',
            'platform_percent' => 'required|numeric|min:0',
            'platform_min_fixed' => 'required|numeric|min:0',
            'is_enabled' => 'sometimes|boolean',
        ]);

        $data['is_enabled'] = $r->boolean('is_enabled');
        TinkoffCommissionRule::create($data);

        return redirect()
            ->route('admin.setting.tbankCommissions')
            ->with('status', 'Правило создано');
    }

    public function edit(int $id)
    {
        $rule = TinkoffCommissionRule::findOrFail($id);
        $partners = Partner::orderBy('title')->get(['id', 'title']);

        $paymentSystems = PaymentSystem::query()
            ->where('name', 'tbank')
            ->get(['id', 'partner_id', 'name', 'settings', 'test_mode']);

        $autoPayoutByPartnerId = $paymentSystems
            ->keyBy(fn ($ps) => (int) $ps->partner_id)
            ->map(fn ($ps) => (bool) ($ps->settings['auto_payout_enabled'] ?? false));

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
        $rule = TinkoffCommissionRule::findOrFail($id);

        $data = $r->validate([
            'partner_id' => 'nullable|integer',
            'method' => 'nullable|in:card,sbp,tpay',
            'acquiring_percent' => 'required|numeric|min:0',
            'acquiring_min_fixed' => 'required|numeric|min:0',
            'payout_percent' => 'required|numeric|min:0',
            'payout_min_fixed' => 'required|numeric|min:0',
            'platform_percent' => 'required|numeric|min:0',
            'platform_min_fixed' => 'required|numeric|min:0',
            'is_enabled' => 'sometimes|boolean',
            'auto_payout_enabled' => 'sometimes|boolean',
        ]);

        $data['is_enabled'] = $r->boolean('is_enabled');
        $rule->update($data);

        // Автовыплата: сохраняем как настройку T-Bank в разрезе партнёра правила.
        // Если partner_id не задан (глобальное правило) — пропускаем.
        if (!empty($data['partner_id'])) {
            $partnerId = (int) $data['partner_id'];
            $enabled = $r->boolean('auto_payout_enabled');

            $ps = PaymentSystem::firstOrCreate(
                ['partner_id' => $partnerId, 'name' => 'tbank'],
                ['settings' => [], 'test_mode' => false]
            );

            $settings = $ps->settings;
            $settings['auto_payout_enabled'] = $enabled;
            $ps->settings = $settings;
            $ps->save();
        }

        return redirect()
            ->route('admin.setting.tbankCommissions')
            ->with('status', 'Правило обновлено');
    }

    public function destroy(int $id)
    {
        TinkoffCommissionRule::whereKey($id)->delete();

        return back()->with('status', 'Правило удалено');
    }
}

