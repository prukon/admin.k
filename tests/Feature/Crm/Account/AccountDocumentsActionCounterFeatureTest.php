<?php

namespace Tests\Feature\Crm\Account;

use App\Models\Contract;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Каунтер договоров «активных к действию» в сайдбаре («Учетная запись»)
 * и на вкладке «Мои документы».
 *
 * @see /docs/documentation/account-contract-fill.html
 */
final class AccountDocumentsActionCounterFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);
    }

    public function test_guest_is_redirected_from_documents_page(): void
    {
        Auth::logout();

        $this->get(route('account.documents.index'))
            ->assertRedirect(route('login'));
    }

    public function test_user_without_documents_view_gets_403_on_documents_page(): void
    {
        $actor = $this->createUserWithoutPermission('account.documents.view', $this->partner);

        $this->actingAs($actor)->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);

        $this->get(route('account.documents.index'))->assertForbidden();
    }

    public function test_sidebar_shows_badge_with_actionable_contracts_count(): void
    {
        $this->makeContract($this->user, Contract::STATUS_AWAITING_CLIENT_FILL);
        $this->makeContract($this->user, Contract::STATUS_SENT);
        $this->makeContract($this->user, Contract::STATUS_OPENED);

        $sidebar = $this->sidebarChunk(
            $this->get(route('account.documents.index'))->assertOk()->getContent()
        );

        $this->assertMatchesRegularExpression(
            '/Учетная запись<span class="badge badge-info right">3<\/span>/',
            $sidebar
        );
    }

    public function test_sidebar_hides_badge_when_no_actionable_contracts(): void
    {
        $this->makeContract($this->user, Contract::STATUS_SIGNED);
        $this->makeContract($this->user, Contract::STATUS_DRAFT);

        $sidebar = $this->sidebarChunk(
            $this->get(route('account.documents.index'))->assertOk()->getContent()
        );

        $this->assertStringContainsString('Учетная запись</p>', $sidebar);
        $this->assertStringNotContainsString(
            'Учетная запись<span class="badge badge-info right">',
            $sidebar
        );
    }

    public function test_sidebar_does_not_count_inactive_statuses(): void
    {
        foreach ([
            Contract::STATUS_DRAFT,
            Contract::STATUS_SIGNED,
            Contract::STATUS_EXPIRED,
            Contract::STATUS_REVOKED,
            Contract::STATUS_FAILED,
        ] as $status) {
            $this->makeContract($this->user, $status);
        }

        $this->makeContract($this->user, Contract::STATUS_GENERATING_PDF);

        $sidebar = $this->sidebarChunk(
            $this->get(route('account.user.edit'))->assertOk()->getContent()
        );

        $this->assertMatchesRegularExpression(
            '/Учетная запись<span class="badge badge-info right">1<\/span>/',
            $sidebar
        );
    }

    public function test_sidebar_counts_only_current_users_contracts(): void
    {
        $other = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->user->role_id,
        ]);

        $this->makeContract($this->user, Contract::STATUS_SENT);
        $this->makeContract($other, Contract::STATUS_AWAITING_CLIENT_FILL);
        $this->makeContract($other, Contract::STATUS_OPENED);
        $this->makeContract($other, Contract::STATUS_GENERATING_PDF);

        $sidebar = $this->sidebarChunk(
            $this->get(route('account.documents.index'))->assertOk()->getContent()
        );

        $this->assertMatchesRegularExpression(
            '/Учетная запись<span class="badge badge-info right">1<\/span>/',
            $sidebar
        );
    }

    public function test_documents_tab_shows_badge_with_left_spacing_and_same_count(): void
    {
        $this->makeContract($this->user, Contract::STATUS_AWAITING_CLIENT_FILL);
        $this->makeContract($this->user, Contract::STATUS_OPENED);

        $html = $this->get(route('account.documents.index'))
            ->assertOk()
            ->assertViewHas('unsignedContractsCount', 2)
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/Мои документы<span class="badge badge-info ms-2">2<\/span>/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/Учетная запись<span class="badge badge-info right">2<\/span>/',
            $this->sidebarChunk($html)
        );
    }

    public function test_documents_tab_hides_badge_when_zero_actionable_contracts(): void
    {
        $this->makeContract($this->user, Contract::STATUS_SIGNED);

        $html = $this->get(route('account.documents.index'))
            ->assertOk()
            ->assertViewHas('unsignedContractsCount', 0)
            ->getContent();

        $this->assertStringContainsString('Мои документы', $html);
        $this->assertStringNotContainsString(
            'Мои документы<span class="badge badge-info ms-2">',
            $html
        );
    }

    public function test_user_edit_page_shows_documents_tab_badge_via_composer(): void
    {
        $this->makeContract($this->user, Contract::STATUS_SENT);
        $this->makeContract($this->user, Contract::STATUS_GENERATING_PDF);

        $html = $this->get(route('account.user.edit'))
            ->assertOk()
            ->assertViewIs('account.index')
            ->assertViewHas('unsignedContractsCount', 2)
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/Мои документы<span class="badge badge-info ms-2">2<\/span>/',
            $html
        );
    }

    public function test_each_actionable_status_increments_counter(): void
    {
        foreach ([
            Contract::STATUS_AWAITING_CLIENT_FILL,
            Contract::STATUS_SENT,
            Contract::STATUS_OPENED,
            Contract::STATUS_GENERATING_PDF,
        ] as $status) {
            $this->makeContract($this->user, $status);
        }

        $this->get(route('account.documents.index'))
            ->assertOk()
            ->assertViewHas('unsignedContractsCount', 4);
    }

    /**
     * UX: без права documents.view вкладка скрыта (@can), каунтер в сайдбаре не рисуется,
     * даже если у пользователя есть «активные» договоры.
     */
    public function test_user_without_documents_view_sees_no_counter_on_account_page(): void
    {
        $actor = $this->createUserWithRole('trainer', $this->partner);
        $this->makeContract($actor, Contract::STATUS_AWAITING_CLIENT_FILL);
        $this->makeContract($actor, Contract::STATUS_SENT);

        $this->actingAs($actor)->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);

        $html = $this->get(route('account.user.edit'))
            ->assertOk()
            ->assertViewHas('unsignedContractsCount', 0)
            ->getContent();

        $this->assertStringNotContainsString(
            'href="' . route('account.documents.index') . '"',
            $html
        );
        $this->assertStringNotContainsString(
            'Учетная запись<span class="badge badge-info right">',
            $this->sidebarChunk($html)
        );
    }

    /**
     * Регрессия: старое правило «все кроме signed» завышало каунтер за счёт draft/expired.
     */
    public function test_counter_ignores_draft_even_when_many_exist(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->makeContract($this->user, Contract::STATUS_DRAFT);
        }
        $this->makeContract($this->user, Contract::STATUS_SENT);

        $html = $this->get(route('account.documents.index'))
            ->assertOk()
            ->assertViewHas('unsignedContractsCount', 1)
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/Мои документы<span class="badge badge-info ms-2">1<\/span>/',
            $html
        );
    }

    private function makeContract(User $user, string $status): Contract
    {
        return Contract::create([
            'school_id'       => $this->partner->id,
            'user_id'         => $user->id,
            'group_id'        => null,
            'creation_mode'   => Contract::CREATION_MODE_PDF,
            'source_pdf_path' => 'documents/counter-test.pdf',
            'source_sha256'   => str_repeat('a', 64),
            'status'          => $status,
            'provider'        => 'podpislon',
        ]);
    }

    private function sidebarChunk(string $html): string
    {
        $sidebarStart = strpos($html, 'nav-sidebar');
        $this->assertNotFalse($sidebarStart, 'Sidebar not found in response');

        return substr($html, (int) $sidebarStart, 8000);
    }
}
