<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

/**
 * Начальная разметка модалки счёта: отправка выключена, письмо скрыто до загрузки, ошибки под полями.
 */
final class SettingPricesInvoiceEmailMarkupFeatureTest extends SettingPricesInvoiceEmailTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->asAdmin();
    }

    public function test_both_tabs_open_the_invoice_modal_closed_and_disabled(): void
    {
        foreach ([route('admin.settingPrices.indexMenu'), route('admin.settingPrices.users')] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertModalStartsClosed($html);

            $withoutScripts = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);
            $this->assertIsString($withoutScripts);
            $this->assertStringNotContainsString(
                'class="dropdown-item setting-prices-invoice-email"',
                $withoutScripts
            );
        }
    }

    public function test_viewer_without_invoice_permission_still_sees_a_closed_modal(): void
    {
        $actor = $this->createUserWithRole('user', $this->partner);
        $this->grant($actor, 'setPrices.view');
        $this->actingAs($actor);

        $html = $this->get(route('admin.settingPrices.indexMenu'))->assertOk()->getContent();
        $this->assertModalStartsClosed($html);
    }

    private function assertModalStartsClosed(string $html): void
    {
        $start = strpos($html, 'id="setting-prices-invoice-email-modal"');
        $end = strpos($html, 'setting-prices-invoice-email-send', $start === false ? 0 : $start);
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);
        $chunk = substr($html, (int) $start, (int) $end - (int) $start);

        $this->assertStringContainsString('modal-dialog modal-dialog-centered', $chunk);
        $this->assertStringNotContainsString('modal-fullscreen', $chunk);
        $this->assertStringNotContainsString('modal-xl', $chunk);

        $order = ['student_name', 'team', 'month', 'amount', 'package', 'parent_name', 'email'];
        $cursor = 0;
        foreach ($order as $fact) {
            $at = strpos($chunk, 'data-fact="'.$fact.'"', $cursor);
            $this->assertNotFalse($at, $fact);
            $cursor = $at;
        }

        $this->assertStringNotContainsString('data-fact="pay_url"', $chunk);
        $this->assertStringNotContainsString('>Оплата<', $chunk);
        $this->assertStringNotContainsString('Письмо по шаблону уведомлений по умолчанию.', $chunk);

        foreach (['user_id', 'team_id', 'new_month', 'package', 'amount', 'email', 'pay_url'] as $field) {
            $this->assertStringContainsString('data-error-for="'.$field.'"', $chunk);
        }

        $this->assertStringContainsString('id="setting-prices-invoice-email-preview" class="mt-3" style="display:none"', $chunk);
        $this->assertStringNotContainsString('setting-prices-invoice-email-preview-btn', $html);
        $this->assertStringNotContainsString('>Превью<', $chunk);
        $this->assertStringContainsString('id="setting-prices-invoice-email-send" disabled', $html);
    }
}
