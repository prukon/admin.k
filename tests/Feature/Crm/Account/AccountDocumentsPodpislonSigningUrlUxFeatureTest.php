<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Account;

use App\Models\Contract;
use Illuminate\Support\Facades\Auth;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Ссылка Подпислона из SMS на «Мои документы»: владелец видит, чужой/гость — нет.
 */
final class AccountDocumentsPodpislonSigningUrlUxFeatureTest extends CrmTestCase
{
    private const SAMPLE_URL = 'https://podpislon.ru/sign/pack/971890/9f1d11872060';

    public function test_owner_sees_sms_link_on_sent_contract_card(): void
    {
        $mine = $this->makeContract([
            'status' => Contract::STATUS_SENT,
            'provider_doc_id' => '971890',
            'provider_signing_url' => self::SAMPLE_URL,
        ]);

        $html = $this->get(route('account.documents.index'))
            ->assertOk()
            ->assertSee('data-id="'.$mine->id.'"', false)
            ->getContent();

        $this->assertStringContainsString('Ссылка на подпись', $html);
        $this->assertStringContainsString('Открыть ссылку из SMS', $html);
        $this->assertStringContainsString('href="'.self::SAMPLE_URL.'"', $html);
        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringContainsString('rel="noopener noreferrer"', $html);
    }

    public function test_card_without_url_does_not_show_sms_button(): void
    {
        $this->makeContract([
            'status' => Contract::STATUS_SENT,
            'provider_doc_id' => '971890',
            'provider_signing_url' => null,
        ]);

        $html = $this->get(route('account.documents.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Открыть ссылку из SMS', $html);
        $this->assertStringNotContainsString('podpislon.ru/sign/pack/', $html);
        $this->assertStringNotContainsString('Ссылка на подпись', $html);
    }

    public function test_card_does_not_render_stored_junk_as_sms_href(): void
    {
        $this->makeContract([
            'status' => Contract::STATUS_SENT,
            'provider_doc_id' => '971890',
            'provider_signing_url' => 'https://evil.example/phish',
        ]);

        $html = $this->get(route('account.documents.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Открыть ссылку из SMS', $html);
        $this->assertStringNotContainsString('evil.example', $html);
        $this->assertStringNotContainsString('Ссылка на подпись', $html);
    }

    public function test_signed_contract_still_shows_sms_link_when_url_is_stored(): void
    {
        $this->makeContract([
            'status' => Contract::STATUS_SIGNED,
            'provider_doc_id' => '971890',
            'provider_signing_url' => self::SAMPLE_URL,
        ]);

        $this->get(route('account.documents.index'))
            ->assertOk()
            ->assertSee('Открыть ссылку из SMS', false)
            ->assertSee(self::SAMPLE_URL, false);
    }

    public function test_guest_is_redirected_and_does_not_see_signing_url(): void
    {
        $this->makeContract([
            'status' => Contract::STATUS_SENT,
            'provider_signing_url' => self::SAMPLE_URL,
        ]);

        Auth::logout();

        $resp = $this->get(route('account.documents.index'));
        $resp->assertRedirect(route('login'));
        $resp->assertDontSee(self::SAMPLE_URL, false);
    }

    public function test_user_without_documents_view_gets_403(): void
    {
        $this->makeContract([
            'status' => Contract::STATUS_SENT,
            'provider_signing_url' => self::SAMPLE_URL,
        ]);

        $actor = $this->createUserWithoutPermission('account.documents.view', $this->partner);

        $resp = $this->actingAs($actor)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true])
            ->get(route('account.documents.index'));

        $resp->assertForbidden();
        $resp->assertDontSee(self::SAMPLE_URL, false);
    }

    public function test_foreign_school_does_not_see_this_school_signing_url(): void
    {
        $this->makeContract([
            'status' => Contract::STATUS_SENT,
            'provider_signing_url' => self::SAMPLE_URL,
        ]);

        $this->actingAs($this->foreignUser)
            ->withSession(['current_partner' => $this->foreignPartner->id, '2fa:passed' => true]);

        $html = $this->get(route('account.documents.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString(self::SAMPLE_URL, $html);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeContract(array $overrides = []): Contract
    {
        return Contract::create(array_merge([
            'school_id' => $this->partner->id,
            'user_id' => $this->user->id,
            'group_id' => null,
            'source_pdf_path' => 'documents/2026/09/account.pdf',
            'source_sha256' => str_repeat('c', 64),
            'provider' => 'podpislon',
            'provider_doc_id' => null,
            'status' => Contract::STATUS_DRAFT,
            'signed_pdf_path' => null,
            'signed_at' => null,
        ], $overrides));
    }
}
