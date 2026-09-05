<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Contracts;

use App\Models\Contract;
use App\Models\User;

final class ContractAnnulAfterSendUxFeatureTest extends ContractsFeatureTestCase
{
    private function makeShowContract(string $status, array $overrides = []): Contract
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);

        return Contract::create(array_merge([
            'school_id'       => $this->partner->id,
            'user_id'         => $student->id,
            'group_id'        => null,
            'source_pdf_path' => 'documents/annul-ux/' . uniqid('', true) . '.pdf',
            'source_sha256'   => str_repeat('u', 64),
            'provider'        => 'podpislon',
            'provider_doc_id' => 'pkg-ux-' . uniqid('', true),
            'status'          => $status,
        ], $overrides));
    }

    public function test_sent_card_shows_annul_button_not_refund_revoke(): void
    {
        $contract = $this->makeShowContract(Contract::STATUS_SENT);

        $html = $this->get(route('contracts.show', $contract))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="annulAfterSendBtn"', $html);
        $this->assertStringContainsString('Аннулировать', $html);
        $this->assertStringNotContainsString('id="revokeAwaitingBtn"', $html);
        $this->assertStringContainsString('70 ₽ не возвращаются', $html);
    }

    public function test_opened_card_shows_annul_button(): void
    {
        $contract = $this->makeShowContract(Contract::STATUS_OPENED);

        $this->get(route('contracts.show', $contract))
            ->assertOk()
            ->assertSee('id="annulAfterSendBtn"', false);
    }

    public function test_awaiting_fill_card_shows_refund_revoke_not_annul(): void
    {
        $contract = $this->makeShowContract(Contract::STATUS_AWAITING_CLIENT_FILL, [
            'creation_mode'   => Contract::CREATION_MODE_TEMPLATE,
            'source_pdf_path' => null,
            'source_sha256'   => null,
            'provider_doc_id' => null,
            'fill_expires_at' => now()->addDays(7),
        ]);

        $html = $this->get(route('contracts.show', $contract))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="revokeAwaitingBtn"', $html);
        $this->assertStringNotContainsString('id="annulAfterSendBtn"', $html);
    }

    public function test_signed_card_does_not_show_annul_button(): void
    {
        $contract = $this->makeShowContract(Contract::STATUS_SIGNED, [
            'signed_pdf_path' => 'documents/annul-ux/signed.pdf',
        ]);

        $this->get(route('contracts.show', $contract))
            ->assertOk()
            ->assertDontSee('id="annulAfterSendBtn"', false);
    }

    public function test_revoked_card_shows_signed_download_when_file_exists(): void
    {
        $contract = $this->makeShowContract(Contract::STATUS_REVOKED, [
            'signed_pdf_path' => 'documents/annul-ux/late-signed.pdf',
        ]);

        $html = $this->get(route('contracts.show', $contract))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('id="annulAfterSendBtn"', $html);
        $this->assertStringContainsString('Скачать оригинал', $html);
        $this->assertStringContainsString('Скачать подписанный', $html);
        $this->assertStringContainsString(route('contracts.downloadSigned', $contract), $html);
    }

    public function test_revoked_without_signed_pdf_keeps_sync_button(): void
    {
        $this->grantPermissionToRoleForPartner(
            $this->user->role_id,
            $this->partner->id,
            self::PERM_CONTRACTS_SYNC
        );

        $contract = $this->makeShowContract(Contract::STATUS_REVOKED, [
            'signed_pdf_path' => null,
        ]);

        $this->get(route('contracts.show', $contract))
            ->assertOk()
            ->assertSee('id="syncStatusBtn"', false);
    }

    public function test_failed_expired_draft_and_generating_do_not_show_annul_button(): void
    {
        foreach ([
            Contract::STATUS_DRAFT,
            Contract::STATUS_GENERATING_PDF,
            Contract::STATUS_FAILED,
            Contract::STATUS_EXPIRED,
        ] as $status) {
            $contract = $this->makeShowContract($status);
            $html = $this->get(route('contracts.show', $contract))->assertOk()->getContent();
            $this->assertStringNotContainsString('id="annulAfterSendBtn"', $html, $status);
        }
    }

    public function test_pdf_mode_sent_card_shows_annul_button(): void
    {
        $contract = $this->makeShowContract(Contract::STATUS_SENT, [
            'creation_mode' => Contract::CREATION_MODE_PDF,
        ]);

        $this->get(route('contracts.show', $contract))
            ->assertOk()
            ->assertSee('id="annulAfterSendBtn"', false);
    }

    public function test_after_annul_card_hides_annul_and_resend_and_shows_revoked_badge(): void
    {
        $contract = $this->makeShowContract(Contract::STATUS_OPENED);

        $this->get(route('contracts.show', $contract))
            ->assertOk()
            ->assertSee('id="annulAfterSendBtn"', false)
            ->assertSee('id="openResendModal"', false);

        $this->postJson(route('contracts.revoke', $contract), [])->assertOk();

        $html = $this->get(route('contracts.show', $contract))->assertOk()->getContent();
        $this->assertStringNotContainsString('id="annulAfterSendBtn"', $html);
        $this->assertStringNotContainsString('id="openResendModal"', $html);
        $this->assertStringContainsString('Отозвано', $html);
    }

    public function test_revoked_with_signed_pdf_hides_sync_even_with_sync_permission(): void
    {
        $this->grantPermissionToRoleForPartner(
            $this->user->role_id,
            $this->partner->id,
            self::PERM_CONTRACTS_SYNC
        );

        $contract = $this->makeShowContract(Contract::STATUS_REVOKED, [
            'signed_pdf_path' => 'documents/annul-ux/has-signed.pdf',
        ]);

        $this->get(route('contracts.show', $contract))
            ->assertOk()
            ->assertDontSee('id="syncStatusBtn"', false);
    }
}
