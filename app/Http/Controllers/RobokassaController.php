<?php

namespace App\Http\Controllers;

use App\Models\PaymentIntent;
use App\Enums\AuditEvent;
use App\Services\Audit\AuditContext;
use App\Services\Audit\AuditLogger;
use App\Models\Payable;
use App\Models\PaymentSystem;
use App\Models\Team;
use App\Models\User;
use App\Models\UserPrice;
use App\Models\UserCustomPayment;
use App\Models\UserLessonPackage;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Support\Payments\PaymentOutSumNormalizer;
use App\Services\Payments\PaymentLedgerRecorder;
use App\Services\Payments\PaymentLedgerTeamResolver;

class RobokassaController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

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
        $payable = $intent->payable;
        if (!$payable) {
            // Пытаемся восстановить связь для "исторических" intent-ов, созданных до появления payable_id.
            $typeGuess = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $intent->payment_date) ? 'monthly_fee' : 'club_fee';
            $payable = null;

            $q = Payable::query()
                ->where('partner_id', (int) $intent->partner_id)
                ->where('user_id', (int) $intent->user_id)
                ->where('amount', (string) $intent->out_sum)
                ->where('status', 'pending')
                ->where('type', $typeGuess);

            if ($typeGuess === 'monthly_fee' && !empty($intent->payment_date)) {
                $q->whereDate('month', '=', (string) $intent->payment_date);
            }

            // ограничиваем поиск по времени создания (±30 минут), чтобы не прицепиться к чужой покупке
            if (!empty($intent->created_at)) {
                $q->whereBetween('created_at', [$intent->created_at->copy()->subMinutes(30), $intent->created_at->copy()->addMinutes(30)]);
            }

            $candidates = $q->limit(5)->get();

            $intentTeamId = $this->resolveTeamIdFromIntentMeta($intent);

            if ($intentTeamId > 0 && $typeGuess === 'monthly_fee') {
                $teamFiltered = $candidates->filter(function (Payable $candidate) use ($intentTeamId) {
                    $metaTeamId = $candidate->meta['team_id'] ?? null;

                    return is_numeric($metaTeamId) && (int) $metaTeamId === $intentTeamId;
                })->values();
                if ($teamFiltered->count() === 1) {
                    $payable = $teamFiltered->first();
                }
            }

            if (! isset($payable) || ! $payable) {
                if ($candidates->count() === 1) {
                    $payable = $candidates->first();
                }
            }

            if (isset($payable) && $payable) {
                $intent->payable_id = $payable->id;
                $intent->save();
                Log::warning('Robokassa result: payable_id restored for intent', [
                    'InvId'      => $invId,
                    'intent_id'  => $intent->id,
                    'payable_id' => $payable->id,
                ]);
            } else {
                Log::error('Robokassa result: payable not found for intent', [
                    'InvId'      => $invId,
                    'intent_id'  => $intent->id,
                    'candidates' => $candidates->count(),
                ]);
                return response("bad invoice\n", 404);
            }
        }

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

        $outSumNorm = PaymentOutSumNormalizer::normalize($outSumRaw);
        if ($outSumNorm === null) {
            Log::warning('Robokassa result: invalid OutSum', ['OutSum' => $outSumRaw, 'InvId' => $invId]);
            return response("bad request\n", 400);
        }

        $expectedSumNorm = PaymentOutSumNormalizer::normalize((string) $intent->out_sum);
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

        DB::transaction(function () use ($invId, $intentIdForLock, $partnerId, $shpPaymentDate, $shpUserId, $outSumNorm, $payable) {
            $lockedIntent = PaymentIntent::whereKey($intentIdForLock)->lockForUpdate()->first();
            if (!$lockedIntent) {
                throw new \RuntimeException("Intent disappeared: $invId");
            }
            if ((string) $lockedIntent->status === 'paid') {
                return;
            }

            // Бизнес-эффект: только monthly_fee влияет на users_prices.
            if ((string) $payable->type === 'monthly_fee') {
                $month = $payable->month ? $payable->month->format('Y-m-d') : ($payable->meta['month'] ?? null);
                if (is_string($month) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $month) && strtotime($month)) {
                    $teamId = isset($payable->meta['team_id']) && is_numeric($payable->meta['team_id'])
                        ? (int) $payable->meta['team_id']
                        : null;
                    UserPrice::markMonthlyPaid((int) $shpUserId, $month, $teamId, true);
                } else {
                    Log::warning('Robokassa result: monthly_fee without valid month in payable', [
                        'InvId' => $invId,
                        'payable_id' => $payable->id,
                        'month' => $month,
                    ]);
                }
            } elseif ((string) $payable->type === 'custom_payment_fee') {
                $pid = $payable->meta['user_period_price_id'] ?? null;
                $pidInt = is_numeric($pid) ? (int) $pid : 0;
                if ($pidInt > 0) {
                    UserCustomPayment::query()
                        ->whereKey($pidInt)
                        ->update(['is_paid' => 1]);
                } else {
                    Log::warning('Robokassa result: custom_payment_fee without user_period_price_id in payable.meta', [
                        'InvId' => $invId,
                        'payable_id' => $payable->id,
                        'meta' => $payable->meta,
                    ]);
                }
            } elseif ((string) $payable->type === 'lesson_package_fee') {
                $ulpId = $payable->meta['user_lesson_package_id'] ?? null;
                $ulpInt = is_numeric($ulpId) ? (int) $ulpId : 0;
                if ($ulpInt > 0) {
                    UserLessonPackage::query()
                        ->whereKey($ulpInt)
                        ->update(['is_paid' => true]);
                } else {
                    Log::warning('Robokassa result: lesson_package_fee without user_lesson_package_id in payable.meta', [
                        'InvId' => $invId,
                        'payable_id' => $payable->id,
                        'meta' => $payable->meta,
                    ]);
                }
            }

            $user = User::with([
                'teams' => fn ($q) => $q
                    ->where('teams.partner_id', $partnerId)
                    ->whereNull('teams.deleted_at'),
            ])->find($shpUserId);

            if (! $user) {
                return;
            }

            $teamSnapshot = app(PaymentLedgerTeamResolver::class)->resolveFromPayable($payable, $user);
            $currentDateTime = now()->format('Y-m-d H:i:s');

            app(PaymentLedgerRecorder::class)->record(
                (string) $invId,
                $partnerId,
                $shpUserId,
                [
                    'user_id' => $shpUserId,
                    'user_name' => ($user->full_name ?: trim(($user->lastname ?? '').' '.($user->name ?? ''))) ?: 'Неизвестно',
                    'team_id' => $teamSnapshot['team_id'],
                    'team_title' => $teamSnapshot['team_title'],
                    'operation_date' => $currentDateTime,
                    'payment_month' => $shpPaymentDate,
                    'summ' => $outSumNorm,
                ]
            );

            $teamName = $teamSnapshot['team_title'];

            $this->auditLogger->record(
                AuditEvent::PaymentReceived,
                AuditContext::make("Платеж на сумму: " . (int) ((float) $outSumNorm) . " руб от "
                    . ( ($user?->full_name ?? trim( ($user?->lastname ?? '') . ' ' . ($user?->name ?? '') )) ?: 'Неизвестно' )
                    . ". ID: $shpUserId. Группа: $teamName. Период: $shpPaymentDate. InvId: $invId.")
                    ->withAuthorId($shpUserId)
                    ->withPartnerId($partnerId)
                    ->withCreatedAt(now())
            );

            $lockedIntent->status = 'paid';
            $lockedIntent->paid_at = now();
            $lockedIntent->save();

            // Payable: фиксируем оплату
            $payable->status = 'paid';
            $payable->paid_at = now();
            $payable->save();
        });

        return response("OK$invId\n", 200);
    }

    private function resolveTeamIdFromIntentMeta(PaymentIntent $intent): int
    {
        if (empty($intent->meta)) {
            return 0;
        }

        $meta = json_decode((string) $intent->meta, true);
        if (! is_array($meta)) {
            return 0;
        }

        $raw = $meta['team_id'] ?? null;

        return is_numeric($raw) && (int) $raw > 0 ? (int) $raw : 0;
    }

}
 












