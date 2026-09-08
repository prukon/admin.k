<?php

namespace Tests\Feature\Crm\Account;

use App\Models\Contract;
use Tests\Feature\Crm\Account\Concerns\InteractsWithAccountContractFill;
use Tests\Feature\Crm\Account\Concerns\InteractsWithFamilyAccountDocuments;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Blade/разметка: переключатель selected, каунтер, вкладка, автооткрытие fill с письма.
 *
 * @see /docs/documentation/account-contract-fill.html#account-documents-family-link
 */
final class AccountDocumentsFamilyContextMarkupFeatureTest extends CrmTestCase
{
    use InteractsWithAccountContractFill;
    use InteractsWithFamilyAccountDocuments;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useAccountContractFillStorage();
        $this->withSession($this->familyDocumentsSession());
        $this->seedFamilyStudents();
    }

    public function test_invitation_link_selects_contract_child_in_switcher_and_opens_fill(): void
    {
        $this->makeContractFor($this->brother1, Contract::STATUS_AWAITING_CLIENT_FILL);
        $sibling = $this->makeAwaitingFillContractFor($this->brother2);
        $url = $this->invitationDocumentsUrl($sibling, $this->brother2);

        $this->actingAsBrother1($this->brother1->id);
        $html = $this->get($url)->assertOk()->getContent();

        $this->assertStringContainsString('id="family-active-student"', $html);
        $this->assertMatchesRegularExpression(
            '/<option value="' . $this->brother2->id . '"[^>]*\bselected\b/u',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<option value="' . $this->brother1->id . '"[^>]*\bselected\b/u',
            $html
        );
        $this->assertStringContainsString('loadContractFill(' . $sibling->id . ',', $html);
        $this->assertMatchesRegularExpression(
            '/Учетная запись<span class="badge badge-info right">1<\/span>/',
            $this->sidebarChunk($html)
        );
        $this->assertMatchesRegularExpression(
            '/Мои документы<span class="badge badge-info ms-2">1<\/span>/',
            $html
        );
    }

    public function test_documents_tab_href_does_not_include_student_query(): void
    {
        $this->actingAsBrother1();
        $html = $this->get(route('account.documents.index'))->assertOk()->getContent();

        $this->assertStringContainsString('href="' . route('account.documents.index') . '"', $html);
        $this->assertStringNotContainsString(
            'href="' . route('account.documents.index', ['student' => $this->brother2->id]) . '"',
            $html
        );
    }

    public function test_opening_documents_without_student_keeps_login_child_selected(): void
    {
        $this->makeContractFor($this->brother1, Contract::STATUS_SENT);
        $sibling = $this->makeContractFor($this->brother2, Contract::STATUS_AWAITING_CLIENT_FILL);

        $this->actingAsBrother1();
        $html = $this->get(route('account.documents.index'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/<option value="' . $this->brother1->id . '"[^>]*\bselected\b/u',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<option value="' . $this->brother2->id . '"[^>]*\bselected\b/u',
            $html
        );
        $this->assertStringNotContainsString('loadContractFill(' . $sibling->id . ',', $html);
        $this->assertMatchesRegularExpression(
            '/Учетная запись<span class="badge badge-info right">1<\/span>/',
            $this->sidebarChunk($html)
        );
    }

    public function test_documents_tab_is_hidden_without_permission(): void
    {
        $actor = $this->createUserWithoutPermission('account.documents.view', $this->partner);
        $this->actingAs($actor)->withSession($this->familyDocumentsSession());

        $html = $this->get(route('account.user.edit'))->assertOk()->getContent();

        $this->assertStringNotContainsString(
            'href="' . route('account.documents.index') . '"',
            $html
        );
        $this->assertStringNotContainsString('Мои документы<span class="badge', $html);
    }

    public function test_lonely_student_has_no_family_switcher_on_documents(): void
    {
        $this->actingAs($this->user)->withSession($this->familyDocumentsSession());
        $html = $this->get(route('account.documents.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('id="family-active-student"', $html);
    }
}
