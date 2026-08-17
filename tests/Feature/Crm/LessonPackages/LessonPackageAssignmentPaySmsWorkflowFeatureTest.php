<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\Partner;
use App\Services\SmsRuService;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\LessonPackages\Concerns\LessonPackageAssignmentPaySmsTestHelpers;

/**
 * P2: страница назначений → превью → отправка SMS → данные видны без отдельного F5 (HTTP-цепочка).
 *
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class LessonPackageAssignmentPaySmsWorkflowFeatureTest extends CrmTestCase
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

    public function test_manager_opens_assignments_sends_sms_and_sees_updated_wallet_without_reload_gap(): void
    {
        $this->seedTbankForSmsPartner();
        Partner::query()->whereKey($this->partner->id)->update(['wallet_balance_cents' => 20000]);
        $ctx = $this->seedSmsAssignment(['student_phone' => '+79001112233']);

        $page = $this->get(route('admin.lesson-packages.assignments'));
        $page->assertOk();
        $this->assertNotSame('', trim((string) $page->getContent()));
        $page->assertSee('ulpSmsSendModal', false)
            ->assertSee('js-ulp-send-sms', false);

        $preview = $this->getJson($this->smsPreviewUrl($ctx['assignment']->id), $this->smsAjaxHeaders());
        $preview->assertOk()
            ->assertJsonPath('phone_locked', true);
        $this->assertStringContainsString('/p/', (string) $preview->json('pay_url'));
        $this->assertStringNotContainsString('/pay/ulp/', (string) $preview->json('pay_url'));

        $this->mock(SmsRuService::class, function ($mock): void {
            $mock->shouldReceive('send')->once()->andReturn(true);
        });

        $this->postJson($this->smsSendUrl($ctx['assignment']->id), [], $this->smsAjaxHeaders())
            ->assertOk()
            ->assertJsonPath('success', true);

        $row = $this->smsAssignmentDataRow($ctx['assignment']->id);
        $this->assertTrue((bool) $row['sms_send_available']);
        $this->assertTrue((bool) $row['sms_wallet_ok']);

        $this->partner->refresh();
        $this->assertSame(13000, (int) $this->partner->wallet_balance_cents);
    }

    public function test_manager_sees_sms_ru_reason_after_failed_send_and_wallet_stays_without_reload(): void
    {
        $this->seedTbankForSmsPartner();
        Partner::query()->whereKey($this->partner->id)->update(['wallet_balance_cents' => 20000]);
        $ctx = $this->seedSmsAssignment(['student_phone' => '+79001112233']);

        $page = $this->get(route('admin.lesson-packages.assignments'));
        $page->assertOk();
        $this->assertNotSame('', trim((string) $page->getContent()));
        $page->assertSee('ulp-sms-modal-alert', false);

        $this->mock(SmsRuService::class, function ($mock): void {
            $mock->shouldReceive('send')->once()->andReturn(
                'SMS error: Вы не подключили данного оператора на данном отправителе. [204]'
            );
        });

        $send = $this->postJson($this->smsSendUrl($ctx['assignment']->id), [], $this->smsAjaxHeaders());
        $this->assertNotSame(500, $send->getStatusCode());
        $send->assertStatus(422)
            ->assertJsonPath('errors.sms.0', SmsRuService::USER_ERROR_OPERATOR_NOT_CONNECTED);
        $this->assertStringNotContainsString('Попробуйте позже', (string) $send->json('errors.sms.0'));

        $row = $this->smsAssignmentDataRow($ctx['assignment']->id);
        $this->assertTrue((bool) $row['sms_send_available']);
        $this->assertTrue((bool) $row['sms_wallet_ok']);

        $this->partner->refresh();
        $this->assertSame(20000, (int) $this->partner->wallet_balance_cents);
    }
}
