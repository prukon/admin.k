<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\UserPricePublicPayLink;
use App\Services\Payments\UserPricePublicPayService;
use Illuminate\Http\Request;

final class PublicUserPricePayController extends Controller
{
    public function show(Request $request, string $token, UserPricePublicPayService $service)
    {
        $link = UserPricePublicPayLink::query()->where('token', $token)->first();
        if (! $link) {
            abort(404);
        }

        return $this->renderShow($request, $link, $service);
    }

    public function showShort(Request $request, string $code, UserPricePublicPayService $service)
    {
        $link = UserPricePublicPayLink::query()->where('short_code', $code)->first();
        if (! $link) {
            abort(404);
        }

        return $this->renderShow($request, $link, $service);
    }

    private function renderShow(
        Request $request,
        UserPricePublicPayLink $link,
        UserPricePublicPayService $service,
    ) {
        $result = $service->resolvePublicShow($link, $request);

        return match ($result['kind']) {
            'paid' => view('payment.ulp-public-status', [
                'title' => 'Оплата получена',
                'message' => 'Этот период уже оплачен. Если у вас остались вопросы, свяжитесь с клубом.',
            ]),
            'expired' => view('payment.ulp-public-status', [
                'title' => 'Ссылка недействительна',
                'message' => 'Срок действия ссылки истёк. Дождитесь нового письма от клуба или свяжитесь с администрацией.',
            ]),
            'config' => view('payment.ulp-public-status', [
                'title' => 'Оплата недоступна',
                'message' => 'Приём платежей временно недоступен. Свяжитесь с клубом.',
            ]),
            'error' => view('payment.ulp-public-status', [
                'title' => 'Не удалось открыть оплату',
                'message' => (string) ($result['message'] ?? 'Попробуйте позже или свяжитесь с клубом.'),
            ]),
            'qr' => view('payment.ulp-public-pay', [
                'pageTitle' => 'Оплата',
                'paymentId' => $result['paymentId'],
                'amountRubFormatted' => $result['amountRubFormatted'],
                'successUrl' => $result['successUrl'],
                'token' => (string) $link->token,
                'isMobileClient' => $result['isMobileClient'],
                'serviceProviderTeamTitle' => $result['serviceProviderTeamTitle'],
                'serviceProviderLabel' => $result['serviceProviderLabel'],
                'showTbankLegalEntityBlock' => $result['showTbankLegalEntityBlock'],
                'qrJsonUrl' => route('up.public.pay.qr.json', ['token' => $link->token]),
                'qrPayloadUrl' => route('up.public.pay.qr.payload', ['token' => $link->token]),
                'qrStateUrl' => route('up.public.pay.qr.state', ['token' => $link->token]),
            ]),
            default => abort(404),
        };
    }

    public function qrJson(Request $request, string $token, UserPricePublicPayService $service)
    {
        $link = UserPricePublicPayLink::query()->where('token', $token)->first();
        if (! $link) {
            return response()->json(['Success' => false, 'Message' => 'Not found'], 404);
        }

        return $service->tinkoffQrJson($link, 'IMAGE', $request);
    }

    public function qrPayload(Request $request, string $token, UserPricePublicPayService $service)
    {
        $link = UserPricePublicPayLink::query()->where('token', $token)->first();
        if (! $link) {
            return response()->json(['Success' => false, 'Message' => 'Not found'], 404);
        }

        return $service->tinkoffQrJson($link, 'PAYLOAD', $request);
    }

    public function qrState(Request $request, string $token, UserPricePublicPayService $service)
    {
        $link = UserPricePublicPayLink::query()->where('token', $token)->first();
        if (! $link) {
            return response()->json(['Success' => false, 'Message' => 'Not found'], 404);
        }

        return $service->tinkoffQrState($link);
    }
}
