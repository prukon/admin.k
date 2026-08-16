<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонсы /doc и разделы документации не должны описывать старый длинный текст SMS
 * и длинный URL как ссылку, которую копируют / шлют в SMS.
 */
final class UlpPaySmsDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_short_pay_link_and_one_sms_text(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="ulp-short-pay-link-index"', $html);
        $this->assertStringContainsString('id="assignments-pay-sms-index"', $html);
        $this->assertStringContainsString('Оплатите абонемент {сумма} руб: {ссылка}', $html);
        $this->assertStringContainsString('/p/{short_code}', $html);
        $this->assertStringContainsString('1 SMS', $html);
        $this->assertStringNotContainsString(
            'Оплатите абонемент «{название}» на сумму {сумма} руб.: {ссылка}',
            $html
        );
    }

    public function test_lesson_packages_and_payments_docs_match_short_sms_contract(): void
    {
        $lessonPackages = $this->docFile('lesson-packages.html');
        $payments = $this->docFile('payments.html');

        foreach ([$lessonPackages, $payments] as $html) {
            $this->assertStringContainsString('/p/{short_code}', $html);
            $this->assertStringNotContainsString(
                'Оплатите абонемент «{название}» на сумму {сумма} руб.: {ссылка}',
                $html
            );
        }

        $this->assertStringContainsString('Оплатите абонемент {сумма} руб: {ссылка}', $lessonPackages);
        $this->assertStringContainsString('без пробела тысяч', $lessonPackages);
        $this->assertStringContainsString('1 Unicode SMS', $lessonPackages);
        $this->assertStringContainsString('/pay/ulp/{token}', $payments);
        $this->assertStringContainsString('в «Скопировать ссылку» и SMS не отдаётся', $payments);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
