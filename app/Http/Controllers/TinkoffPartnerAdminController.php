<?php

namespace App\Http\Controllers;

use App\Models\Partner;
use App\Services\Tinkoff\SmRegisterClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TinkoffPartnerAdminController extends Controller
{
    public function show($id)
    {
        $partner = Partner::findOrFail($id);
        return view('tinkoff.partners.show', compact('partner'));
    }

    // POST: регистрация партнёра (создание PartnerId / shopCode)
    public function smRegister($id, Request $r, SmRegisterClient $sm)
    {
        $partner = Partner::findOrFail($id);

        // валидация формы
        $data = $r->validate([
            'business_type' => 'required|in:company,individual_entrepreneur,physical_person,non_commercial_organization',
            'title'         => 'required|string|max:255',
            'tax_id'        => 'required|string|max:64', // ИНН
            'registration_number' => 'nullable|string|max:64', // ОГРНИП/ОГРН
            'email'         => 'required|email',
            'address'       => 'required|string|max:255',
            'bank_name'     => 'required|string|max:255',
            'bank_bik'      => 'required|string|max:20',
            'bank_account'  => 'required|string|max:32', // р/с
            'sm_details_template' => 'required|string|max:500',
        ]);

        // payload для /sm-register/register
        $payload = [
            'type'        => $data['business_type'], // как просит API
            'title'       => $data['title'],
            'inn'         => $data['tax_id'],
            'ogrn'        => $data['registration_number'] ?? null,
            'email'       => $data['email'],
            'address'     => $data['address'],
            'bankAccount' => [
                'bankName' => $data['bank_name'],
                'bik'      => $data['bank_bik'],
                'account'  => $data['bank_account'],
                'details'  => $data['sm_details_template'], // назначение платежа
            ],
        ];

        try {
            $res = $sm->register($payload);
            $shopCode = $res['shopCode'] ?? $res['PartnerId'] ?? null;
            if (!$shopCode) {
                return back()->withErrors(['sm' => 'shopCode не получен из ответа sm-register']);
            }

            // сохраняем в партнёра
            $partner->tinkoff_partner_id = $shopCode;
            $partner->sm_register_status = 'registered';
            $partner->sm_details_template = $data['sm_details_template'];
            $partner->bank_name = $data['bank_name'];
            $partner->bank_bik = $data['bank_bik'];
            $partner->bank_account = $data['bank_account'];
            $partner->bank_details_version = (int)($partner->bank_details_version ?? 0) + 1;
            $partner->bank_details_last_updated_at = now();
            $partner->save();

            return back()->with('ok', "Партнёр зарегистрирован. PartnerId: {$shopCode}");
        } catch (\Throwable $e) {
            Log::channel('tinkoff')->error('[sm-register][register] failed: '.$e->getMessage(), ['trace'=>$e->getTraceAsString()]);
            return back()->withErrors(['sm' => 'Ошибка регистрации: '.$e->getMessage()]);
        }
    }

    // POST: изменение реквизитов через PATCH
    public function smPatch($id, Request $r, SmRegisterClient $sm)
    {
        $partner = Partner::findOrFail($id);
        if (!$partner->tinkoff_partner_id) {
            return back()->withErrors(['sm' => 'Сначала зарегистрируйте партнёра (нет PartnerId)']);
        }

        $data = $r->validate([
            'bank_name'     => 'required|string|max:255',
            'bank_bik'      => 'required|string|max:20',
            'bank_account'  => 'required|string|max:32',
            'sm_details_template' => 'required|string|max:500',
            'email'         => 'required|email',
            'address'       => 'required|string|max:255',
        ]);

        $payload = [
            'bankAccount' => [
                'bankName' => $data['bank_name'],
                'bik'      => $data['bank_bik'],
                'account'  => $data['bank_account'],
                'details'  => $data['sm_details_template'],
            ],
            'email'   => $data['email'],
            'address' => $data['address'],
        ];

        try {
            $res = $sm->patch($partner->tinkoff_partner_id, $payload);

            $partner->bank_name = $data['bank_name'];
            $partner->bank_bik = $data['bank_bik'];
            $partner->bank_account = $data['bank_account'];
            $partner->sm_details_template = $data['sm_details_template'];
            $partner->bank_details_version = (int)($partner->bank_details_version ?? 0) + 1;
            $partner->bank_details_last_updated_at = now();
            $partner->save();

            return back()->with('ok', 'Реквизиты обновлены в sm-register');
        } catch (\Throwable $e) {
            Log::channel('tinkoff')->error('[sm-register][patch] failed: '.$e->getMessage());
            return back()->withErrors(['sm' => 'Ошибка PATCH: '.$e->getMessage()]);
        }
    }

    // формальная кнопка «обновить статус» на будущее
    public function smRefresh($id)
    {
        // при необходимости дернуть какой-то GET статус у acqapi
        return back()->with('ok', 'Статус обновлён (заглушка)');
    }
}
