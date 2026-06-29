<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\LessonPackage;
use App\Models\PartnerLegalEntity;
use App\Models\Team;
use App\Models\User;
use App\Models\UserLessonPackage;
use App\Services\TeamUserSyncService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Назначения абонементов: team_id, teams-for-user, multi legal entity, контроль доступа.
 */
final class LessonPackageAssignmentsTeamAccessFeatureTest extends CrmTestCase
{
    private LessonPackage $package;

    private Team $team;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Группа назначения',
        ]);

        $this->student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);

        app(TeamUserSyncService::class)->attachTeamForStudent($this->student, (int) $this->team->id);

        $this->package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Пакет team access',
            'schedule_type' => 'no_schedule',
            'duration_days' => 30,
            'lessons_count' => 4,
            'price_cents' => 5000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);
    }

    private function grantLessonPackagesView(User $actor): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $actor->role_id,
            'permission_id' => $this->permissionId('lessonPackages.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedMultiLegalEntityMode(): void
    {
        PartnerLegalEntity::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Юрлицо A',
            'organization_name' => 'Юрлицо A',
            'is_default' => true,
        ]);
        PartnerLegalEntity::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Юрлицо B',
            'organization_name' => 'Юрлицо B',
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function ajaxHeaders(): array
    {
        return ['X-Requested-With' => 'XMLHttpRequest'];
    }

    public function test_guest_cannot_access_teams_for_user_endpoint(): void
    {
        Auth::logout();

        $response = $this->getJson(route('admin.lesson-packages.assignments.teams-for-user', [
            'user_id' => $this->student->id,
        ]));

        $this->assertContains($response->getStatusCode(), [302, 401, 403, 419]);
        $this->assertNotSame(500, $response->getStatusCode());
    }

    public function test_teams_for_user_forbidden_without_lesson_packages_view(): void
    {
        $denied = $this->createUserWithoutPermission('lessonPackages.view', $this->partner);
        $this->actingAs($denied);

        $this->getJson(route('admin.lesson-packages.assignments.teams-for-user', [
            'user_id' => $this->student->id,
        ]), $this->ajaxHeaders())
            ->assertForbidden();
    }

    public function test_teams_for_user_returns_student_teams_contract(): void
    {
        $this->grantLessonPackagesView($this->user);
        $this->actingAs($this->user);

        $this->getJson(route('admin.lesson-packages.assignments.teams-for-user', [
            'user_id' => $this->student->id,
        ]))
            ->assertOk()
            ->assertJsonStructure(['results' => [['id', 'text']]])
            ->assertJsonPath('results.0.id', (int) $this->team->id)
            ->assertJsonPath('results.0.text', 'Группа назначения');
    }

    public function test_store_assignment_non_ajax_with_team_id_in_multi_entity_mode(): void
    {
        $this->seedMultiLegalEntityMode();
        $this->grantLessonPackagesView($this->user);
        $this->actingAs($this->user);

        $response = $this->post(route('admin.lesson-packages.assignments.store'), [
            '_token' => csrf_token(),
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'lesson_package_id' => $this->package->id,
            'fee_amount' => '555.00',
        ]);

        $response->assertStatus(302);
        $response->assertRedirect(route('admin.lesson-packages.assignments'));

        $this->assertDatabaseHas('user_lesson_packages', [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'lesson_package_id' => $this->package->id,
            'fee_amount' => '555.00',
        ]);
    }

    public function test_store_assignment_non_ajax_missing_team_id_in_multi_entity_redirects_with_errors(): void
    {
        $this->seedMultiLegalEntityMode();
        $this->grantLessonPackagesView($this->user);
        $this->actingAs($this->user);

        $response = $this->from(route('admin.lesson-packages.assignments'))
            ->post(route('admin.lesson-packages.assignments.store'), [
                '_token' => csrf_token(),
                'user_id' => $this->student->id,
                'lesson_package_id' => $this->package->id,
                'fee_amount' => '100.00',
            ]);

        $response->assertStatus(302);
        $response->assertRedirect(route('admin.lesson-packages.assignments'));
        $response->assertSessionHasErrors(['team_id']);

        $this->assertSame(0, UserLessonPackage::query()->count());
    }

    public function test_assignments_page_renders_team_select_in_multi_entity_mode(): void
    {
        $this->seedMultiLegalEntityMode();
        $this->grantLessonPackagesView($this->user);
        $this->actingAs($this->user);

        $this->get(route('admin.lesson-packages.assignments'))
            ->assertOk()
            ->assertViewHas('multiLegalEntityMode', true)
            ->assertSee('id="ulp_team_id"', false);
    }
}
