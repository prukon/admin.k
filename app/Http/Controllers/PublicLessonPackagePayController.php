<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\UserLessonPackagePublicPayLink;
use App\Services\Payments\UserLessonPackagePublicPayService;
use Illuminate\Http\Request;

final class PublicLessonPackagePayController extends Controller
{
    public function show(Request $request, string $token, UserLessonPackagePublicPayService $service)
    {
        $link = UserLessonPackagePublicPayLink::query()->where('token', $token)->first();
        if (! $link) {
            abort(404);
        }

        $result = $service->resolvePublicShow($link, $request);

        return match ($result['kind']) {
            'paid' => view('payment.ulp-public-status', [
                'title' => 'Оплата получена',
                'message' => 'Этот абонемент уже оплачен. Если у вас остались вопросы, свяжитесь с клубом.',
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
                'paymentId' => $result['paymentId'],
                'amountRubFormatted' => $result['amountRubFormatted'],
                'successUrl' => $result['successUrl'],
                'token' => $token,
                'isMobileClient' => $result['isMobileClient'],
                'serviceProviderTeamTitle' => $result['serviceProviderTeamTitle'],
                'serviceProviderLabel' => $result['serviceProviderLabel'],
                'showTbankLegalEntityBlock' => $result['showTbankLegalEntityBlock'],
            ]),
            default => abort(404),
        };
    }

    public function qrJson(Request $request, string $token, UserLessonPackagePublicPayService $service)
    {
        $link = UserLessonPackagePublicPayLink::query()->where('token', $token)->first();
        if (! $link) {
            return response()->json(['Success' => false, 'Message' => 'Not found'], 404);
        }

        return $service->tinkoffQrJson($link, 'IMAGE');
    }

    public function qrPayload(Request $request, string $token, UserLessonPackagePublicPayService $service)
    {
        $link = UserLessonPackagePublicPayLink::query()->where('token', $token)->first();
        if (! $link) {
            return response()->json(['Success' => false, 'Message' => 'Not found'], 404);
        }

        return $service->tinkoffQrJson($link, 'PAYLOAD');
    }

    public function qrState(Request $request, string $token, UserLessonPackagePublicPayService $service)
    {
        $link = UserLessonPackagePublicPayLink::query()->where('token', $token)->first();
        if (! $link) {
            return response()->json(['Success' => false, 'Message' => 'Not found'], 404);
        }

        return $service->tinkoffQrState($link);
    }
}
