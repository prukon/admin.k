<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Partner;

class PartnerSwitchController extends Controller
{
    /**
     * Метод для переключения текущего партнёра.
     */
    public function switch(Request $request)
    {
        $partnerId = $request->input('partner_id');
        $partner = Partner::find($partnerId);

        if (!$partner) {
            return redirect()->back()->withErrors(['partner' => 'Партнёр не найден.']);
        }

        // Сохраняем идентификатор выбранного партнёра в сессии
        session(['current_partner' => $partner->id]);

        return redirect()->back()->with('status', 'Партнёр успешно переключён.');
    }
}
