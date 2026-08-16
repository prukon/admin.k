<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\Partner;
use App\Services\SmsRuService;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\LessonPackages\Concerns\LessonPackageAssignmentPaySmsTestHelpers;

/**
 * AJAX JSON-контракт превью и отправки SMS: структура, 200/422, errors по полям.
 *
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class LessonPackageAssignmentPaySmsAjaxContractFeatureTest extends CrmTestCase
{
    use LessonPackageAssignmentPaySmsTestHelpers;

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

    public function test_preview_returns_json_payload_when_pay_link_is_available(): void
    {
        $this->seedTbankForSmsPartner();
        $ctx = $this->seedSmsAssignment(['student_phone' => '+79001112233']);

        $response = $this->getJson($this->smsPreviewUrl($ctx['assignment']->id), $this->smsAjaxHeaders())
            ->assertOk()
            ->assertJsonStructure([
                'phone',
                'phone_display',
                'phone_locked',
                'phone_source',
                'message',
                'fee',
                'fee_label',
                'pay_url',
            ])
            ->assertJsonPath('phone', '79001112233')
            ->assertJsonPath('phone_locked', true)
            ->assertJsonPath('phone_source', 'student')
            ->assertJsonPath('fee', 70);

        $this->assertStringContainsString(
            'Оплатите абонемент 500 руб:',
            (string) $response->json('message')
        );
        $this->assertStringContainsString('/p/', (string) $response->json('message'));
        $this->assertStringNotContainsString('/pay/ulp/', (string) $response->json('message'));
    }

    public function test_preview_returns_422_sms_error_when_pay_link_unavailable(): void
    {
        $ctx = $this->seedSmsAssignment();

        $this->getJson($this->smsPreviewUrl($ctx['assignment']->id), $this->smsAjaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['sms']);
    }

    public function test_preview_returns_422_sms_error_when_assignment_already_paid(): void
    {
        $this->seedTbankForSmsPartner();
        $ctx = $this->seedSmsAssignment(['is_paid' => true]);

        $this->getJson($this->smsPreviewUrl($ctx['assignment']->id), $this->smsAjaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['sms']);
    }

    public function test_preview_returns_422_sms_error_when_fee_is_below_ten_rubles(): void
    {
        $this->seedTbankForSmsPartner();
        $ctx = $this->seedSmsAssignment(['fee' => 9.99]);

        $this->getJson($this->smsPreviewUrl($ctx['assignment']->id), $this->smsAjaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['sms']);
    }

    public function test_send_returns_json_success_payload(): void
    {
        $this->seedTbankForSmsPartner();
        Partner::query()->whereKey($this->partner->id)->update(['wallet_balance_cents' => 20000]);
        $ctx = $this->seedSmsAssignment(['student_phone' => '+79001112233']);

        $this->mock(SmsRuService::class, function ($mock): void {
            $mock->shouldReceive('send')->once()->andReturn(true);
        });

        $response = $this->postJson($this->smsSendUrl($ctx['assignment']->id), [], $this->smsAjaxHeaders())
            ->assertOk()
            ->assertJsonStructure(['success', 'message', 'phone_saved'])
            ->assertJsonPath('success', true)
            ->assertJsonPath('phone_saved', false);

        $this->assertStringContainsString('SMS отправлено', (string) $response->json('message'));
        $this->assertStringContainsString('70', (string) $response->json('message'));
    }

    public function test_send_returns_422_phone_error_when_number_missing(): void
    {
        $this->seedTbankForSmsPartner();
        Partner::query()->whereKey($this->partner->id)->update(['wallet_balance_cents' => 20000]);
        $ctx = $this->seedSmsAssignment(['student_phone' => null]);

        $this->mock(SmsRuService::class, function ($mock): void {
            $mock->shouldReceive('send')->never();
        });

        $this->postJson($this->smsSendUrl($ctx['assignment']->id), [], $this->smsAjaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_send_returns_422_phone_error_when_number_is_invalid(): void
    {
        $this->seedTbankForSmsPartner();
        Partner::query()->whereKey($this->partner->id)->update(['wallet_balance_cents' => 20000]);
        $ctx = $this->seedSmsAssignment(['student_phone' => null]);

        $this->mock(SmsRuService::class, function ($mock): void {
            $mock->shouldReceive('send')->never();
        });

        $this->postJson(
            $this->smsSendUrl($ctx['assignment']->id),
            ['phone' => '+7 (900) 11'],
            $this->smsAjaxHeaders()
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_send_returns_422_wallet_error_when_balance_is_below_fee(): void
    {
        $this->seedTbankForSmsPartner();
        Partner::query()->whereKey($this->partner->id)->update(['wallet_balance_cents' => 6999]);
        $ctx = $this->seedSmsAssignment(['student_phone' => '+79001112233']);

        $this->mock(SmsRuService::class, function ($mock): void {
            $mock->shouldReceive('send')->never();
        });

        $this->postJson($this->smsSendUrl($ctx['assignment']->id), [], $this->smsAjaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['wallet']);
    }

    public function test_send_returns_422_sms_error_when_gateway_fails(): void
    {
        $this->seedTbankForSmsPartner();
        Partner::query()->whereKey($this->partner->id)->update(['wallet_balance_cents' => 20000]);
        $ctx = $this->seedSmsAssignment(['student_phone' => '+79001112233']);

        $this->mock(SmsRuService::class, function ($mock): void {
            $mock->shouldReceive('send')->once()->andReturn('API error');
        });

        $this->postJson($this->smsSendUrl($ctx['assignment']->id), [], $this->smsAjaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['sms']);
    }
}
