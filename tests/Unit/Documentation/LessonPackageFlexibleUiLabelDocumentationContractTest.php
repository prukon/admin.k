<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc#flexible-ui-label-index совпадает с UI-подписью типа flexible = «Предоплата».
 */
final class LessonPackageFlexibleUiLabelDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_flexible_ui_label(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="flexible-ui-label-index"', $html);
        $start = strpos($html, 'id="flexible-ui-label-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="contract-annul-after-send-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('schedule_type = flexible', $chunk);
        $this->assertStringContainsString('Предоплата', $chunk);
        $this->assertStringContainsString('Абонемент предоплаты', $chunk);
        $this->assertStringContainsString('Привязать абонемент предоплаты', $chunk);
        $this->assertStringContainsString('Гибкий 8 занятий', $chunk);
        $this->assertStringContainsString('schedule_type_label', $chunk);
        $this->assertStringContainsString('schoolCalFlexibleButtonDefaultLabel', $chunk);
        $this->assertStringContainsString("setScheduleTypes(['fixed', 'flexible'])", $chunk);
        $this->assertStringContainsString('errors.schedule_type', $chunk);
        $this->assertStringContainsString('LessonPackageFlexibleUiLabelAccessFeatureTest', $chunk);
        $this->assertStringContainsString('LessonPackageFlexibleUiLabelMarkupFeatureTest', $chunk);
        $this->assertStringContainsString('LessonPackageFlexibleUiLabelAjaxContractFeatureTest', $chunk);
        $this->assertStringContainsString('LessonPackageFlexibleUiLabelNonAjaxSafetyNetFeatureTest', $chunk);
        $this->assertStringContainsString('LessonPackageFlexibleUiLabelWorkflowFeatureTest', $chunk);
        $this->assertStringContainsString('Тип: Фиксированный → Предоплата', $chunk);
        $this->assertStringContainsString('Подпись <b>«Фиксированный»</b> не меняли', $chunk);
        $this->assertStringContainsString('меняет тип', $chunk);
        $this->assertStringContainsString('Предоплата: «название»', $chunk);
        $this->assertStringContainsString('{{package_type}}', $chunk);
        $this->assertStringContainsString('type_label', $chunk);
        $this->assertStringContainsString('Excel', $chunk);
        $this->assertStringContainsString('Консоль: абонемент предоплаты', $chunk);
        $this->assertStringContainsString('lesson-packages#flexible-ui-label', $chunk);
        $this->assertStringContainsString('/doc#flexible-ui-label-index', $html);
    }

    public function test_lesson_packages_page_documents_flexible_ui_label(): void
    {
        $html = $this->docFile('lesson-packages.html');

        $this->assertStringContainsString('id="flexible-ui-label"', $html);
        $this->assertStringContainsString('/doc#flexible-ui-label-index', $html);
        $this->assertStringContainsString('schedule_type = flexible', $html);
        $this->assertStringContainsString('Предоплата', $html);
        $this->assertStringContainsString('LessonPackageFlexibleUiLabelAccessFeatureTest', $html);
        $this->assertStringContainsString('LessonPackageFlexibleUiLabelMarkupFeatureTest', $html);
        $this->assertStringContainsString('LessonPackageFlexibleUiLabelAjaxContractFeatureTest', $html);
        $this->assertStringContainsString('LessonPackageFlexibleUiLabelNonAjaxSafetyNetFeatureTest', $html);
        $this->assertStringContainsString('LessonPackageFlexibleUiLabelWorkflowFeatureTest', $html);
        $this->assertStringContainsString('option value="flexible"', $html);
        $this->assertStringContainsString('schedule_type_label', $html);
        $this->assertStringContainsString('Тип: Фиксированный → Предоплата', $html);
        $this->assertStringContainsString('«Фиксированный» не трогали', $html);
        $this->assertStringContainsString('Предоплата: «название»', $html);
    }

    public function test_payment_notifications_page_documents_prepay_checkbox_label(): void
    {
        $html = $this->docFile('setting-prices-payment-notifications.html');

        $this->assertStringContainsString('schedule_type_labels.flexible', $html);
        $this->assertStringContainsString('Предоплата', $html);
        $this->assertStringContainsString('/doc#flexible-ui-label-index', $html);
        $this->assertStringContainsString('setScheduleTypes', $html);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
