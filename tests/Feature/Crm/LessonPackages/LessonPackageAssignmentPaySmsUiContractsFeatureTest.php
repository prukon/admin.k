<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\Partner;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\LessonPackages\Concerns\LessonPackageAssignmentPaySmsTestHelpers;

/**
 * Разметка вкладки и правила «если X, то по умолчанию Y» для колонки «Отправка СМС».
 * Ячейки DataTable собирает JS — флаги проверяем в JSON /data, ветки рендера — в исходнике blade.
 *
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class LessonPackageAssignmentPaySmsUiContractsFeatureTest extends CrmTestCase
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

    public function test_assignments_page_renders_sms_column_after_pay_link_and_standard_modal(): void
    {
        $html = $this->get(route('admin.lesson-packages.assignments'))
            ->assertOk()
            ->getContent();

        $this->assertNotSame('', trim($html));

        $payLinkTh = strpos($html, '>Ссылка на оплату</th>');
        $smsTh = strpos($html, '>Отправка СМС</th>');
        $packageTh = strpos($html, '>Абонемент</th>');
        $this->assertNotFalse($payLinkTh);
        $this->assertNotFalse($smsTh);
        $this->assertNotFalse($packageTh);
        $this->assertTrue($payLinkTh < $smsTh, 'Колонка «Отправка СМС» должна идти сразу после «Ссылка на оплату»');
        $this->assertTrue($smsTh < $packageTh, 'Колонка «Отправка СМС» должна быть перед «Абонемент»');

        $payLinkToggle = strpos($html, 'id="ulpColPayLink"');
        $smsToggle = strpos($html, 'id="ulpColSmsSend"');
        $this->assertNotFalse($payLinkToggle);
        $this->assertNotFalse($smsToggle);
        $this->assertTrue($payLinkToggle < $smsToggle);

        $modalPos = strpos($html, 'id="ulpSmsSendModal"');
        $this->assertNotFalse($modalPos);
        $historyPos = strpos($html, 'id="historyModal"', $modalPos);
        $modalChunk = substr(
            $html,
            $modalPos,
            $historyPos !== false ? $historyPos - $modalPos : 2500
        );
        $this->assertStringContainsString('modal-dialog modal-dialog-centered', $modalChunk);
        $this->assertStringNotContainsString('modal-fullscreen', $modalChunk);
        $this->assertStringNotContainsString('modal-xl', $modalChunk);

        $phonePos = strpos($modalChunk, 'id="ulp-sms-phone"');
        $previewPos = strpos($modalChunk, 'id="ulp-sms-message-preview"');
        $feePos = strpos($modalChunk, 'id="ulp-sms-fee-note"');
        $sendPos = strpos($modalChunk, 'id="ulp-sms-send-btn"');
        $this->assertNotFalse($phonePos);
        $this->assertNotFalse($previewPos);
        $this->assertNotFalse($feePos);
        $this->assertNotFalse($sendPos);
        $this->assertTrue($phonePos < $previewPos, 'Поле номера должно быть выше превью текста');
        $this->assertTrue($previewPos < $feePos, 'Превью текста должно быть выше напоминания о тарифе');
        $this->assertTrue($feePos < $sendPos, 'Напоминание о тарифе должно быть выше кнопки «Отправить»');

        $this->assertMatchesRegularExpression(
            '/id="ulp-sms-send-btn"[^>]*\bdisabled\b/',
            $modalChunk,
            'Кнопка «Отправить» в модалке при первом открытии должна быть disabled'
        );
        $this->assertStringContainsString('#ulpSmsSendModal #ulp-sms-send-btn:disabled', $html);
        $this->assertStringContainsString('background-color: #e9ecef !important', $html);
        $this->assertStringContainsString('color: #6c757d !important', $html);

        $cancelPos = strpos($modalChunk, '>Отмена</button>');
        $sendLabelPos = strpos($modalChunk, '>Отправить</button>');
        $this->assertNotFalse($cancelPos);
        $this->assertNotFalse($sendLabelPos);
        $this->assertTrue($cancelPos < $sendLabelPos);

        $this->assertStringContainsString('Отправка сообщений платная, 70 руб. за сообщение', $modalChunk);
        $this->assertStringContainsString('ulp-sms-phone-error', $modalChunk);
        $this->assertMatchesRegularExpression(
            '/id="ulp-sms-modal-alert"[^>]*\bd-none\b[^>]*><\/div>/',
            $modalChunk,
            'Алерт ошибки шлюза при первом открытии должен быть скрыт и пустым'
        );
        $this->assertStringContainsString('@can', file_get_contents(
            resource_path('views/admin/lessonPackages/tabs/assignments.blade.php')
        ) ?: '');
    }

    public function test_data_flags_send_button_when_pay_link_available_and_wallet_covers_fee(): void
    {
        $this->seedTbankForSmsPartner();
        Partner::query()->whereKey($this->partner->id)->update(['wallet_balance_cents' => 7000]);
        $ctx = $this->seedSmsAssignment(['student_phone' => '+79001112233']);

        $row = $this->smsAssignmentDataRow($ctx['assignment']->id);
        $this->assertTrue((bool) $row['pay_link_available']);
        $this->assertTrue((bool) $row['sms_send_available']);
        $this->assertTrue((bool) $row['sms_wallet_ok']);
    }

    public function test_data_flags_insufficient_wallet_keeps_send_available_but_wallet_not_ok(): void
    {
        $this->seedTbankForSmsPartner();
        Partner::query()->whereKey($this->partner->id)->update(['wallet_balance_cents' => 6999]);
        $ctx = $this->seedSmsAssignment(['student_phone' => '+79001112233']);

        $row = $this->smsAssignmentDataRow($ctx['assignment']->id);
        $this->assertTrue((bool) $row['sms_send_available']);
        $this->assertFalse((bool) $row['sms_wallet_ok']);
    }

    public function test_data_flags_do_not_disable_table_button_when_phone_is_missing(): void
    {
        $this->seedTbankForSmsPartner();
        Partner::query()->whereKey($this->partner->id)->update(['wallet_balance_cents' => 20000]);
        $ctx = $this->seedSmsAssignment(['student_phone' => null]);

        $row = $this->smsAssignmentDataRow($ctx['assignment']->id);
        $this->assertTrue((bool) $row['sms_send_available']);
        $this->assertTrue((bool) $row['sms_wallet_ok']);
    }

    public function test_data_flags_hide_send_when_pay_link_unavailable(): void
    {
        Partner::query()->whereKey($this->partner->id)->update(['wallet_balance_cents' => 20000]);
        $ctx = $this->seedSmsAssignment();

        $row = $this->smsAssignmentDataRow($ctx['assignment']->id);
        $this->assertFalse((bool) $row['pay_link_available']);
        $this->assertFalse((bool) $row['sms_send_available']);
    }

    public function test_data_flags_hide_send_when_assignment_is_paid_even_if_wallet_is_ok(): void
    {
        $this->seedTbankForSmsPartner();
        Partner::query()->whereKey($this->partner->id)->update(['wallet_balance_cents' => 20000]);
        $ctx = $this->seedSmsAssignment(['is_paid' => true]);

        $row = $this->smsAssignmentDataRow($ctx['assignment']->id);
        $this->assertFalse((bool) $row['sms_send_available']);
        $this->assertTrue((bool) $row['sms_wallet_ok']);
    }

    public function test_data_flags_hide_send_when_fee_is_below_ten_rubles(): void
    {
        $this->seedTbankForSmsPartner();
        Partner::query()->whereKey($this->partner->id)->update(['wallet_balance_cents' => 20000]);
        $ctx = $this->seedSmsAssignment(['fee' => 9.99]);

        $row = $this->smsAssignmentDataRow($ctx['assignment']->id);
        $this->assertFalse((bool) $row['sms_send_available']);
        $this->assertFalse((bool) $row['pay_link_available']);
    }

    public function test_data_flags_show_send_at_ten_ruble_fee_boundary(): void
    {
        $this->seedTbankForSmsPartner();
        Partner::query()->whereKey($this->partner->id)->update(['wallet_balance_cents' => 20000]);
        $ctx = $this->seedSmsAssignment(['fee' => 10.00]);

        $row = $this->smsAssignmentDataRow($ctx['assignment']->id);
        $this->assertTrue((bool) $row['sms_send_available']);
        $this->assertTrue((bool) $row['pay_link_available']);
    }
}
