<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Trainers;

use App\Models\Team;
use App\Models\TrainerProfile;
use App\Models\User;
use App\Services\TeamTrainerSyncService;
use App\Services\TeamUserSyncService;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Общий сетап права groups.own: тренер видит только свои группы.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
abstract class TrainerOwnTeamsScopeTestCase extends CrmTestCase
{
    protected const LIST_ERROR = 'Выберите группу из списка.';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    /**
     * @return array{0: Team, 1: Team, 2: User, 3: User}
     */
    protected function seedTwoTeamsAndStudents(): array
    {
        $ownTeam = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'ScopeOwn_'.uniqid('', true),
        ]);
        $otherTeam = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'ScopeOther_'.uniqid('', true),
        ]);
        $ownStudent = $this->makeStudent('OwnStud_'.uniqid('', true), [(int) $ownTeam->id]);
        $otherStudent = $this->makeStudent('OthStud_'.uniqid('', true), [(int) $otherTeam->id]);

        return [$ownTeam, $otherTeam, $ownStudent, $otherStudent];
    }

    /**
     * @param  list<string>  $permissions
     * @return array{0: User, 1: TrainerProfile}
     */
    protected function makeRestrictedTrainer(array $permissions, ?Team $ownTeam = null): array
    {
        $trainer = $this->createUserWithRole('trainer', $this->partner, [
            'name' => 'Scope',
            'lastname' => 'Actor_'.uniqid('', true),
            'is_enabled' => 1,
        ]);
        $profile = TrainerProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $trainer->id,
        ]);

        foreach ($permissions as $name) {
            $this->grantToRole((int) $trainer->role_id, $name);
        }

        if ($ownTeam !== null) {
            app(TeamTrainerSyncService::class)->attachTrainerToTeam($ownTeam, (int) $profile->id);
        }

        $this->actingAs($trainer);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        return [$trainer, $profile];
    }

    /**
     * @param  list<int>  $teamIds
     */
    protected function makeStudent(string $lastname, array $teamIds): User
    {
        $student = $this->createUserWithRole('user', $this->partner, [
            'name' => 'Kid',
            'lastname' => $lastname,
            'is_enabled' => 1,
        ]);
        app(TeamUserSyncService::class)->syncTeamsForStudent($student, $teamIds);

        return $student->refresh();
    }

    protected function grantToRole(int $roleId, string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $roleId,
            'permission_id' => $this->permissionId($permissionName),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return list<int>
     */
    protected function usersDataIds(): array
    {
        return collect($this->getJson('/admin/users/data?draw=1&start=0&length=100')->assertOk()->json('data'))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return list<int>
     */
    protected function teamsDataIds(): array
    {
        return collect($this->getJson('/admin/teams/data')->assertOk()->json('data'))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    protected function ajaxHeaders(): array
    {
        return [
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ];
    }

    protected function studentRoleId(): int
    {
        return $this->roleId('user');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function studentStorePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'New',
            'lastname' => 'St'.substr(uniqid(), -10),
            'role_id' => $this->studentRoleId(),
            'is_enabled' => 1,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function teamStorePayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'OwnNew_'.uniqid('', true),
            'default_duration_minutes' => 45,
            'order_by' => 10,
            'is_enabled' => 1,
        ], $overrides);
    }

    protected function revokeFromRole(int $roleId, string $permissionName): void
    {
        DB::table('permission_role')
            ->where('partner_id', $this->partner->id)
            ->where('role_id', $roleId)
            ->where('permission_id', $this->permissionId($permissionName))
            ->delete();
    }

    protected function assertFieldError(TestResponse $response, string $field, string $message = self::LIST_ERROR): void
    {
        $response->assertStatus(422)->assertJsonValidationErrors([$field]);
        $bag = $response->json('errors') ?? [];
        $this->assertArrayHasKey($field, $bag, 'errors['.$field.']');
        $errors = is_array($bag[$field]) ? $bag[$field] : [(string) $bag[$field]];
        $this->assertContains($message, $errors, 'errors['.$field.']');
    }

    protected function assertDeniedNotEmptyOk(TestResponse $response, string $context): void
    {
        $this->assertContains(
            $response->getStatusCode(),
            [302, 401, 403, 404, 405, 419, 422],
            $context.' → '.$response->getStatusCode()
        );
        $this->assertNotSame(500, $response->getStatusCode(), $context);
        $this->assertNotSame(200, $response->getStatusCode(), $context.' не должен быть пустым 200');
    }
}
