<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Enums\AuditEvent;
use App\Models\MyLog;
use App\Models\Partner;
use App\Models\PartnerWalletTransaction;
use App\Services\SmsRuService;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\LessonPackages\Concerns\LessonPackageAssignmentPaySmsTestHelpers;

/**
 * UX-баг: в модалке была общая «Попробуйте позже», хотя sms.ru вернул причину (204 и др.).
 * Полный стек без мока SmsRuService: Http::fake ответа шлюза → 422 errors.sms.
 *
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class LessonPackageAssignmentPaySmsGatewayErrorFeatureTest extends CrmTestCase
{
    use LessonPackageAssignmentPaySmsTestHelpers;

    private const STUDENT_DIGITS = '79001112233';

    private int $assignmentId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->grantSmsAssignmentsAccess();
        config([
            'billing.sms_send_fee' => 70.00,
            'services.sms_ru.api_id' => 'test-smsru-key',
            'services.sms_ru.from' => 'kidscrm',
        ]);
    }

    public function test_operator_sees_why_sms_ru_rejected_the_number_instead_of_try_later(): void
    {
        $this->seedReadyAssignment();
        $this->fakeSmsRuPerNumberError(204, 'Вы не подключили данного оператора на данном отправителе.');

        $response = $this->postJson($this->smsSendUrl($this->assignmentId), [], $this->smsAjaxHeaders());

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode(), 'Ошибка шлюза не должна маскироваться успешным 200');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['sms'])
            ->assertJsonMissingValidationErrors(['phone', 'wallet'])
            ->assertJsonPath('errors.sms.0', SmsRuService::USER_ERROR_OPERATOR_NOT_CONNECTED);

        $shown = (string) $response->json('errors.sms.0');
        $this->assertStringNotContainsString('Попробуйте позже', $shown);
        $this->assertStringContainsString('оператор этого номера не подключён', $shown);
        $this->assertNotSame('', trim($shown));
        Http::assertSent(fn ($request) => str_contains($request->url(), 'sms.ru/sms/send'));
    }

    public function test_operator_sees_sms_ru_status_text_in_modal_alert_field(): void
    {
        $this->seedReadyAssignment();
        $this->fakeSmsRuPerNumberError(202, 'Недостаточно средств на счете');

        $this->postJson($this->smsSendUrl($this->assignmentId), [], $this->smsAjaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['sms'])
            ->assertJsonPath('errors.sms.0', 'Не удалось отправить SMS: Недостаточно средств на счете');
    }

    public function test_operator_sees_gateway_unavailable_when_sms_ru_returns_http_error(): void
    {
        $this->seedReadyAssignment();
        Http::fake([
            'sms.ru/*' => Http::response('bad gateway', 502),
        ]);

        $this->postJson($this->smsSendUrl($this->assignmentId), [], $this->smsAjaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['sms'])
            ->assertJsonPath('errors.sms.0', SmsRuService::USER_ERROR_GATEWAY_UNAVAILABLE);
    }

    public function test_unknown_gateway_error_falls_back_to_generic_and_does_not_use_operator_text(): void
    {
        $this->seedReadyAssignment();
        config(['services.sms_ru.api_id' => '']);
        Http::fake(function () {
            $this->fail('sms.ru не должен вызываться при пустом api_id');
        });

        $response = $this->postJson($this->smsSendUrl($this->assignmentId), [], $this->smsAjaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['sms']);

        $this->assertSame(SmsRuService::USER_ERROR_GENERIC, $response->json('errors.sms.0'));
        $this->assertStringNotContainsString(
            'оператор этого номера не подключён',
            (string) $response->json('errors.sms.0')
        );
    }

    public function test_successful_send_does_not_put_gateway_error_on_sms_field(): void
    {
        $this->seedReadyAssignment();
        Http::fake([
            'sms.ru/*' => Http::response([
                'status' => 'OK',
                'status_code' => 100,
                'sms' => [
                    self::STUDENT_DIGITS => [
                        'status' => 'OK',
                        'status_code' => 100,
                        'sms_id' => 'ok-1',
                    ],
                ],
                'balance' => 269.19,
            ], 200),
        ]);

        $this->postJson($this->smsSendUrl($this->assignmentId), [], $this->smsAjaxHeaders())
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonMissingPath('errors');
    }

    public function test_missing_phone_errors_under_phone_and_does_not_call_sms_ru(): void
    {
        $this->seedTbankForSmsPartner();
        Partner::query()->whereKey($this->partner->id)->update(['wallet_balance_cents' => 20000]);
        $ctx = $this->seedSmsAssignment(['student_phone' => null]);
        Http::fake(function () {
            $this->fail('sms.ru не должен вызываться без номера');
        });

        $this->postJson($this->smsSendUrl($ctx['assignment']->id), [], $this->smsAjaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['phone'])
            ->assertJsonMissingValidationErrors(['sms']);
    }

    public function test_wallet_is_refunded_and_audit_is_not_written_when_operator_is_not_connected(): void
    {
        $this->seedReadyAssignment();
        $this->fakeSmsRuPerNumberError(204, 'Вы не подключили данного оператора на данном отправителе.');

        $this->postJson($this->smsSendUrl($this->assignmentId), [], $this->smsAjaxHeaders())
            ->assertStatus(422);

        $this->partner->refresh();
        $this->assertSame(20000, (int) $this->partner->wallet_balance_cents);
        $this->assertSame(0, (int) PartnerWalletTransaction::query()
            ->where('partner_id', $this->partner->id)
            ->where('type', 'debit')
            ->where('status', 'succeeded')
            ->count());
        $this->assertNull(
            MyLog::query()
                ->where('partner_id', $this->partner->id)
                ->where('event', AuditEvent::UserLessonPackagePaySmsSent->value)
                ->first()
        );
    }

    public function test_manager_without_permission_gets_403_and_sms_ru_is_not_called(): void
    {
        $this->seedReadyAssignment();
        Http::fake(function () {
            $this->fail('sms.ru не должен вызываться без права');
        });

        $denied = $this->createUserWithoutPermission('lessonPackages.view', $this->partner);
        $this->actingAs($denied);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->postJson($this->smsSendUrl($this->assignmentId), [], $this->smsAjaxHeaders())
            ->assertForbidden();
    }

    private function seedReadyAssignment(): void
    {
        $this->seedTbankForSmsPartner();
        Partner::query()->whereKey($this->partner->id)->update(['wallet_balance_cents' => 20000]);
        $ctx = $this->seedSmsAssignment(['student_phone' => '+'.self::STUDENT_DIGITS]);
        $this->assignmentId = (int) $ctx['assignment']->id;
    }

    private function fakeSmsRuPerNumberError(int $statusCode, string $statusText): void
    {
        Http::fake([
            'sms.ru/*' => Http::response([
                'status' => 'OK',
                'status_code' => 100,
                'sms' => [
                    self::STUDENT_DIGITS => [
                        'status' => 'ERROR',
                        'status_code' => $statusCode,
                        'status_text' => $statusText,
                        'sms_id' => '202634-1000000',
                    ],
                ],
                'balance' => 269.19,
            ], 200),
        ]);
    }
}
