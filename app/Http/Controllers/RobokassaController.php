<?php

namespace App\Http\Controllers;

use App\Models\MyLog;
use App\Models\Payment;
use App\Models\PaymentIntent;
use App\Models\PaymentSystem;
use App\Models\Team;
use App\Models\User;
use App\Models\UserPrice;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class RobokassaController extends Controller
{
    public function result(Request $request)
    {
        Log::info('Robokassa result request', $request->query());

        // Получаем параметры из запроса (ВАЖНО: не меняем регистр Shp_* — иначе сломаем подпись для строковых значений).
        $shpPaymentDate = (string) $request->query('Shp_paymentDate', '');
        $shpUserIdRaw = (string) $request->query('Shp_userId', '');
        $signature = (string) $request->query('SignatureValue', '');
        $outSumRaw = (string) $request->query('OutSum', '');
        $invIdRaw = (string) $request->query('InvId', '');

        if ($shpPaymentDate === '' || $shpUserIdRaw === '' || $signature === '' || $outSumRaw === '' || $invIdRaw === '') {
            Log::warning('Robokassa result: missing params', [
                'Shp_paymentDate' => $shpPaymentDate,
                'Shp_userId'      => $shpUserIdRaw,
                'SignatureValue'  => $signature,
                'OutSum'          => $outSumRaw,
                'InvId'           => $invIdRaw,
            ]);
            return response("bad request\n", 400);
        }

        if (!ctype_digit($invIdRaw)) {
            Log::warning('Robokassa result: invalid InvId', ['InvId' => $invIdRaw]);
            return response("bad invoice\n", 400);
        }

        $invId = (int) $invIdRaw;
        // Сначала ищем по внешнему InvId провайдера (Robokassa), затем fallback по первичному ключу (на всякий случай).
        $intent = PaymentIntent::where('provider', 'robokassa')
            ->where('provider_inv_id', $invId)
            ->first();
        if (!$intent) {
            $intent = PaymentIntent::find($invId);
        }
        if (!$intent) {
            Log::warning('Robokassa result: intent not found', ['InvId' => $invId]);
            return response("bad invoice\n", 404);
        }

        $partnerId = (int) $intent->partner_id;

        // Достаём настройки Robokassa именно для партнёра intent-а
        $paymentSystem = PaymentSystem::where('partner_id', $partnerId)
            ->where('name', 'robokassa')
            ->first();

        if (!$paymentSystem || !$paymentSystem->is_connected) {
            Log::error('Robokassa result: payment system not connected for partner', [
                'partner_id' => $partnerId,
                'InvId'      => $invId,
            ]);
            return response("bad config\n", 500);
        }

        $settings = $paymentSystem->settings;
        $password2 = $settings['password2'] ?? null;
        if (empty($password2)) {
            Log::error('Robokassa result: missing password2', [
                'partner_id' => $partnerId,
                'payment_system_id' => $paymentSystem->id,
            ]);
            return response("bad config\n", 500);
        }

        $outSumNorm = $this->normalizeOutSum($outSumRaw);
        if ($outSumNorm === null) {
            Log::warning('Robokassa result: invalid OutSum', ['OutSum' => $outSumRaw, 'InvId' => $invId]);
            return response("bad request\n", 400);
        }

        $expectedSumNorm = $this->normalizeOutSum((string) $intent->out_sum);
        if ($expectedSumNorm === null || $expectedSumNorm !== $outSumNorm) {
            Log::warning('Robokassa result: sum mismatch', [
                'InvId'         => $invId,
                'expected_sum'  => $expectedSumNorm,
                'received_sum'  => $outSumNorm,
                'partner_id'    => $partnerId,
            ]);
            return response("bad sum\n", 400);
        }

        $shpUserId = ctype_digit($shpUserIdRaw) ? (int) $shpUserIdRaw : null;
        if ($shpUserId === null || (int) $intent->user_id !== $shpUserId) {
            Log::warning('Robokassa result: user mismatch', [
                'InvId'         => $invId,
                'intent_user'   => (int) $intent->user_id,
                'shp_user'      => $shpUserIdRaw,
            ]);
            return response("bad user\n", 400);
        }

        if (!empty($intent->payment_date) && (string) $intent->payment_date !== $shpPaymentDate) {
            Log::warning('Robokassa result: payment_date mismatch', [
                'InvId'          => $invId,
                'intent_date'    => (string) $intent->payment_date,
                'shp_date'       => $shpPaymentDate,
            ]);
            return response("bad period\n", 400);
        }

        // Генерация подписи (Robokassa ResultURL): OutSum:InvId:Password2 + пользовательские параметры
        $mySignature = strtoupper(md5("$outSumRaw:$invId:$password2:Shp_paymentDate=$shpPaymentDate:Shp_userId=$shpUserIdRaw"));
        if (strtoupper($signature) !== $mySignature) {
            Log::warning('Robokassa result: signature mismatch', [
                'InvId'      => $invId,
                'received'   => strtoupper($signature),
                'expected'   => $mySignature,
            ]);
            return response("bad sign\n", 400);
        }

        // Идемпотентность: если уже paid — отвечаем OK.
        if ((string) $intent->status === 'paid') {
            return response("OK$invId\n", 200);
        }

        $intentIdForLock = (int) $intent->id;

        DB::transaction(function () use ($invId, $intentIdForLock, $partnerId, $shpPaymentDate, $shpUserId, $outSumNorm) {
            $lockedIntent = PaymentIntent::whereKey($intentIdForLock)->lockForUpdate()->first();
            if (!$lockedIntent) {
                throw new \RuntimeException("Intent disappeared: $invId");
            }
            if ((string) $lockedIntent->status === 'paid') {
                return;
            }

            $isValidDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $shpPaymentDate) && strtotime($shpPaymentDate);
            $newMonthValue = $isValidDate ? $shpPaymentDate : null;

            UserPrice::updateOrCreate(
                [
                    'user_id'   => $shpUserId,
                    'new_month' => $newMonthValue,
                ],
                [
                    'is_paid' => 1,
                ]
            );

            $user = User::find($shpUserId);
            $teamName = $user?->team?->title ?? 'Без команды';
            $currentDateTime = now()->format('Y-m-d H:i:s');

            Payment::updateOrCreate(
                [
                    'payment_number' => (string) $invId,
                    'partner_id'     => $partnerId,
                ],
                [
                    'user_id'         => $shpUserId,
                    'user_name'       => ($user?->full_name ?: trim(($user->lastname ?? '').' '.($user->name ?? ''))) ?: 'Неизвестно',
                    'team_title'      => $teamName,
                    'operation_date'  => $currentDateTime,
                    'payment_month'   => $shpPaymentDate,
                    'summ'            => $outSumNorm,
                ]
            );

            MyLog::create([
                'type'        => 5,
                'action'      => 50,
                'author_id'   => $shpUserId,
                'partner_id'  => $partnerId,
                'description' => "Платеж на сумму: " . (int) ((float) $outSumNorm) . " руб от "
                    . ( ($user?->full_name ?? trim( ($user?->lastname ?? '') . ' ' . ($user?->name ?? '') )) ?: 'Неизвестно' )
                    . ". ID: $shpUserId. Группа: $teamName. Период: $shpPaymentDate. InvId: $invId.",
                'created_at'  => now(),
            ]);

            $lockedIntent->status = 'paid';
            $lockedIntent->paid_at = now();
            $lockedIntent->save();
        });

        return response("OK$invId\n", 200);
    }

    /**
     * Нормализуем сумму (Robokassa).
     * Разрешаем только формат: 123 или 123.4 или 123.45, возвращаем строку с 2 знаками после точки.
     */
    private function normalizeOutSum(string $value): ?string
    {
        $v = trim(str_replace(',', '.', $value));
        if ($v === '') {
            return null;
        }
        if (!preg_match('/^\d+(\.\d{1,2})?$/', $v)) {
            return null;
        }
        if (str_contains($v, '.')) {
            [$a, $b] = explode('.', $v, 2);
            $b = str_pad($b, 2, '0');
            return $a . '.' . substr($b, 0, 2);
        }
        return $v . '.00';
    }

}
 












