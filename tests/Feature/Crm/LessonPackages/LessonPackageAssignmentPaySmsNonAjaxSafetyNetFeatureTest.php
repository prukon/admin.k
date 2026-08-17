<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\Partner;
use App\Services\SmsRuService;
use Illuminate\Support\Facades\Auth;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\LessonPackages\Concerns\LessonPackageAssignmentPaySmsTestHelpers;

/**
 * Non-AJAX safety-net для SMS: без X-Requested-With успех пишет в БД (JSON 200, как public-pay-link),
 * валидация — 302 + session errors, не пустой 200.
 *
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 * @see LessonPackagePublicPayCheckoutFeatureTest::test_issue_public_pay_link_non_ajax_post_returns_json_and_creates_link_record
 */
final class LessonPackageAssignmentPaySmsNonAjaxSafetyNetFeatureTest extends CrmTestCase
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

    public function test_non_ajax_send_returns_json_and_charges_wallet(): void
    {
        $this->seedTbankForSmsPartner();
        Partner::query()->whereKey($this->partner->id)->update(['wallet_balance_cents' => 20000]);
        $ctx = $this->seedSmsAssignment(['student_phone' => '+79001112233']);

        $this->mock(SmsRuService::class, function ($mock): void {
            $mock->shouldReceive('send')->once()->andReturn(true);
        });

        $response = $this->post($this->smsSendUrl($ctx['assignment']->id), [
            '_token' => csrf_token(),
        ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertSame(200, $response->getStatusCode());
        $response->assertJsonPath('success', true);
        $this->assertNotSame('', trim((string) $response->getContent()));

        $this->partner->refresh();
        $this->assertSame(13000, (int) $this->partner->wallet_balance_cents);
    }

    public function test_non_ajax_send_without_phone_redirects_with_phone_field_error_and_does_not_charge(): void
    {
        $this->seedTbankForSmsPartner();
        Partner::query()->whereKey($this->partner->id)->update(['wallet_balance_cents' => 20000]);
        $ctx = $this->seedSmsAssignment(['student_phone' => null]);

        $this->mock(SmsRuService::class, function ($mock): void {
            $mock->shouldReceive('send')->never();
        });

        $response = $this->from(route('admin.lesson-packages.assignments'))
            ->post($this->smsSendUrl($ctx['assignment']->id), [
                '_token' => csrf_token(),
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode(), 'Валидация не должна давать пустой/успешный 200');
        $response->assertStatus(302);
        $response->assertRedirect(route('admin.lesson-packages.assignments'));
        $response->assertSessionHasErrors(['phone']);

        $this->partner->refresh();
        $this->assertSame(20000, (int) $this->partner->wallet_balance_cents);
        $ctx['student']->refresh();
        $this->assertTrue($ctx['student']->phone === null || $ctx['student']->phone === '');
    }

    public function test_non_ajax_send_with_invalid_phone_redirects_with_phone_field_error(): void
    {
        $this->seedTbankForSmsPartner();
        Partner::query()->whereKey($this->partner->id)->update(['wallet_balance_cents' => 20000]);
        $ctx = $this->seedSmsAssignment(['student_phone' => null]);

        $this->mock(SmsRuService::class, function ($mock): void {
            $mock->shouldReceive('send')->never();
        });

        $response = $this->from(route('admin.lesson-packages.assignments'))
            ->post($this->smsSendUrl($ctx['assignment']->id), [
                '_token' => csrf_token(),
                'phone' => '+7 (900) 11',
            ]);

        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['phone']);
    }

    public function test_non_ajax_send_when_wallet_insufficient_redirects_with_wallet_error(): void
    {
        $this->seedTbankForSmsPartner();
        Partner::query()->whereKey($this->partner->id)->update(['wallet_balance_cents' => 1000]);
        $ctx = $this->seedSmsAssignment(['student_phone' => '+79001112233']);

        $this->mock(SmsRuService::class, function ($mock): void {
            $mock->shouldReceive('send')->never();
        });

        $response = $this->from(route('admin.lesson-packages.assignments'))
            ->post($this->smsSendUrl($ctx['assignment']->id), [
                '_token' => csrf_token(),
            ]);

        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertStatus(302);
        $response->assertSessionHasErrors(['wallet']);
        $this->partner->refresh();
        $this->assertSame(1000, (int) $this->partner->wallet_balance_cents);
    }

    public function test_non_ajax_preview_success_returns_json_not_empty_200(): void
    {
        $this->seedTbankForSmsPartner();
        $ctx = $this->seedSmsAssignment();

        $response = $this->get($this->smsPreviewUrl($ctx['assignment']->id));

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertSame(200, $response->getStatusCode());
        $response->assertJsonStructure(['phone', 'message', 'pay_url', 'phone_locked']);
        $this->assertNotSame('', trim((string) $response->getContent()));
    }

    public function test_non_ajax_preview_when_pay_link_unavailable_is_not_empty_200_or_500(): void
    {
        $ctx = $this->seedSmsAssignment();

        $response = $this->from(route('admin.lesson-packages.assignments'))
            ->get($this->smsPreviewUrl($ctx['assignment']->id));

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode(), 'Ошибка превью не должна маскироваться пустым 200');
        $this->assertContains($response->getStatusCode(), [302, 422]);
        if ($response->getStatusCode() === 302) {
            $response->assertSessionHasErrors(['sms']);
        } else {
            $response->assertJsonValidationErrors(['sms']);
        }
    }

    public function test_non_ajax_send_when_sms_ru_rejects_operator_redirects_with_sms_reason_not_empty_200(): void
    {
        $this->seedTbankForSmsPartner();
        Partner::query()->whereKey($this->partner->id)->update(['wallet_balance_cents' => 20000]);
        $ctx = $this->seedSmsAssignment(['student_phone' => '+79001112233']);

        $this->mock(SmsRuService::class, function ($mock): void {
            $mock->shouldReceive('send')->once()->andReturn(
                'SMS error: Вы не подключили данного оператора на данном отправителе. [204]'
            );
        });

        $response = $this->from(route('admin.lesson-packages.assignments'))
            ->post($this->smsSendUrl($ctx['assignment']->id), [
                '_token' => csrf_token(),
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode(), 'Ошибка шлюза не должна маскироваться пустым/успешным 200');
        $response->assertStatus(302);
        $response->assertRedirect(route('admin.lesson-packages.assignments'));
        $response->assertSessionHasErrors(['sms']);
        $this->assertSame(
            SmsRuService::USER_ERROR_OPERATOR_NOT_CONNECTED,
            session('errors')->first('sms')
        );
        $this->assertStringNotContainsString('Попробуйте позже', (string) session('errors')->first('sms'));

        $this->partner->refresh();
        $this->assertSame(20000, (int) $this->partner->wallet_balance_cents);
    }

    public function test_guest_non_ajax_send_is_denied_and_does_not_charge(): void
    {
        $this->seedTbankForSmsPartner();
        Partner::query()->whereKey($this->partner->id)->update(['wallet_balance_cents' => 20000]);
        $ctx = $this->seedSmsAssignment();
        Auth::logout();

        $this->mock(SmsRuService::class, function ($mock): void {
            $mock->shouldReceive('send')->never();
        });

        $response = $this->post($this->smsSendUrl($ctx['assignment']->id), [
            '_token' => csrf_token(),
        ]);

        $this->assertContains($response->getStatusCode(), [302, 401, 403, 419]);
        $this->assertNotSame(500, $response->getStatusCode());
        $this->partner->refresh();
        $this->assertSame(20000, (int) $this->partner->wallet_balance_cents);
    }

    public function test_user_without_permission_non_ajax_send_gets_403(): void
    {
        $this->seedTbankForSmsPartner();
        Partner::query()->whereKey($this->partner->id)->update(['wallet_balance_cents' => 20000]);
        $ctx = $this->seedSmsAssignment();

        $denied = $this->createUserWithoutPermission('lessonPackages.view', $this->partner);
        $this->actingAs($denied);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->mock(SmsRuService::class, function ($mock): void {
            $mock->shouldReceive('send')->never();
        });

        $this->post($this->smsSendUrl($ctx['assignment']->id), [
            '_token' => csrf_token(),
        ])->assertForbidden();

        $this->partner->refresh();
        $this->assertSame(20000, (int) $this->partner->wallet_balance_cents);
    }
}
