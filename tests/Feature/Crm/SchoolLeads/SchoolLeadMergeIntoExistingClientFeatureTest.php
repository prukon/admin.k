<?php

namespace Tests\Feature\Crm\SchoolLeads;

use App\Models\SchoolLead;
use App\Models\Team;
use App\Models\User;
use App\Services\PartnerWidgetService;
use App\Services\TeamUserSyncService;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Лид на уже существующего ученика (совпадение по фамилии и имени) должен
 * предлагать добавить группу существующему клиенту вместо создания дубля.
 */
final class SchoolLeadMergeIntoExistingClientFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->asAdmin();
        $this->grantPermission($this->user, 'schoolLeads.view');
        $this->grantPermission($this->user, 'users.view');
        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);
    }

    private function createExistingStudent(string $lastname, string $firstname, Team $team): User
    {
        $student = $this->createUserWithRole('user', $this->partner, [
            'lastname' => $lastname,
            'name'     => $firstname,
        ]);

        app(TeamUserSyncService::class)->attachTeamForStudent($student, (int) $team->id);

        return $student;
    }

    public function test_datatable_exposes_matched_client_with_different_group(): void
    {
        $footballTeam = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Футбол',
        ]);
        $chessTeam = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Шахматы',
        ]);

        $existingStudent = $this->createExistingStudent('Иванов', 'Иван', $footballTeam);

        $lead = SchoolLead::create([
            'partner_id'             => $this->partner->id,
            'name'                   => 'Иванов Иван',
            'phone'                  => '+7 900 111-22-33',
            'child_lastname'         => 'Иванов',
            'child_firstname'        => 'Иван',
            'team_id'                => $chessTeam->id,
            'school_lead_status_id'  => $this->schoolLeadSystemStatusId(),
        ]);

        $row = $this->getJson(route('admin.school-leads.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 10,
        ]))->json('data.0');

        $this->assertSame($lead->id, $row['id']);
        $this->assertNotNull($row['matched_client']);
        $this->assertSame($existingStudent->id, $row['matched_client']['id']);
        $this->assertContains('Футбол', $row['matched_client']['team_titles']);
    }

    public function test_datatable_hides_matched_client_without_users_view_permission(): void
    {
        $actor = $this->createUserWithoutPermission('users.view', $this->partner);
        $this->actingAs($actor);
        $this->grantPermission($actor, 'schoolLeads.view');

        $team = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Футбол']);
        $this->createExistingStudent('Петров', 'Пётр', $team);

        SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Петров Пётр',
            'phone'                 => '+7 900 222-33-44',
            'child_lastname'        => 'Петров',
            'child_firstname'       => 'Пётр',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $row = $this->getJson(route('admin.school-leads.data', [
            'draw'   => 1,
            'start'  => 0,
            'length' => 10,
        ]))->json('data.0');

        $this->assertNull($row['matched_client']);
    }

    public function test_attach_existing_client_adds_group_without_creating_new_user(): void
    {
        $footballTeam = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Футбол']);
        $chessTeam = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Шахматы']);

        $existingStudent = $this->createExistingStudent('Сидоров', 'Алексей', $footballTeam);

        $lead = SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Сидоров Алексей',
            'phone'                 => '+7 900 333-44-55',
            'child_lastname'        => 'Сидоров',
            'child_firstname'       => 'Алексей',
            'team_id'               => $chessTeam->id,
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $usersBefore = User::query()->where('partner_id', $this->partner->id)->count();

        $response = $this->postJson(
            route('admin.school-leads.attach-existing-client', ['schoolLead' => $lead->id]),
            ['user_id' => $existingStudent->id],
            ['X-Requested-With' => 'XMLHttpRequest']
        );

        $response->assertOk();

        $usersAfter = User::query()->where('partner_id', $this->partner->id)->count();
        $this->assertSame($usersBefore, $usersAfter, 'Не должен создаваться новый клиент.');

        $lead->refresh();
        $this->assertSame($existingStudent->id, (int) $lead->user_id);

        $teamTitles = app(TeamUserSyncService::class)->teamTitlesCollection($existingStudent->fresh());
        $this->assertTrue($teamTitles->contains('Футбол'));
        $this->assertTrue($teamTitles->contains('Шахматы'));
    }

    public function test_attach_existing_client_requires_users_view_permission(): void
    {
        $actor = $this->createUserWithoutPermission('users.view', $this->partner);
        $this->actingAs($actor);
        $this->grantPermission($actor, 'schoolLeads.view');

        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $existingStudent = $this->createExistingStudent('Кузнецов', 'Олег', $team);

        $lead = SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Кузнецов Олег',
            'phone'                 => '+7 900 444-55-66',
            'child_lastname'        => 'Кузнецов',
            'child_firstname'       => 'Олег',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $this->postJson(
            route('admin.school-leads.attach-existing-client', ['schoolLead' => $lead->id]),
            ['user_id' => $existingStudent->id]
        )->assertForbidden();

        $this->assertNull($lead->fresh()->user_id);
    }

    public function test_attach_existing_client_rejects_already_linked_lead(): void
    {
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $existingStudent = $this->createExistingStudent('Волков', 'Дмитрий', $team);
        $otherStudent = $this->createUserWithRole('user', $this->partner);

        $lead = SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Волков Дмитрий',
            'phone'                 => '+7 900 555-66-77',
            'child_lastname'        => 'Волков',
            'child_firstname'       => 'Дмитрий',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
            'user_id'               => $otherStudent->id,
        ]);

        $this->postJson(
            route('admin.school-leads.attach-existing-client', ['schoolLead' => $lead->id]),
            ['user_id' => $existingStudent->id],
            ['X-Requested-With' => 'XMLHttpRequest']
        )->assertStatus(422);

        $this->assertSame($otherStudent->id, (int) $lead->fresh()->user_id);
    }

    public function test_attach_existing_client_rejects_stale_matched_user_id(): void
    {
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $existingStudent = $this->createExistingStudent('Орлов', 'Никита', $team);
        $unrelatedUser = $this->createUserWithRole('user', $this->partner);

        $lead = SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Орлов Никита',
            'phone'                 => '+7 900 666-77-88',
            'child_lastname'        => 'Орлов',
            'child_firstname'       => 'Никита',
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $this->postJson(
            route('admin.school-leads.attach-existing-client', ['schoolLead' => $lead->id]),
            ['user_id' => $unrelatedUser->id],
            ['X-Requested-With' => 'XMLHttpRequest']
        )->assertStatus(409);

        $this->assertNull($lead->fresh()->user_id);
    }

    private function grantPermission(User $actor, string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $actor->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }
}
