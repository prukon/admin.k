<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#payment-vitrina-sbp-only-index совпадает с витриной и клубным взносом.
 */
final class PaymentCheckoutMethodsDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_sbp_only_vitrina_labels(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="payment-vitrina-sbp-only-index"', $html);
        $start = strpos($html, 'id="payment-vitrina-sbp-only-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="cabinet-fio-select-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('POST /payment', $chunk);
        $this->assertStringContainsString('GET/POST /payment/club-fee', $chunk);
        $this->assertStringContainsString('Другие способы оплаты', $chunk);
        $this->assertStringContainsString('$tbankAvailable', $chunk);
        $this->assertStringContainsString('$canTbankCard', $chunk);
        $this->assertStringContainsString('$robokassaAvailable', $chunk);
        $this->assertStringContainsString('payment.method.tbankCard', $chunk);
        $this->assertStringContainsString('payment.method.robokassa', $chunk);
        $this->assertStringContainsString('payment-layout--sbp-only', $chunk);
        $this->assertStringContainsString('Способ оплаты', $chunk);
        $this->assertStringContainsString('Рекомендуемый способ', $chunk);
        $this->assertStringContainsString('Выберите ниже', $chunk);
        $this->assertStringContainsString('updateClubFeeOtherMethodsVisibility()', $chunk);
        $this->assertStringContainsString('payments §3.1.1', $chunk);
        $this->assertStringContainsString('§3.2.1', $chunk);
        $this->assertStringContainsString('PaymentCheckoutMethodsUxFeatureTest', $chunk);
        $this->assertStringContainsString('PaymentCheckoutMethodsNonAjaxSafetyNetFeatureTest', $chunk);
        $this->assertStringContainsString('PaymentCheckoutMethodsDocumentationContractTest', $chunk);
        $this->assertStringContainsString('BladeInlineJsSyntaxTest::test_club_fee_other_methods_visibility_js_contract', $chunk);
        $this->assertStringContainsString('/doc#payment-vitrina-sbp-only-index', $chunk);
    }

    public function test_payments_doc_describes_vitrina_and_club_fee_labels(): void
    {
        $html = $this->docFile('payments.html');
        $this->assertStringContainsString('/doc#payment-vitrina-sbp-only-index', $html);
        $this->assertStringContainsString('PaymentCheckoutMethodsUxFeatureTest', $html);
        $this->assertStringContainsString('PaymentCheckoutMethodsNonAjaxSafetyNetFeatureTest', $html);
        $this->assertStringContainsString('PaymentCheckoutMethodsDocumentationContractTest', $html);
        $this->assertStringContainsString('payment-layout--sbp-only', $html);
        $this->assertStringContainsString('updateClubFeeOtherMethodsVisibility()', $html);
        $this->assertStringContainsString('#clubFeeOtherMethodsColumn', $html);

        $vitrinaStart = strpos($html, 'id="vitrina-payment"');
        $this->assertNotFalse($vitrinaStart);
        $vitrinaEnd = strpos($html, 'id="up-public-pay"');
        $this->assertNotFalse($vitrinaEnd);
        $this->assertGreaterThan($vitrinaStart, $vitrinaEnd);
        $vitrina = substr($html, $vitrinaStart, $vitrinaEnd - $vitrinaStart);
        $this->assertStringContainsString('Способ оплаты / Выберите ниже', $vitrina);
        $this->assertStringContainsString('не «Рекомендуемый способ»', $vitrina);
        $this->assertStringContainsString('$tbankAvailable', $vitrina);
        $this->assertStringContainsString('$robokassaAvailable', $vitrina);

        $clubStart = strpos($html, 'id="club-fee-page"');
        $this->assertNotFalse($clubStart);
        $clubEnd = strpos($html, '3.3 Оплата через T‑Bank');
        $this->assertNotFalse($clubEnd);
        $this->assertGreaterThan($clubStart, $clubEnd);
        $club = substr($html, $clubStart, $clubEnd - $clubStart);
        $this->assertStringContainsString('#clubFeeOtherMethodsColumn', $club);
        $this->assertStringContainsString('updateClubFeeOtherMethodsVisibility()', $club);
        $this->assertStringContainsString('$renderTbankCard', $club);
        $this->assertStringContainsString('2+ группах без Робокассы', $club);
    }

    public function test_related_doc_pages_link_announcement_and_controller_title(): void
    {
        $payments = $this->docFile('payments.html');
        $testsStandards = $this->docFile('tests-standards.html');
        $cabinet = $this->docFile('dashboard-cabinet.html');
        $tbank = $this->docFile('tbank.html');
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');

        $this->assertStringContainsString('/doc#payment-vitrina-sbp-only-index', $payments);
        $this->assertStringContainsString('/doc#payment-vitrina-sbp-only-index', $cabinet);
        $this->assertStringContainsString('/doc#payment-vitrina-sbp-only-index', $tbank);
        $this->assertStringContainsString('PaymentCheckoutMethodsUxFeatureTest', $testsStandards);
        $this->assertStringContainsString('PaymentCheckoutMethodsNonAjaxSafetyNetFeatureTest', $testsStandards);
        $this->assertStringContainsString('без «Выберите ниже»', $controller);
        $this->assertStringContainsString('«Другие способы» только при карте/Робокассе', $controller);
        $this->assertStringContainsString('бейдж СБП «Способ оплаты»', $controller);
    }

    public function test_live_blades_match_documented_labels(): void
    {
        $root = dirname(__DIR__, 3);
        $paymentUser = (string) file_get_contents($root.'/resources/views/payment/paymentUser.blade.php');
        $clubFee = (string) file_get_contents($root.'/resources/views/payment/clubFee.blade.php');

        $this->assertStringNotContainsString('Выберите ниже', $paymentUser);
        $this->assertStringNotContainsString('Рекомендуемый способ', $paymentUser);
        $this->assertStringContainsString('recommend-badge">Способ оплаты', $paymentUser);
        $this->assertStringContainsString('$showOtherPaymentMethods = !empty($tbankAvailable) || !empty($robokassaAvailable)', $paymentUser);
        $this->assertStringContainsString('payment-layout--sbp-only', $paymentUser);
        $this->assertStringContainsString('Другие способы оплаты', $paymentUser);

        $this->assertStringNotContainsString('Рекомендуемый способ', $clubFee);
        $this->assertStringContainsString('recommend-badge">Способ оплаты', $clubFee);
        $this->assertStringContainsString('id="clubFeeOtherMethodsColumn"', $clubFee);
        $this->assertStringContainsString('function updateClubFeeOtherMethodsVisibility()', $clubFee);
        $this->assertStringContainsString('data-other-method="robokassa"', $clubFee);
        $this->assertStringContainsString('$showOtherPaymentMethods = $renderTbankCard || !empty($robokassaAvailable)', $clubFee);
        $this->assertStringContainsString('$otherMethodsInitiallyHidden', $clubFee);
        $this->assertStringContainsString('payment-layout--sbp-only', $clubFee);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
