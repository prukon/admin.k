<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Dashboard;

use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\Crm\StudentTeams\StudentTeamPivotTestCase;

/**
 * Фикстуры селекта «ФИО» на /cabinet: активные ученики role=user.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
abstract class DashboardCabinetFioSelectTestCase extends StudentTeamPivotTestCase
{
    protected Team $team;

    protected User $crmAdmin;

    protected User $studentInTeam;

    protected User $studentWithoutTeam;

    protected User $disabledStudent;

    protected User $adminStaff;

    protected User $trainerStaff;

    protected User $customRoleStaff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config(['broadcasting.default' => 'null']);

        $this->team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Fio-Select-Group',
            'is_enabled' => true,
        ]);

        $this->crmAdmin = $this->createUserWithRole('admin', $this->partner, [
            'lastname' => 'FioAdmin',
            'name' => 'Actor',
            'is_enabled' => 1,
            'team_id' => null,
        ]);

        $this->studentInTeam = $this->makeStudentWithTeams([$this->team], [
            'lastname' => 'FioInTeam',
            'name' => 'Ivan',
            'is_enabled' => 1,
        ]);

        $this->studentWithoutTeam = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->studentRoleId(),
            'team_id' => null,
            'is_enabled' => 1,
            'lastname' => 'FioNoTeam',
            'name' => 'Petr',
        ]);

        $this->disabledStudent = $this->makeStudentWithTeams([$this->team], [
            'lastname' => 'FioDisabled',
            'name' => 'Oleg',
            'is_enabled' => 0,
        ]);

        $this->adminStaff = $this->createUserWithRole('admin', $this->partner, [
            'lastname' => 'FioStaffAdmin',
            'name' => 'Sergey',
            'is_enabled' => 1,
            'team_id' => null,
        ]);

        $this->trainerStaff = $this->createUserWithRole('trainer', $this->partner, [
            'lastname' => 'FioStaffTrainer',
            'name' => 'Oleg',
            'is_enabled' => 1,
            'team_id' => null,
        ]);
        DB::table('team_user')->insert([
            'partner_id' => $this->partner->id,
            'team_id' => $this->team->id,
            'user_id' => $this->trainerStaff->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->customRoleStaff = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->makeCustomRole()->id,
            'is_enabled' => 1,
            'team_id' => null,
            'lastname' => 'FioCustom',
            'name' => 'Igor',
        ]);
    }

    protected function actingAsCrmAdmin(): self
    {
        $this->actingAs($this->crmAdmin);
        $this->withSession(['current_partner' => $this->partner->id]);

        return $this;
    }

    /**
     * @return array<string, string>
     */
    protected function ajaxHeaders(): array
    {
        return [
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
            'HTTP_ACCEPT' => 'application/json',
        ];
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}>
     */
    protected function fioSelectEndpointsPayload(): array
    {
        return [
            [
                'method' => 'GET',
                'url' => route('dashboard'),
                'headers' => ['HTTP_ACCEPT' => 'text/html'],
            ],
            [
                'method' => 'POST',
                'url' => route('dashboard'),
                'headers' => ['HTTP_ACCEPT' => 'text/html'],
            ],
            [
                'method' => 'GET',
                'url' => route('getUserDetails', ['userId' => $this->studentInTeam->id]),
            ],
            [
                'method' => 'GET',
                'url' => route('getTeamDetails', [
                    'teamId' => $this->team->id,
                    'teamName' => $this->team->title,
                ]),
            ],
            [
                'method' => 'GET',
                'url' => route('getTeamDetails', ['teamName' => 'all']),
            ],
            [
                'method' => 'GET',
                'url' => route('getTeamDetails', ['teamName' => 'withoutTeam']),
            ],
        ];
    }

    protected function makeCustomRole(): Role
    {
        return Role::query()->create([
            'name' => 'fio_staff_'.Str::lower(Str::random(8)),
            'label' => 'Сотрудник ФИО',
            'is_sistem' => 0,
            'is_visible' => 1,
            'order_by' => 80,
        ]);
    }

    /**
     * @param  list<mixed>|null  $rows
     * @return list<int>
     */
    protected function idsFromPayload(?array $rows): array
    {
        return collect($rows ?? [])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * То, что JS кладёт в #single-select-user при выбранной группе:
     * userWithoutTeam.concat(usersTeam).
     *
     * @param  array<string, mixed>  $json
     * @return list<int>
     */
    protected function jsConcatSelectIds(array $json): array
    {
        return collect($json['userWithoutTeam'] ?? [])
            ->concat($json['usersTeam'] ?? [])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  list<int>  $ids
     */
    protected function assertStaffAndDisabledNotInIds(array $ids): void
    {
        $this->assertNotContains((int) $this->adminStaff->id, $ids);
        $this->assertNotContains((int) $this->trainerStaff->id, $ids);
        $this->assertNotContains((int) $this->customRoleStaff->id, $ids);
        $this->assertNotContains((int) $this->disabledStudent->id, $ids);
        $this->assertNotContains((int) $this->crmAdmin->id, $ids);
    }

    /**
     * @param  array<string, mixed>  $json
     */
    protected function assertTeamDetailsPayloadOnlyEnabledStudents(array $json): void
    {
        $this->assertTrue((bool) ($json['success'] ?? false));
        $studentRoleId = $this->studentRoleId();

        foreach (['usersTeam', 'userWithoutTeam'] as $key) {
            $this->assertIsArray($json[$key] ?? null, $key);
            foreach ($json[$key] as $row) {
                $this->assertIsArray($row);
                $this->assertSame(
                    (int) $this->partner->id,
                    (int) $row['partner_id'],
                    $key.' только текущий партнёр'
                );
                $this->assertSame(1, (int) $row['is_enabled'], $key.' только is_enabled=1');
                $this->assertSame($studentRoleId, (int) $row['role_id'], $key.' только role=user');
            }
        }
    }
}
