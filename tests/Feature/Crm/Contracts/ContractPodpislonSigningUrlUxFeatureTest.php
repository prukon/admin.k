<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Contracts;

use App\Models\Contract;

/**
 * Разметка ссылки Подпислона на карточке CRM: после статуса, @can sync, junk/пусто.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ContractPodpislonSigningUrlUxFeatureTest extends ContractPodpislonSigningUrlTestCase
{
    public function test_show_renders_signing_url_after_status_with_noopener(): void
    {
        $this->actingAsContractsViewer();
        $contract = $this->makeSentWithSigningUrl();

        $html = $this->get(route('contracts.show', $contract))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/Статус[\s\S]*id="contract-provider-signing-url"[\s\S]*Способ создания/u',
            $html
        );
        $this->assertStringContainsString('Ссылка на подпись', $html);
        $this->assertStringContainsString('href="'.self::SAMPLE_URL.'"', $html);
        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringContainsString('rel="noopener noreferrer"', $html);
    }

    public function test_show_hides_signing_url_row_when_empty_even_if_sync_is_allowed(): void
    {
        $actor = $this->actingAsContractsViewer();
        $this->grantContractsSync($actor);

        $contract = $this->makeSentWithSigningUrl(['provider_signing_url' => null]);

        $html = $this->get(route('contracts.show', $contract))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('id="contract-provider-signing-url"', $html);
        $this->assertStringNotContainsString('podpislon.ru/sign/pack/', $html);
        $this->assertStringNotContainsString('Ссылка на подпись', $html);
        $this->assertStringContainsString('id="syncStatusBtn"', $html);
    }

    public function test_show_does_not_render_stored_junk_as_href(): void
    {
        $this->actingAsContractsViewer();
        $contract = $this->makeSentWithSigningUrl([
            'provider_signing_url' => 'https://evil.example/phish',
        ]);

        $html = $this->get(route('contracts.show', $contract))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('id="contract-provider-signing-url"', $html);
        $this->assertStringNotContainsString('evil.example', $html);
        $this->assertStringNotContainsString('Ссылка на подпись', $html);
    }

    public function test_signed_contract_still_shows_sms_link_when_url_is_stored(): void
    {
        $this->actingAsContractsViewer();
        $contract = $this->makeSentWithSigningUrl([
            'status' => Contract::STATUS_SIGNED,
        ]);

        $this->get(route('contracts.show', $contract))
            ->assertOk()
            ->assertSee('id="contract-provider-signing-url"', false)
            ->assertSee(self::SAMPLE_URL, false);
    }

    public function test_awaiting_client_fill_without_url_does_not_show_sms_row(): void
    {
        $this->actingAsContractsViewer();
        $contract = $this->makeContract([
            'creation_mode' => Contract::CREATION_MODE_TEMPLATE,
            'status' => Contract::STATUS_AWAITING_CLIENT_FILL,
            'source_pdf_path' => null,
            'source_sha256' => null,
        ]);

        $html = $this->get(route('contracts.show', $contract))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('id="contract-provider-signing-url"', $html);
        $this->assertStringNotContainsString('Ссылка на подпись', $html);
        $this->assertStringNotContainsString('id="openSendModal"', $html);
        $this->assertStringNotContainsString('id="openResendModal"', $html);
    }

    public function test_draft_pdf_opens_send_modal_and_sent_opens_resend_into_same_modal(): void
    {
        $this->actingAsContractsViewer();

        $draftHtml = $this->get(route('contracts.show', $this->makeContract()))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('id="openSendModal"', $draftHtml);
        $this->assertStringContainsString('id="sendModal"', $draftHtml);
        $this->assertStringContainsString('id="sendSubmit"', $draftHtml);
        $this->assertStringNotContainsString('id="openResendModal"', $draftHtml);

        $sentHtml = $this->get(route('contracts.show', $this->makeSentWithSigningUrl()))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('id="openResendModal"', $sentHtml);
        $this->assertStringContainsString('id="sendModal"', $sentHtml);
        $this->assertStringContainsString('id="sendSubmit"', $sentHtml);
        $this->assertStringNotContainsString('id="openSendModal"', $sentHtml);
    }
}
