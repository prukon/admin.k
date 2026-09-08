<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SchoolLeads;

use App\Enums\SchoolLeadParentMatchConfirmation;
use App\Enums\SchoolLeadParentMatchReason;
use App\Models\ParentProfile;
use App\Models\Role;
use App\Models\SchoolLead;
use App\Models\User;
use App\Services\PartnerWidgetService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\Ui\Concerns\SuccessToastInsteadOfModalTestHelpers;

/**
 * Создание клиента из заявки: занятый email «нового родителя», затем выбор из справочника;
 * ошибки модалки — общий #kidsMainToast.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
abstract class SchoolLeadCreateClientDirectorySwitchTestCase extends CrmTestCase
{
    use SuccessToastInsteadOfModalTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);

        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);
    }

    protected function studentRoleId(): int
    {
        return (int) Role::query()->where('name', 'user')->value('id');
    }

    protected function trainerRoleId(): int
    {
        return (int) Role::query()->where('name', 'trainer')->value('id');
    }

    protected function grantPermission(User $actor, string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $actor->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    protected function actingAsLeadsAndUsersViewer(): User
    {
        $this->asAdmin();
        $this->grantPermission($this->user, 'schoolLeads.view');
        $this->grantPermission($this->user, 'users.view');

        return $this->user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeLead(array $overrides = []): SchoolLead
    {
        return SchoolLead::factory()->forPartner((int) $this->partner->id)->create(array_merge([
            'name'                  => 'Лид справочник',
            'phone'                 => '+7 900 200-10-10',
            'parent_lastname'       => 'Заявочный',
            'parent_firstname'      => 'Родитель',
            'parent_email'          => 'lead-snapshot-'.uniqid('', true).'@example.test',
            'parent_phone'         => '+7 900 200-10-11',
            'child_lastname'        => 'Учеников',
            'child_firstname'       => 'Пётр',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeDirectoryParent(array $overrides = []): ParentProfile
    {
        return ParentProfile::factory()->create(array_merge([
            'partner_id' => $this->partner->id,
            'lastname'   => 'Справочников',
            'firstname'  => 'Алексей',
            'email'      => 'dir-parent-'.uniqid('', true).'@example.test',
            'phone'      => '79991112233',
        ], $overrides));
    }

    protected function makeOccupiedStudentLogin(string $email, ?ParentProfile $parent = null): User
    {
        $parent ??= ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'email'      => $email,
        ]);

        return User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->studentRoleId(),
            'email'      => $email,
            'parent_id'  => $parent->id,
            'name'       => 'Чужой',
            'lastname'   => 'Логин',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function createClientPayload(SchoolLead $lead, array $overrides = []): array
    {
        return array_merge([
            'name'             => $lead->child_firstname ?: 'Пётр',
            'lastname'         => $lead->child_lastname ?: 'Учеников',
            'role_id'          => $this->studentRoleId(),
            'is_enabled'       => 1,
            'school_lead_id'   => $lead->id,
            'parent_email'     => $lead->parent_email,
            'parent_lastname'  => $lead->parent_lastname,
            'parent_firstname' => $lead->parent_firstname,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function leadPutPayload(SchoolLead $lead, array $overrides = []): array
    {
        return array_merge([
            'school_lead_status_id' => $lead->school_lead_status_id,
            'comment'                => $lead->comment,
            'parent_lastname'       => $lead->parent_lastname,
            'parent_firstname'        => $lead->parent_firstname,
            'parent_middlename'      => $lead->parent_middlename,
            'parent_phone'           => $lead->parent_phone,
            'parent_email'           => $lead->parent_email,
            'child_lastname'         => $lead->child_lastname,
            'child_firstname'         => $lead->child_firstname,
            'child_middlename'       => $lead->child_middlename,
        ], $overrides);
    }

    protected function attachMatch(SchoolLead $lead, ParentProfile $parent): void
    {
        $lead->update([
            'parent_id'              => $parent->id,
            'parent_match_reason'    => SchoolLeadParentMatchReason::Email,
            'parent_match_count'     => 1,
            'parent_match_confirmed' => SchoolLeadParentMatchConfirmation::Rejected,
        ]);
    }
}
