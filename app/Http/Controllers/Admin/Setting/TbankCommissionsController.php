<?php

namespace App\Http\Controllers\Admin\Setting;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\TinkoffCommissionRule;
use Illuminate\Http\Request;

class TbankCommissionsController extends Controller
{
    public function index()
    {
        $rules = TinkoffCommissionRule::orderByRaw('partner_id IS NULL DESC, method IS NULL DESC')->paginate(30);
        $partners = Partner::orderBy('title')->get(['id', 'title']);

        return view('admin.setting.index', [
            'activeTab' => 'tbankCommissions',
            'mode' => 'list',
            'rules' => $rules,
            'partners' => $partners,
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

        return view('admin.setting.index', [
            'activeTab' => 'tbankCommissions',
            'mode' => 'edit',
            'rule' => $rule,
            'partners' => $partners,
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
        ]);

        $data['is_enabled'] = $r->boolean('is_enabled');
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
}

