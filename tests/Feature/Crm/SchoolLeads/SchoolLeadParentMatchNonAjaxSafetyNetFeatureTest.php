<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SchoolLeads;

use App\Enums\SchoolLeadParentMatchConfirmation;
use App\Enums\SchoolLeadParentMatchReason;
use App\Models\ParentProfile;
use App\Models\Partner;
use App\Models\SchoolLead;
use App\Services\PartnerWidgetService;
use Tests\Feature\Crm\CrmTestCase;

/**
 * [P1] Non-AJAX safety-net + AJAX-контракт для PUT подтверждения автосопоставления родителя.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 * @see \Tests\Feature\Crm\SchoolLeads\SchoolLeadUpdateAndStatusesNonAjaxSafetyNetFeatureTest
 */
final class SchoolLeadParentMatchNonAjaxSafetyNetFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->asAdmin();
        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);
    }

    public function test_accept_parent_match_non_ajax_redirects_and_persists(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'email'      => 'nonajax-dir@example.test',
            'lastname'   => 'Справочников',
            'firstname'  => 'Родитель',
        ]);

        $lead = SchoolLead::factory()->forPartner((int) $this->partner->id)->create([
            'parent_lastname'        => 'Иванов',
            'parent_firstname'       => 'Иван',
            'parent_email'           => 'nonajax-lead@example.test',
            'parent_phone'           => '+7 999 111-22-33',
            'parent_id'              => $parent->id,
            'parent_match_reason'    => SchoolLeadParentMatchReason::Email,
            'parent_match_count'     => 1,
            'parent_match_confirmed' => null,
            'school_lead_status_id'  => $this->schoolLeadSystemStatusId(),
            'comment'                => 'keep-comment',
        ]);

        $response = $this->from(route('admin.school-leads'))
            ->put(route('admin.school-leads.update', ['schoolLead' => $lead->id]), [
                'school_lead_status_id'  => $lead->school_lead_status_id,
                'comment'                => 'keep-comment',
                'parent_id'              => $parent->id,
                'parent_match_confirmed' => SchoolLeadParentMatchConfirmation::Accepted->value,
                'parent_lastname'        => 'Иванов',
                'parent_firstname'       => 'Иван',
                'parent_email'           => 'nonajax-lead@example.test',
                'parent_phone'           => '+7 999 111-22-33',
            ]);

        $response->assertRedirect(route('admin.school-leads'));
        $this->assertNotSame(200, $response->getStatusCode());

        $lead->refresh();
        $this->assertSame(SchoolLeadParentMatchConfirmation::Accepted, $lead->parent_match_confirmed);
        $this->assertSame($parent->id, (int) $lead->parent_id);
        $this->assertSame('Иванов', $lead->parent_lastname);
        $this->assertSame('nonajax-lead@example.test', $lead->parent_email);
        $this->assertSame('keep-comment', $lead->comment);
        $this->assertFalse($lead->needsParentMatchDecision());
    }

    public function test_reject_parent_match_non_ajax_redirects_and_clears_parent_id(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'email'      => 'nonajax-reject@example.test',
        ]);

        $lead = SchoolLead::factory()->forPartner((int) $this->partner->id)->create([
            'parent_lastname'        => 'Петров',
            'parent_firstname'       => 'Пётр',
            'parent_email'           => 'nonajax-reject-lead@example.test',
            'parent_id'              => $parent->id,
            'parent_match_reason'    => SchoolLeadParentMatchReason::Name,
            'parent_match_count'     => 3,
            'parent_match_confirmed' => null,
            'school_lead_status_id'  => $this->schoolLeadSystemStatusId(),
        ]);

        $response = $this->from(route('admin.school-leads'))
            ->put(route('admin.school-leads.update', ['schoolLead' => $lead->id]), [
                'school_lead_status_id'  => $lead->school_lead_status_id,
                'parent_id'              => '',
                'parent_match_confirmed' => SchoolLeadParentMatchConfirmation::Rejected->value,
                'parent_lastname'        => 'Петров',
                'parent_firstname'       => 'Пётр',
                'parent_email'           => 'nonajax-reject-lead@example.test',
            ]);

        $response->assertRedirect(route('admin.school-leads'));
        $this->assertNotSame(200, $response->getStatusCode());

        $lead->refresh();
        $this->assertNull($lead->parent_id);
        $this->assertSame(SchoolLeadParentMatchConfirmation::Rejected, $lead->parent_match_confirmed);
        $this->assertSame(SchoolLeadParentMatchReason::Name, $lead->parent_match_reason);
        $this->assertSame(3, (int) $lead->parent_match_count);
        $this->assertSame('Петров', $lead->parent_lastname);
    }

    public function test_accept_parent_match_non_ajax_validation_failure_redirects_with_errors_not_empty_200(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'email'      => 'nonajax-valid@example.test',
        ]);

        $lead = SchoolLead::factory()->forPartner((int) $this->partner->id)->create([
            'parent_lastname'        => 'Иванов',
            'parent_firstname'       => 'Иван',
            'parent_email'           => 'lead@example.test',
            'parent_id'              => $parent->id,
            'parent_match_reason'    => SchoolLeadParentMatchReason::Email,
            'parent_match_count'     => 1,
            'parent_match_confirmed' => null,
            'school_lead_status_id'  => $this->schoolLeadSystemStatusId(),
        ]);

        $this->from(route('admin.school-leads'))
            ->put(route('admin.school-leads.update', ['schoolLead' => $lead->id]), [
                'school_lead_status_id'  => $lead->school_lead_status_id,
                'parent_id'              => $parent->id,
                'parent_match_confirmed' => 'invalid-value',
                'parent_lastname'        => 'Иванов',
                'parent_firstname'       => 'Иван',
                'parent_email'           => 'lead@example.test',
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['parent_match_confirmed']);

        $lead->refresh();
        $this->assertNull($lead->parent_match_confirmed);
        $this->assertSame($parent->id, (int) $lead->parent_id);
    }

    public function test_accept_parent_match_ajax_returns_json_message_and_match_fields(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname'   => 'Справочников',
            'firstname'  => 'Алекс',
            'email'      => 'ajax-dir@example.test',
            'phone'      => '79991234567',
        ]);

        $lead = SchoolLead::factory()->forPartner((int) $this->partner->id)->create([
            'parent_lastname'        => 'Иванов',
            'parent_firstname'       => 'Иван',
            'parent_email'           => 'ajax-lead@example.test',
            'parent_phone'           => '+7 999 111-22-33',
            'parent_id'              => $parent->id,
            'parent_match_reason'    => SchoolLeadParentMatchReason::Email,
            'parent_match_count'     => 1,
            'parent_match_confirmed' => null,
            'school_lead_status_id'  => $this->schoolLeadSystemStatusId(),
        ]);

        $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->putJson(route('admin.school-leads.update', ['schoolLead' => $lead->id]), [
                'school_lead_status_id'  => $lead->school_lead_status_id,
                'parent_id'              => $parent->id,
                'parent_match_confirmed' => SchoolLeadParentMatchConfirmation::Accepted->value,
                'parent_lastname'        => 'Иванов',
                'parent_firstname'       => 'Иван',
                'parent_email'           => 'ajax-lead@example.test',
                'parent_phone'           => '+7 999 111-22-33',
            ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Изменения сохранены.')
            ->assertJsonPath('parent_id', $parent->id)
            ->assertJsonPath('parent_match_reason', 'email')
            ->assertJsonPath('parent_match_count', 1)
            ->assertJsonPath('parent_match_confirmed', 'accepted')
            ->assertJsonPath('parent_match_needs_decision', false)
            ->assertJsonPath('matched_parent.id', $parent->id)
            ->assertJsonPath('matched_parent.lastname', 'Справочников')
            ->assertJsonPath('parent_lastname', 'Иванов')
            ->assertJsonPath('parent_email', 'ajax-lead@example.test');

        $this->assertNotSame('', trim((string) $response->getContent()));
    }

    public function test_reject_parent_match_ajax_returns_json_and_keeps_reason_meta(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'email'      => 'ajax-reject@example.test',
        ]);

        $lead = SchoolLead::factory()->forPartner((int) $this->partner->id)->create([
            'parent_lastname'        => 'Сидоров',
            'parent_firstname'       => 'Сидор',
            'parent_email'           => 'ajax-reject-lead@example.test',
            'parent_id'              => $parent->id,
            'parent_match_reason'    => SchoolLeadParentMatchReason::Phone,
            'parent_match_count'     => 1,
            'parent_match_confirmed' => null,
            'school_lead_status_id'  => $this->schoolLeadSystemStatusId(),
        ]);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->putJson(route('admin.school-leads.update', ['schoolLead' => $lead->id]), [
                'school_lead_status_id'  => $lead->school_lead_status_id,
                'parent_id'              => null,
                'parent_match_confirmed' => SchoolLeadParentMatchConfirmation::Rejected->value,
                'parent_lastname'        => 'Сидоров',
                'parent_firstname'       => 'Сидор',
                'parent_email'           => 'ajax-reject-lead@example.test',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Изменения сохранены.')
            ->assertJsonPath('parent_id', null)
            ->assertJsonPath('parent_match_confirmed', 'rejected')
            ->assertJsonPath('parent_match_reason', 'phone')
            ->assertJsonPath('parent_match_count', 1)
            ->assertJsonPath('parent_match_needs_decision', false)
            ->assertJsonPath('matched_parent', null);
    }

    public function test_ajax_update_rejects_foreign_partner_parent_id_with_422(): void
    {
        $foreignPartner = Partner::factory()->create();
        $foreignParent = ParentProfile::factory()->create([
            'partner_id' => $foreignPartner->id,
            'email'      => 'foreign@example.test',
        ]);

        $ownParent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'email'      => 'own@example.test',
        ]);

        $lead = SchoolLead::factory()->forPartner((int) $this->partner->id)->create([
            'parent_id'              => $ownParent->id,
            'parent_match_reason'    => SchoolLeadParentMatchReason::Email,
            'parent_match_count'     => 1,
            'parent_match_confirmed' => null,
            'parent_email'           => 'own-lead@example.test',
            'school_lead_status_id'  => $this->schoolLeadSystemStatusId(),
        ]);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->putJson(route('admin.school-leads.update', ['schoolLead' => $lead->id]), [
                'school_lead_status_id'  => $lead->school_lead_status_id,
                'parent_id'              => $foreignParent->id,
                'parent_match_confirmed' => SchoolLeadParentMatchConfirmation::Accepted->value,
                'parent_email'           => 'own-lead@example.test',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['parent_id']);

        $lead->refresh();
        $this->assertSame($ownParent->id, (int) $lead->parent_id);
        $this->assertNull($lead->parent_match_confirmed);
    }

    public function test_ajax_update_rejects_invalid_parent_match_confirmed_with_422(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
        ]);

        $lead = SchoolLead::factory()->forPartner((int) $this->partner->id)->create([
            'parent_id'              => $parent->id,
            'parent_match_reason'    => SchoolLeadParentMatchReason::Email,
            'parent_match_count'     => 1,
            'parent_match_confirmed' => null,
            'school_lead_status_id'  => $this->schoolLeadSystemStatusId(),
        ]);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->putJson(route('admin.school-leads.update', ['schoolLead' => $lead->id]), [
                'school_lead_status_id'  => $lead->school_lead_status_id,
                'parent_id'              => $parent->id,
                'parent_match_confirmed' => 'maybe',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['parent_match_confirmed']);
    }
}
