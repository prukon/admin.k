<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Contracts;

use App\Models\Contract;
use App\Models\ContractEvent;

/**
 * UX: текст ошибки на карточке, в журнале и title бейджа в списке.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ContractSendFailureMessageUxFeatureTest extends ContractSendFailureMessageTestCase
{
    public function test_show_page_prints_error_under_status_badge_and_in_journal(): void
    {
        $this->actingAsContractsViewer();
        $contract = $this->makeFailedContractWithProviderMessage();

        $html = $this->get(route('contracts.show', $contract))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/Статус[\s\S]*id="contract-status-error"[\s\S]*Способ создания/u',
            $html
        );
        $this->assertStringContainsString('id="contract-status-error"', $html);
        $this->assertStringContainsString(self::PROVIDER_ERROR, $html);
        $this->assertStringContainsString('contract-event-error', $html);
        $this->assertStringContainsString('Ошибка', $html);
        $this->assertStringContainsString('id="openResendModal"', $html);
    }

    public function test_show_page_hides_status_error_when_not_failed(): void
    {
        $this->actingAsContractsViewer();
        $contract = $this->makeDraftContract();

        $html = $this->get(route('contracts.show', $contract))->assertOk()->getContent();

        $this->assertStringNotContainsString('id="contract-status-error"', $html);
        $this->assertStringNotContainsString(self::PROVIDER_ERROR, $html);
        $this->assertStringContainsString('id="openSendModal"', $html);
    }

    public function test_show_hides_status_error_when_status_is_sent_even_with_old_failed_event(): void
    {
        $this->actingAsContractsViewer();
        $contract = $this->makeFailedContractWithProviderMessage();
        $contract->update(['status' => Contract::STATUS_SENT]);
        ContractEvent::create([
            'contract_id' => $contract->id,
            'author_id' => $this->user->id,
            'type' => 'sent',
            'payload_json' => json_encode(['res' => ['ok' => true]], JSON_UNESCAPED_UNICODE),
        ]);

        $html = $this->get(route('contracts.show', $contract->fresh()))->assertOk()->getContent();

        $this->assertStringNotContainsString('id="contract-status-error"', $html);
        $this->assertStringContainsString('contract-event-error', $html);
        $this->assertStringContainsString(self::PROVIDER_ERROR, $html);
        $this->assertStringContainsString('Отправлено СМС', $html);
        $this->assertNull($contract->fresh()->lastFailureMessage());
    }

    public function test_failed_event_with_empty_payload_does_not_render_empty_status_error(): void
    {
        $this->actingAsContractsViewer();
        $contract = $this->makeDraftContract(['status' => Contract::STATUS_FAILED]);
        ContractEvent::create([
            'contract_id' => $contract->id,
            'author_id' => $this->user->id,
            'type' => 'failed',
            'payload_json' => json_encode(['res' => ['ok' => false]], JSON_UNESCAPED_UNICODE),
        ]);

        $html = $this->get(route('contracts.show', $contract->fresh()))->assertOk()->getContent();

        $this->assertStringNotContainsString('id="contract-status-error"', $html);
        $this->assertNull($contract->fresh()->lastFailureMessage());
    }

    public function test_sent_journal_row_does_not_print_event_error(): void
    {
        $this->actingAsContractsViewer();
        $contract = $this->makeDraftContract(['status' => Contract::STATUS_SENT]);
        ContractEvent::create([
            'contract_id' => $contract->id,
            'author_id' => $this->user->id,
            'type' => 'sent',
            'payload_json' => json_encode(['res' => ['ok' => true]], JSON_UNESCAPED_UNICODE),
        ]);

        $html = $this->get(route('contracts.show', $contract))->assertOk()->getContent();

        $this->assertStringNotContainsString('id="contract-status-error"', $html);
        $this->assertStringNotContainsString('contract-event-error', $html);
        $this->assertStringContainsString('Отправлено СМС', $html);
    }

    public function test_journal_shows_resend_failed_message(): void
    {
        $this->actingAsContractsViewer();
        $contract = $this->makeDraftContract(['status' => Contract::STATUS_FAILED]);
        ContractEvent::create([
            'contract_id' => $contract->id,
            'author_id' => $this->user->id,
            'type' => 'resend_failed',
            'payload_json' => json_encode(['message' => self::PROVIDER_ERROR], JSON_UNESCAPED_UNICODE),
        ]);

        $this->get(route('contracts.show', $contract))
            ->assertOk()
            ->assertSee('id="contract-status-error"', false)
            ->assertSee(self::PROVIDER_ERROR, false)
            ->assertSee('contract-event-error', false)
            ->assertSee('Повторная отправка СМС — ошибка', false);
    }

    public function test_provider_error_html_is_escaped_on_card_and_journal(): void
    {
        $this->actingAsContractsViewer();
        $xss = '<script>alert(1)</script>';
        $contract = $this->makeFailedContractWithProviderMessage($xss);

        $html = $this->get(route('contracts.show', $contract))->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
    }

    public function test_list_badge_uses_status_error_as_title_only_when_set(): void
    {
        $index = (string) file_get_contents(resource_path('views/contracts/index.blade.php'));
        $this->assertStringContainsString('row.status_error', $index);
        $this->assertStringContainsString("row.status_error ? escapeHtml(row.status_error) : ''", $index);
        $this->assertStringContainsString("const titleAttr = statusError ? ' title=\"' + statusError + '\"' : ''", $index);
        $this->assertStringContainsString("'<span class=\"badge ' + badgeClass + '\"' + titleAttr + '>'", $index);
    }

    public function test_list_page_js_does_not_force_title_when_status_error_absent(): void
    {
        $this->actingAsContractsViewer();
        $html = $this->get(route('contracts.index'))->assertOk()->getContent();

        $this->assertStringContainsString("const titleAttr = statusError ? ' title=\"' + statusError + '\"' : ''", $html);
        $this->assertStringContainsString('escapeHtml(row.status_error)', $html);
    }

    public function test_list_json_status_error_is_null_for_non_failed_rows(): void
    {
        $this->actingAsContractsViewer();
        $this->makeDraftContract();

        $row = $this->getJson('/client-contracts/data?draw=1&start=0&length=20')
            ->assertOk()
            ->json('data.0');

        $this->assertNull($row['status_error']);
    }

    public function test_journal_shows_legacy_payload_without_top_level_message_key(): void
    {
        $this->actingAsContractsViewer();
        $contract = $this->makeDraftContract(['status' => Contract::STATUS_FAILED]);
        ContractEvent::create([
            'contract_id' => $contract->id,
            'author_id' => $this->user->id,
            'type' => 'failed',
            'payload_json' => json_encode([
                'res' => [
                    'raw' => [
                        'status' => false,
                        'message' => self::PROVIDER_ERROR,
                    ],
                ],
            ], JSON_UNESCAPED_UNICODE),
        ]);

        $this->get(route('contracts.show', $contract))
            ->assertOk()
            ->assertSee(self::PROVIDER_ERROR, false)
            ->assertSee('contract-event-error', false);
    }

    public function test_parent_cabinet_does_not_render_admin_status_error_row(): void
    {
        $contract = $this->makeFailedContractWithProviderMessage();
        $contract->update(['user_id' => $this->user->id]);

        $this->actingAs($this->user)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $html = $this->get(route('account.documents.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('id="contract-status-error"', $html);
        $this->assertStringNotContainsString('contract-event-error', $html);
        $this->assertStringNotContainsString(self::PROVIDER_ERROR, $html);
    }
}
