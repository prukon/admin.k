<?php

namespace App\Http\Controllers;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class PartnerOfferController extends Controller
{
    public function downloadPdf(): Response
    {
        $html = view('agreements.partner-offerta-pdf')->render();

        $options = new Options();
        $options->set('defaultFont', (string) config('contracts.dompdf_font', 'DejaVu Sans'));
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="partnerskaya-oferta-kidscrm.pdf"',
        ]);
    }

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
