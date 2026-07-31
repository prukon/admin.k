<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SchoolLeads;

use App\Models\Role;
use App\Models\SchoolLead;
use App\Models\User;
use App\Services\PartnerWidgetService;
use Tests\Feature\Crm\CrmTestCase;

/**
 * [P1] Non-AJAX safety-net: PUT лида и CRUD статусов без X-Requested-With → 302,
 * запись сохранена; AJAX → JSON. Не допускаем пустой 200.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 * @see \Tests\Feature\Crm\LessonPackages\LessonPackagesNonAjaxSafetyNetFeatureTest
 */
final class SchoolLeadUpdateAndStatusesNonAjaxSafetyNetFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->asAdmin();
        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);
    }

    public function test_update_lead_non_ajax_redirects_and_updates_status(): void
    {
        $lead = SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Non-ajax lead',
            'phone'                 => '+7 900 111-00-01',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'comment'               => 'old',
        ]);

        $status = $this->createPartnerSchoolLeadStatus(['name' => 'NonAjaxLeadStatus']);

        $response = $this->from(route('admin.school-leads'))
            ->put(route('admin.school-leads.update', ['schoolLead' => $lead->id]), [
                'school_lead_status_id' => $status->id,
                'comment'               => 'non-ajax comment',
            ]);

        $response->assertRedirect(route('admin.school-leads'));
        $this->assertNotSame(200, $response->getStatusCode());

        $lead->refresh();
        $this->assertSame($status->id, (int) $lead->school_lead_status_id);
        $this->assertSame('non-ajax comment', $lead->comment);
    }

    public function test_update_linked_lead_status_non_ajax_redirects_and_persists(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => (int) Role::query()->where('name', 'user')->value('id'),
        ]);

        $lead = SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Linked non-ajax',
            'phone'                 => '+7 900 111-00-02',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'user_id'               => $student->id,
            'comment'               => 'keep-me',
        ]);

        $status = $this->createPartnerSchoolLeadStatus(['name' => 'LinkedNonAjax']);

        $this->from(route('admin.school-leads'))
            ->put(route('admin.school-leads.update', ['schoolLead' => $lead->id]), [
                'school_lead_status_id' => $status->id,
            ])
            ->assertRedirect(route('admin.school-leads'));

        $lead->refresh();
        $this->assertSame($status->id, (int) $lead->school_lead_status_id);
        $this->assertSame('keep-me', $lead->comment);
    }

    public function test_update_linked_lead_non_ajax_validation_failure_redirects_with_errors_not_empty_200(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => (int) Role::query()->where('name', 'user')->value('id'),
        ]);

        $lead = SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Linked invalid non-ajax',
            'phone'                 => '+7 900 111-00-03',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'user_id'               => $student->id,
            'comment'               => 'untouched',
        ]);

        $this->from(route('admin.school-leads'))
            ->put(route('admin.school-leads.update', ['schoolLead' => $lead->id]), [
                'comment' => 'forbidden',
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['school_lead']);

        $this->assertSame('untouched', $lead->fresh()->comment);
    }

    public function test_update_lead_ajax_returns_json_message_and_entity_fields(): void
    {
        $lead = SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Ajax lead',
            'phone'                 => '+7 900 111-00-04',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $status = $this->createPartnerSchoolLeadStatus(['name' => 'AjaxLeadStatus']);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->putJson(route('admin.school-leads.update', ['schoolLead' => $lead->id]), [
                'school_lead_status_id' => $status->id,
                'comment'               => 'ajax comment',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Изменения сохранены.')
            ->assertJsonPath('school_lead_status_id', $status->id)
            ->assertJsonPath('comment', 'ajax comment');
    }

    public function test_status_store_non_ajax_redirects_and_creates_status(): void
    {
        $name = 'NonAjaxStatus ' . uniqid('', true);

        $response = $this->from(route('admin.school-leads'))
            ->post(route('admin.school-leads.statuses.store'), [
                'name'                 => $name,
                'color'                => '#6610f2',
                'sort_order'           => 55,
                'is_default_in_filter' => true,
            ]);

        $response->assertRedirect(route('admin.school-leads'));
        $this->assertNotSame(200, $response->getStatusCode());

        $this->assertDatabaseHas('school_lead_statuses', [
            'partner_id'           => $this->partner->id,
            'name'                 => $name,
            'is_default_in_filter' => 1,
            'is_system'            => 0,
        ]);
    }

    public function test_status_store_non_ajax_validation_failure_redirects_with_errors_not_empty_200(): void
    {
        $this->from(route('admin.school-leads'))
            ->post(route('admin.school-leads.statuses.store'), [
                'color' => '#0d6efd',
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['name']);

        $this->assertDatabaseMissing('school_lead_statuses', [
            'partner_id' => $this->partner->id,
            'name'       => '',
        ]);
    }

    public function test_status_store_ajax_returns_json_success_and_status_entity(): void
    {
        $name = 'AjaxStatus ' . uniqid('', true);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson(route('admin.school-leads.statuses.store'), [
                'name'                 => $name,
                'color'                => '#20c997',
                'sort_order'           => 60,
                'is_default_in_filter' => false,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status.name', $name)
            ->assertJsonStructure([
                'success',
                'message',
                'status' => ['id', 'name', 'color', 'is_system'],
            ]);
    }

    public function test_status_update_non_ajax_redirects_and_updates_status(): void
    {
        $status = $this->createPartnerSchoolLeadStatus([
            'name'  => 'Before non-ajax',
            'color' => '#0d6efd',
        ]);

        $this->from(route('admin.school-leads'))
            ->put(route('admin.school-leads.statuses.update', ['schoolLeadStatus' => $status->id]), [
                'name'                 => 'After non-ajax',
                'color'                => '#fd7e14',
                'sort_order'           => 70,
                'is_default_in_filter' => true,
            ])
            ->assertRedirect(route('admin.school-leads'));

        $status->refresh();
        $this->assertSame('After non-ajax', $status->name);
        $this->assertSame('#fd7e14', $status->color);
        $this->assertTrue((bool) $status->is_default_in_filter);
    }

    public function test_status_update_ajax_returns_json_success_and_status_entity(): void
    {
        $status = $this->createPartnerSchoolLeadStatus(['name' => 'Ajax update before']);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->putJson(route('admin.school-leads.statuses.update', ['schoolLeadStatus' => $status->id]), [
                'name'                 => 'Ajax update after',
                'color'                => '#dc3545',
                'sort_order'           => 80,
                'is_default_in_filter' => false,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status.name', 'Ajax update after')
            ->assertJsonPath('message', 'Статус обновлён.');
    }

    public function test_status_destroy_non_ajax_redirects_and_soft_deletes(): void
    {
        $status = $this->createPartnerSchoolLeadStatus(['name' => 'Delete non-ajax']);

        $this->from(route('admin.school-leads'))
            ->delete(route('admin.school-leads.statuses.destroy', ['schoolLeadStatus' => $status->id]))
            ->assertRedirect(route('admin.school-leads'));

        $this->assertSoftDeleted('school_lead_statuses', ['id' => $status->id]);
    }

    public function test_status_destroy_ajax_returns_json_success(): void
    {
        $status = $this->createPartnerSchoolLeadStatus(['name' => 'Delete ajax']);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->deleteJson(route('admin.school-leads.statuses.destroy', ['schoolLeadStatus' => $status->id]))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Статус удалён.');

        $this->assertSoftDeleted('school_lead_statuses', ['id' => $status->id]);
    }

    public function test_status_destroy_non_ajax_with_leads_redirects_with_errors_not_empty_200(): void
    {
        $status = $this->createPartnerSchoolLeadStatus(['name' => 'Busy non-ajax']);

        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Holds status',
            'phone'                 => '+7 900 111-00-05',
            'school_lead_status_id' => $status->id,
        ]);

        $this->from(route('admin.school-leads'))
            ->delete(route('admin.school-leads.statuses.destroy', ['schoolLeadStatus' => $status->id]))
            ->assertStatus(302)
            ->assertSessionHasErrors(['status']);

        $this->assertNotSoftDeleted('school_lead_statuses', ['id' => $status->id]);
    }
}
