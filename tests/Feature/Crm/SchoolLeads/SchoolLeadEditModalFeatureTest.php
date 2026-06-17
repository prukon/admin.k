<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SchoolLeads;

use App\Models\Role;
use App\Models\SchoolLead;
use App\Models\Team;
use App\Models\User;
use App\Services\PartnerWidgetService;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Объединённая модалка «Редактирование лида»: разметка, расширенное обновление, read-only после конвертации.
 */
final class SchoolLeadEditModalFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->asAdmin();
        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);
    }

    public function test_leads_page_renders_unified_edit_lead_modal(): void
    {
        $this->get(route('admin.school-leads'))
            ->assertOk()
            ->assertSee('id="editLeadModal"', false)
            ->assertSee('Редактирование лида', false)
            ->assertSee('id="saveLeadBtn"', false)
            ->assertSee('id="createClientBtn"', false)
            ->assertSee('id="editLeadAccordion"', false)
            ->assertSee('editLeadAccordionStudent', false)
            ->assertSee('editLeadAccordionParent', false)
            ->assertSee('id="leadModalStatusPicker"', false)
            ->assertSee('id="leadClientCreatedBadge"', false)
            ->assertSee('На основании лида был создан клиент.', false)
            ->assertSee('id="leadCreateContractBtn"', false)
            ->assertSee('setLeadModalClientInfo', false)
            ->assertSee('openCreateContractFromLead', false)
            ->assertSee('id="createContractModal"', false)
            ->assertSee('KidsCrmContractCreate.openModal', false)
            ->assertSee('js-lead-health-checkbox', false)
            ->assertDontSee('editLeadAccordionGeneral', false)
            ->assertSee('id="leadChildFirstname"', false)
            ->assertSee('id="leadChildLastname"', false)
            ->assertSee('id="leadChildMiddlename"', false)
            ->assertSee('id="leadNeedsContactHelpBadge"', false)
            ->assertSee('Просит помочь с выбором секции', false)
            ->assertDontSee('id="createUserModal"', false)
            ->assertDontSee('create-user-from-lead', false)
            ->assertDontSee('Редактирование заявки', false);
    }

    public function test_leads_page_hides_create_client_button_without_users_view(): void
    {
        $denied = $this->createUserWithoutPermission('users.view', $this->partner);

        \Illuminate\Support\Facades\DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $denied->role_id,
            'permission_id' => $this->permissionId('schoolLeads.view'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $this->actingAs($denied)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true])
            ->get(route('admin.school-leads'))
            ->assertOk()
            ->assertSee('id="editLeadModal"', false)
            ->assertDontSee('id="createClientBtn"', false);
    }

    public function test_datatable_includes_needs_contact_help_flag(): void
    {
        SchoolLead::create([
            'partner_id'         => $this->partner->id,
            'name'               => 'Нужна помощь',
            'phone'              => '+7 900 111-11-11',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'needs_contact_help' => true,
        ]);

        $row = $this->getJson(route('admin.school-leads.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 10,
        ]))->json('data.0');

        $this->assertTrue($row['needs_contact_help']);
    }

    public function test_update_persists_extended_lead_fields(): void
    {
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Группа А',
            'is_enabled' => true,
        ]);

        $lead = SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Старое имя',
            'phone'                 => '+7 900 000-00-01',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $this->putJson(route('admin.school-leads.update', ['schoolLead' => $lead->id]), [
            'comment'          => 'Новый комментарий',
            'parent_lastname'  => 'Иванова',
            'parent_firstname' => 'Мария',
            'parent_middlename'=> 'Петровна',
            'parent_phone'     => '+7 900 444-44-44',
            'parent_email'     => 'parent@example.com',
            'child_lastname'   => 'Иванов',
            'child_firstname'  => 'Пётр',
            'child_middlename' => 'Сергеевич',
            'child_birthday'   => '2018-05-10',
            'team_id'          => $team->id,
        ])->assertOk();

        $lead->refresh();

        $this->assertSame('Новый комментарий', $lead->comment);
        $this->assertSame('Иванова', $lead->parent_lastname);
        $this->assertSame('Мария', $lead->parent_firstname);
        $this->assertSame('Петровна', $lead->parent_middlename);
        $this->assertSame('parent@example.com', $lead->parent_email);
        $this->assertSame('Иванов', $lead->child_lastname);
        $this->assertSame('Пётр', $lead->child_firstname);
        $this->assertSame('Сергеевич', $lead->child_middlename);
        $this->assertSame('2018-05-10', $lead->child_birthday?->format('Y-m-d'));
        $this->assertSame($team->id, (int) $lead->team_id);
    }

    public function test_update_rejects_linked_lead(): void
    {
        $linkedUser = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => (int) Role::query()->where('name', 'user')->value('id'),
        ]);

        $lead = SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Связанный',
            'phone'                 => '+7 900 888-88-88',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'user_id'               => $linkedUser->id,
        ]);

        $this->putJson(route('admin.school-leads.update', ['schoolLead' => $lead->id]), [
            'comment' => 'Попытка изменить',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['school_lead']);
    }
}
