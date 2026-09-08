<?php

namespace Tests\Feature\Crm\Account;

use App\Models\Contract;
use App\Models\User;
use App\Services\Users\FamilyStudentContextService;
use Illuminate\Support\Facades\Auth;
use Tests\Feature\Crm\Account\Concerns\InteractsWithAccountContractFill;
use Tests\Feature\Crm\Account\Concerns\InteractsWithFamilyAccountDocuments;
use Tests\Feature\Crm\CrmTestCase;

/**
 * UX семейного кабинета: ссылка из письма открывает документы нужного ребёнка.
 *
 * @see /docs/documentation/account-contract-fill.html#account-documents-family-link
 */
final class AccountDocumentsFamilyContextFeatureTest extends CrmTestCase
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

    public function test_guest_clicking_invitation_link_is_redirected_to_login_with_intended_query(): void
    {
        $contract = $this->makeContractFor($this->brother2, Contract::STATUS_AWAITING_CLIENT_FILL);
        $url = $this->invitationDocumentsUrl($contract, $this->brother2);

        Auth::logout();

        $this->get($url)->assertRedirect(route('login'));
        $intended = (string) session('url.intended');
        $this->assertStringContainsString('student=' . $this->brother2->id, $intended);
        $this->assertStringContainsString('fill=' . $contract->id, $intended);
    }

    public function test_parent_viewing_one_child_opens_invitation_link_for_the_other_child(): void
    {
        $own = $this->makeContractFor($this->brother1, Contract::STATUS_AWAITING_CLIENT_FILL);
        $sibling = $this->makeAwaitingFillContractFor($this->brother2);
        $url = $this->invitationDocumentsUrl($sibling, $this->brother2);

        $this->actingAsBrother1($this->brother1->id);

        $html = $this->get($url)
            ->assertOk()
            ->assertViewHas('openFillContractId', $sibling->id)
            ->assertViewHas('unsignedContractsCount', 1)
            ->getContent();

        $this->assertSame($this->brother2->id, session(FamilyStudentContextService::SESSION_KEY));
        $this->assertStringContainsString('data-id="' . $sibling->id . '"', $html);
        $this->assertStringNotContainsString('data-id="' . $own->id . '"', $html);
        $this->assertMatchesRegularExpression(
            '/<option value="' . $this->brother2->id . '"[^>]*\bselected\b/u',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<option value="' . $this->brother1->id . '"[^>]*\bselected\b/u',
            $html
        );
        $this->assertStringContainsString('loadContractFill(' . $sibling->id . ',', $html);
    }

    public function test_index_lists_active_child_contracts_not_siblings(): void
    {
        $own = $this->makeContractFor($this->brother1, Contract::STATUS_AWAITING_CLIENT_FILL);
        $sibling = $this->makeContractFor($this->brother2, Contract::STATUS_AWAITING_CLIENT_FILL);

        $this->actingAsBrother1();

        $this->get(route('account.documents.index'))
            ->assertOk()
            ->assertSee('data-id="' . $own->id . '"', false)
            ->assertDontSee('data-id="' . $sibling->id . '"', false);
    }

    public function test_switcher_changes_documents_list_to_selected_child(): void
    {
        $own = $this->makeContractFor($this->brother1, Contract::STATUS_DRAFT);
        $sibling = $this->makeContractFor($this->brother2, Contract::STATUS_DRAFT);

        $this->actingAsBrother1()
            ->post(route('cabinet.active-student.switch'), [
                'student_user_id' => $this->brother2->id,
            ])
            ->assertRedirect();

        $this->assertSame($this->brother2->id, session(FamilyStudentContextService::SESSION_KEY));

        $this->get(route('account.documents.index'))
            ->assertOk()
            ->assertSee('data-id="' . $sibling->id . '"', false)
            ->assertDontSee('data-id="' . $own->id . '"', false);
    }

    public function test_fill_query_without_student_does_not_switch_child(): void
    {
        $own = $this->makeContractFor($this->brother1, Contract::STATUS_AWAITING_CLIENT_FILL);
        $sibling = $this->makeContractFor($this->brother2, Contract::STATUS_AWAITING_CLIENT_FILL);

        $this->actingAsBrother1($this->brother1->id);

        $this->get(route('account.documents.index', ['fill' => $sibling->id]))
            ->assertOk()
            ->assertSee('data-id="' . $own->id . '"', false)
            ->assertDontSee('data-id="' . $sibling->id . '"', false);

        $this->assertSame($this->brother1->id, session(FamilyStudentContextService::SESSION_KEY));
    }

    public function test_generic_documents_url_from_pdf_does_not_switch_child(): void
    {
        $own = $this->makeContractFor($this->brother1, Contract::STATUS_DRAFT);
        $this->makeContractFor($this->brother2, Contract::STATUS_DRAFT);

        $this->actingAsBrother1($this->brother1->id);

        $this->get(url('/account-settings/documents'))
            ->assertOk()
            ->assertSee('data-id="' . $own->id . '"', false);

        $this->assertSame($this->brother1->id, session(FamilyStudentContextService::SESSION_KEY));
    }

    public function test_foreign_student_query_is_ignored(): void
    {
        $own = $this->makeContractFor($this->brother1, Contract::STATUS_DRAFT);

        $this->actingAsBrother1($this->brother1->id);

        $this->get(route('account.documents.index', [
            'student' => $this->foreignUser->id,
        ]))
            ->assertOk()
            ->assertSee('data-id="' . $own->id . '"', false);

        $this->assertSame($this->brother1->id, session(FamilyStudentContextService::SESSION_KEY));
    }

    public function test_disabled_sibling_student_query_is_ignored(): void
    {
        $own = $this->makeContractFor($this->brother1, Contract::STATUS_DRAFT);
        $this->brother2->update(['is_enabled' => false]);

        $this->actingAsBrother1($this->brother1->id);

        $this->get(route('account.documents.index', [
            'student' => $this->brother2->id,
        ]))
            ->assertOk()
            ->assertSee('data-id="' . $own->id . '"', false);

        $this->assertSame($this->brother1->id, session(FamilyStudentContextService::SESSION_KEY));
    }

    public function test_menu_without_query_keeps_current_session_child(): void
    {
        $own = $this->makeContractFor($this->brother1, Contract::STATUS_DRAFT);
        $sibling = $this->makeContractFor($this->brother2, Contract::STATUS_DRAFT);

        $this->actingAsBrother1($this->brother2->id);

        $this->get(route('account.documents.index'))
            ->assertOk()
            ->assertSee('data-id="' . $sibling->id . '"', false)
            ->assertDontSee('data-id="' . $own->id . '"', false);

        $this->assertSame($this->brother2->id, session(FamilyStudentContextService::SESSION_KEY));
    }

    public function test_counter_counts_only_active_child_not_whole_family(): void
    {
        $this->makeContractFor($this->brother1, Contract::STATUS_AWAITING_CLIENT_FILL);
        $this->makeContractFor($this->brother2, Contract::STATUS_SENT);
        $this->makeContractFor($this->brother2, Contract::STATUS_OPENED);

        $this->actingAsBrother1();

        $this->get(route('account.documents.index'))
            ->assertOk()
            ->assertViewHas('unsignedContractsCount', 1);

        $this->get($this->invitationDocumentsUrl(
            $this->makeContractFor($this->brother2, Contract::STATUS_AWAITING_CLIENT_FILL),
            $this->brother2
        ))
            ->assertOk()
            ->assertViewHas('unsignedContractsCount', 3);
    }

    public function test_unrelated_same_partner_user_gets_404_on_family_contract(): void
    {
        $contract = $this->makeAwaitingFillContractFor($this->brother2);
        $stranger = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->brother1->role_id,
            'parent_id'  => null,
            'is_enabled' => true,
        ]);

        $this->actingAs($stranger)
            ->withSession($this->familyDocumentsSession())
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->getJson(route('account.documents.fill', $contract))
            ->assertNotFound();
    }
}
