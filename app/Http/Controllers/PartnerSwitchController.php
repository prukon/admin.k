<?php

namespace App\Http\Controllers;

use App\Http\Requests\Partner\SwitchPartnerRequest;
use App\Models\Partner;

class PartnerSwitchController extends Controller
{
    /**
     * Метод для переключения текущего партнёра.
     */
    public function switch(SwitchPartnerRequest $request)
    {
        $partnerId = (int) $request->validated('partner_id');
        $partner = Partner::find($partnerId);

        if (!$partner) {
            return redirect()->back()->withErrors(['partner_id' => 'Партнёр не найден.']);
        }

        // Сохраняем идентификатор выбранного партнёра в сессии
        session(['current_partner' => $partner->id]);

        return redirect()->back()->with('status', 'Партнёр успешно переключён.');
    }
}
