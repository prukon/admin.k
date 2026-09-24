<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\UserCustomPaymentPublicPayLink;
use App\Services\Payments\UserCustomPaymentPublicPayService;
use Illuminate\Http\Request;

final class PublicCustomPaymentPayController extends Controller
{
    public function show(Request $request, string $token, UserCustomPaymentPublicPayService $service)
    {
        $link = UserCustomPaymentPublicPayLink::query()->where('token', $token)->first();
        if (! $link) {
            abort(404);
        }

        return $this->renderShow($request, $link, $service);
    }

    public function showShort(Request $request, string $code, UserCustomPaymentPublicPayService $service)
    {
        $link = UserCustomPaymentPublicPayLink::query()->where('short_code', $code)->first();
        if (! $link) {
            abort(404);
        }

        return $this->renderShow($request, $link, $service);
    }

    private function renderShow(
        Request $request,
        UserCustomPaymentPublicPayLink $link,
        UserCustomPaymentPublicPayService $service,
    ) {
        $result = $service->resolvePublicShow($link, $request);

        return match ($result['kind']) {
            'paid' => view('payment.ulp-public-status', [
                'title' => 'Оплата получена',
                'message' => 'Этот дополнительный платеж уже оплачен. Если у вас остались вопросы, свяжитесь с клубом.',
            ]),
            'expired' => view('payment.ulp-public-status', [
                'title' => 'Ссылка недействительна',
                'message' => 'Срок действия ссылки истёк. Попросите у клуба новую ссылку на оплату.',
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
                'pageTitle' => 'Оплата дополнительного платежа',
                'paymentId' => $result['paymentId'],
                'amountRubFormatted' => $result['amountRubFormatted'],
                'successUrl' => $result['successUrl'],
                'token' => (string) $link->token,
                'isMobileClient' => $result['isMobileClient'],
                'serviceProviderTeamTitle' => $result['serviceProviderTeamTitle'],
                'serviceProviderLabel' => $result['serviceProviderLabel'],
                'showTbankLegalEntityBlock' => $result['showTbankLegalEntityBlock'],
                'qrJsonUrl' => route('ucp.public.pay.qr.json', ['token' => $link->token]),
                'qrPayloadUrl' => route('ucp.public.pay.qr.payload', ['token' => $link->token]),
                'qrStateUrl' => route('ucp.public.pay.qr.state', ['token' => $link->token]),
            ]),
            default => abort(404),
        };
    }

    public function qrJson(Request $request, string $token, UserCustomPaymentPublicPayService $service)
    {
        $link = UserCustomPaymentPublicPayLink::query()->where('token', $token)->first();
        if (! $link) {
            return response()->json(['Success' => false, 'Message' => 'Not found'], 404);
        }

        return $service->tinkoffQrJson($link, 'IMAGE', $request);
    }

    public function qrPayload(Request $request, string $token, UserCustomPaymentPublicPayService $service)
    {
        $link = UserCustomPaymentPublicPayLink::query()->where('token', $token)->first();
        if (! $link) {
            return response()->json(['Success' => false, 'Message' => 'Not found'], 404);
        }

        return $service->tinkoffQrJson($link, 'PAYLOAD', $request);
    }

    public function qrState(Request $request, string $token, UserCustomPaymentPublicPayService $service)
    {
        $link = UserCustomPaymentPublicPayLink::query()->where('token', $token)->first();
        if (! $link) {
            return response()->json(['Success' => false, 'Message' => 'Not found'], 404);
        }

        return $service->tinkoffQrState($link);
    }
}
