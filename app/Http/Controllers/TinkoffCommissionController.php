<?php

namespace App\Http\Controllers;

use App\Models\TinkoffCommissionRule;
use App\Models\Partner;
use Illuminate\Http\Request;

class TinkoffCommissionController extends Controller
{
    public function index()
    {
        $rules = TinkoffCommissionRule::orderByRaw('partner_id IS NULL DESC, method IS NULL DESC')->paginate(30);
        $partners = Partner::orderBy('title')->get(['id','title']);
        return view('tinkoff.commissions.index', compact('rules','partners'));
    }

    public function create()
    {
        $partners = \App\Models\Partner::orderBy('title')->get(['id','title']);
        return view('tinkoff.commissions.create', compact('partners'));
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'partner_id' => 'nullable|integer',
            'method'     => 'nullable|in:card,sbp,tpay',
            // 3 комиссии: банк-эквайринг, банк-выплата, платформа
            'acquiring_percent'    => 'required|numeric|min:0',
            'acquiring_min_fixed'  => 'required|numeric|min:0',
            'payout_percent'       => 'required|numeric|min:0',
            'payout_min_fixed'     => 'required|numeric|min:0',
            'platform_percent'     => 'required|numeric|min:0',
            'platform_min_fixed'   => 'required|numeric|min:0',
            'is_enabled' => 'sometimes|boolean',
        ]);
        $data['is_enabled'] = $r->boolean('is_enabled');
        TinkoffCommissionRule::create($data);
        return redirect('/admin/tinkoff/commissions')->with('status','Правило создано');
    }

    public function edit($id)
    {
        $rule = TinkoffCommissionRule::findOrFail($id);
        $partners = \App\Models\Partner::orderBy('title')->get(['id','title']);
        return view('tinkoff.commissions.edit', compact('rule','partners'));
    }

    public function update(Request $r, $id)
    {
        $rule = TinkoffCommissionRule::findOrFail($id);
        $data = $r->validate([
            'partner_id' => 'nullable|integer',
            'method'     => 'nullable|in:card,sbp,tpay',
            'acquiring_percent'    => 'required|numeric|min:0',
            'acquiring_min_fixed'  => 'required|numeric|min:0',
            'payout_percent'       => 'required|numeric|min:0',
            'payout_min_fixed'     => 'required|numeric|min:0',
            'platform_percent'     => 'required|numeric|min:0',
            'platform_min_fixed'   => 'required|numeric|min:0',
            'is_enabled' => 'sometimes|boolean',
        ]);
        $data['is_enabled'] = $r->boolean('is_enabled');
        $rule->update($data);
        return redirect('/admin/tinkoff/commissions')->with('status','Правило обновлено');
    }

    public function destroy($id)
    {
        TinkoffCommissionRule::whereKey($id)->delete();
        return back()->with('status','Правило удалено');
    }
}
