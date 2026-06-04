<?php

namespace Tests\Unit\Services\Contracts;

use App\Services\Contracts\ContractTemplateEmailDefaults;
use PHPUnit\Framework\TestCase;

class ContractTemplateEmailDefaultsTest extends TestCase
{
    /** @test */
    public function subject_contains_child_placeholder_and_brand(): void
    {
        $subject = ContractTemplateEmailDefaults::subject();

        $this->assertStringContainsString(ContractTemplateEmailDefaults::PLACEHOLDER_CHILD_FULL_NAME, $subject);
        $this->assertStringContainsString('KidsCRM.online', $subject);
        $this->assertStringContainsString('личном кабинете', $subject);
    }

    /** @test */
    public function body_contains_all_placeholders_and_cabinet_messaging(): void
    {
        $body = ContractTemplateEmailDefaults::bodyHtml();

        foreach (ContractTemplateEmailDefaults::placeholderTokens() as $token) {
            $this->assertStringContainsString($token, $body);
        }

        $this->assertStringContainsString('подготовлен договор', $body);
        $this->assertStringContainsString('прямо в личном кабинете', $body);
        $this->assertStringContainsString('Пожалуйста, заполните до', $body);
        $this->assertStringContainsString('С уважением', $body);
    }
}
