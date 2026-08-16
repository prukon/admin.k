<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\UserLessonPackage;
use App\Models\UserLessonPackagePublicPayLink;
use App\Services\Payments\UserLessonPackagePublicPayService;
use App\Services\SmsRuService;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\LessonPackages\Concerns\LessonPackageAssignmentPaySmsTestHelpers;

/**
 * Текст SMS с короткой ссылкой обязан укладываться в 1 Unicode-сегмент (≤70 символов)
 * при любой генерации /p/{short_code} и сумме до 99 999 ₽.
 */
final class LessonPackageAssignmentPaySmsLengthFeatureTest extends CrmTestCase
{
    use LessonPackageAssignmentPaySmsTestHelpers;

    private const SHORT_CODE_PATTERN = '/^[abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789]{10}$/';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->grantSmsAssignmentsAccess();
        config(['billing.sms_send_fee' => 70.00]);
    }

    public function test_app_url_is_the_project_domain_used_for_one_sms_budget(): void
    {
        $this->assertSame(
            'https://test.kidscrm.online',
            rtrim((string) config('app.url'), '/'),
            'Длина SMS посчитана для этого APP_URL; смена домена требует пересчёта 70 символов.'
        );
    }

    public function test_preview_sms_fits_one_unicode_segment_for_fees_up_to_99999(): void
    {
        $this->seedTbankForSmsPartner();

        foreach ([10.0, 99.0, 500.0, 8000.0, 9999.0, 10000.0, 99999.0] as $fee) {
            $ctx = $this->seedSmsAssignment(['fee' => $fee]);
            $message = $this->previewSmsMessage($ctx['assignment']);
            $this->assertPaySmsIsOneSegment($message, 'fee='.$fee);
            $this->assertStringContainsString(' '.((string) (int) $fee).' руб:', $message);
            $this->assertShortPayLinkOnAssignment($ctx['assignment']);
        }
    }

    public function test_worst_case_99999_is_exactly_70_characters_on_this_domain(): void
    {
        $this->seedTbankForSmsPartner();
        $ctx = $this->seedSmsAssignment(['fee' => 99999.0]);

        $message = $this->previewSmsMessage($ctx['assignment']);
        $this->assertPaySmsIsOneSegment($message, '99999');
        $this->assertSame(70, mb_strlen($message, 'UTF-8'));
    }

    public function test_many_random_short_codes_keep_sms_in_one_segment(): void
    {
        $this->seedTbankForSmsPartner();
        $codes = [];

        for ($i = 0; $i < 12; $i++) {
            $ctx = $this->seedSmsAssignment([
                'fee' => 99999.0,
                'package_name' => 'Очень длинное название абонемента которое не должно попасть в SMS '.$i,
            ]);
            $message = $this->previewSmsMessage($ctx['assignment']);
            $this->assertPaySmsIsOneSegment($message, 'generation #'.$i);
            $this->assertStringNotContainsString('длинное название', $message);

            $code = $this->currentShortCode($ctx['assignment']);
            $this->assertMatchesRegularExpression(self::SHORT_CODE_PATTERN, $code);
            $codes[] = $code;
        }

        $this->assertSame(count($codes), count(array_unique($codes)));
    }

    public function test_copy_link_and_sms_share_the_same_short_url_in_one_segment(): void
    {
        $this->seedTbankForSmsPartner();
        $ctx = $this->seedSmsAssignment(['fee' => 8000.0]);

        $copied = (string) $this->postJson(
            route('admin.lesson-packages.assignments.public-pay-link', ['assignment' => $ctx['assignment']->id]),
            [],
            $this->smsAjaxHeaders()
        )->assertOk()->json('url');

        $this->assertStringContainsString('/p/', $copied);
        $this->assertStringNotContainsString('/pay/ulp/', $copied);

        $preview = $this->getJson($this->smsPreviewUrl($ctx['assignment']->id), $this->smsAjaxHeaders())
            ->assertOk();
        $message = (string) $preview->json('message');
        $payUrl = (string) $preview->json('pay_url');

        $this->assertSame($copied, $payUrl);
        $this->assertStringContainsString($copied, $message);
        $this->assertPaySmsIsOneSegment($message, 'after copy-link');
    }

    public function test_sms_stays_one_segment_after_expired_link_rotation(): void
    {
        $this->seedTbankForSmsPartner();
        $ctx = $this->seedSmsAssignment(['fee' => 99999.0]);

        $first = $this->previewSmsMessage($ctx['assignment']);
        $oldCode = $this->currentShortCode($ctx['assignment']);
        $oldToken = $this->currentToken($ctx['assignment']);
        $this->assertPaySmsIsOneSegment($first, 'before expiry');

        UserLessonPackagePublicPayLink::query()
            ->where('user_lesson_package_id', $ctx['assignment']->id)
            ->update(['expires_at' => now()->subDay()]);

        $second = $this->previewSmsMessage($ctx['assignment']);
        $newCode = $this->currentShortCode($ctx['assignment']);
        $newToken = $this->currentToken($ctx['assignment']);

        $this->assertNotSame($oldCode, $newCode);
        $this->assertNotSame($oldToken, $newToken);
        $this->assertPaySmsIsOneSegment($second, 'after expiry rotation');
        $this->assertStringNotContainsString($oldCode, $second);
    }

    public function test_sms_stays_one_segment_after_fee_change_resets_short_code(): void
    {
        $this->seedTbankForSmsPartner();
        $ctx = $this->seedSmsAssignment(['fee' => 500.0]);

        $first = $this->previewSmsMessage($ctx['assignment']);
        $oldCode = $this->currentShortCode($ctx['assignment']);
        $this->assertPaySmsIsOneSegment($first, 'before fee reset');

        $ctx['assignment']->forceFill(['fee_amount_cents' => 9999900])->save();
        $newShareUrl = app(UserLessonPackagePublicPayService::class)
            ->resetPublicPayAfterFeeChange($ctx['assignment']->fresh());
        $this->assertNotNull($newShareUrl);
        $this->assertStringContainsString('/p/', (string) $newShareUrl);

        $second = $this->previewSmsMessage($ctx['assignment']->fresh());
        $newCode = $this->currentShortCode($ctx['assignment']);

        $this->assertNotSame($oldCode, $newCode);
        $this->assertStringContainsString((string) $newShareUrl, $second);
        $this->assertPaySmsIsOneSegment($second, 'after fee reset');
        $this->assertSame(70, mb_strlen($second, 'UTF-8'));
    }

    public function test_backfill_short_code_without_rotating_token_keeps_sms_one_segment(): void
    {
        $this->seedTbankForSmsPartner();
        $ctx = $this->seedSmsAssignment(['fee' => 8000.0]);
        $token = bin2hex(random_bytes(32));

        UserLessonPackagePublicPayLink::query()->create([
            'user_lesson_package_id' => $ctx['assignment']->id,
            'partner_id' => $this->partner->id,
            'token' => $token,
            'short_code' => null,
            'expires_at' => now()->addDays(10),
        ]);

        $message = $this->previewSmsMessage($ctx['assignment']);
        $this->assertPaySmsIsOneSegment($message, 'backfill short_code');
        $this->assertSame($token, $this->currentToken($ctx['assignment']));
        $this->assertMatchesRegularExpression(self::SHORT_CODE_PATTERN, $this->currentShortCode($ctx['assignment']));
        $this->assertStringNotContainsString($token, $message);
    }

    public function test_sent_sms_payload_is_one_unicode_segment(): void
    {
        $this->seedTbankForSmsPartner();
        $ctx = $this->seedSmsAssignment(['fee' => 99999.0, 'student_phone' => '+79001112233']);

        $this->mock(SmsRuService::class, function ($mock): void {
            $mock->shouldReceive('send')
                ->once()
                ->withArgs(function (string $phone, string $message): bool {
                    $this->assertSame('79001112233', $phone);
                    $this->assertPaySmsIsOneSegment($message, 'sms.ru payload');
                    $this->assertSame(70, mb_strlen($message, 'UTF-8'));

                    return true;
                })
                ->andReturn(true);
        });

        $this->postJson($this->smsSendUrl($ctx['assignment']->id), [], $this->smsAjaxHeaders())
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    private function previewSmsMessage(UserLessonPackage $assignment): string
    {
        $message = (string) $this->getJson($this->smsPreviewUrl((int) $assignment->id), $this->smsAjaxHeaders())
            ->assertOk()
            ->json('message');

        $this->assertNotSame('', $message);

        return $message;
    }

    private function currentShortCode(UserLessonPackage $assignment): string
    {
        $code = (string) UserLessonPackagePublicPayLink::query()
            ->where('user_lesson_package_id', $assignment->id)
            ->value('short_code');
        $this->assertMatchesRegularExpression(self::SHORT_CODE_PATTERN, $code);

        return $code;
    }

    private function currentToken(UserLessonPackage $assignment): string
    {
        $token = (string) UserLessonPackagePublicPayLink::query()
            ->where('user_lesson_package_id', $assignment->id)
            ->value('token');
        $this->assertSame(64, strlen($token));

        return $token;
    }

    private function assertShortPayLinkOnAssignment(UserLessonPackage $assignment): void
    {
        $this->currentShortCode($assignment);
        $this->assertSame(64, strlen($this->currentToken($assignment)));
    }
}
