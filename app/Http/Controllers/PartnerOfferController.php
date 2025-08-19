<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PartnerOfferController extends Controller
{
    /**
     * Обработка принятия партнёрской оферты.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
//    public function acceptOffer(Request $request)
//    {
//        $request->validate([
//            'confirm' => 'accepted',
//        ]);
//
//        $partner = Auth::guard('partner')->user();
//
//        $partner->offer_accepted = true;
//        $partner->offer_accepted_at = now();
//        $partner->save();
//
//        return redirect()
//            ->route('partner.dashboard')
//            ->with('success', 'Оферта успешно принята');
//    }

    public function acceptOffer(Request $request)
    {
        $request->validate([
            'confirm' => 'accepted'
        ]);

        $user = Auth::user();

        if (!$user->role || $user->role->name !== 'admin') {
            abort(403, 'Вы не имеете права подписывать эту оферту');
        }

        $user->offer_accepted = true;
        $user->offer_accepted_at = now();
        $user->save();

        return redirect()->route('dashboard')->with('success', 'Оферта принята');
    }
}
