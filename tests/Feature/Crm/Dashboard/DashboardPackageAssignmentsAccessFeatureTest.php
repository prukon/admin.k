<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Dashboard;

use App\Models\LessonPackage;
use App\Models\Team;
use App\Models\User;
use App\Models\UserLessonPackage;
use Illuminate\Support\Facades\Auth;
use Tests\Feature\Crm\StudentTeams\StudentTeamPivotTestCase;

/**
 * Консоль (/cabinet): блок «Назначенные абонементы» и право setPrices.packageAssignments.view.
 *
 * @see DashboardLessonPackagesFeatureTest
 * @see resources/views/dashboard.blade.php
 */
final class DashboardPackageAssignmentsAccessFeatureTest extends StudentTeamPivotTestCase
{
    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config(['broadcasting.default' => 'null']);

        $this->team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Cabinet-PA-Team',
        ]);
    }

    private function createAssignment(User $student, float $feeAmount, string $packageName): UserLessonPackage
    {
        $package = LessonPackage::factory()->forPartner($this->partner->id)->create([
            'name' => $packageName,
        ]);

        $lessons = (int) $package->lessons_count;

        return UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $package->id,
            'team_id' => $this->team->id,
            'lessons_total' => $lessons,
            'lessons_remaining' => $lessons,
            'fee_amount' => number_format($feeAmount, 2, '.', ''),
            'is_paid' => false,
        ]);
    }

    public function test_guest_is_denied_on_cabinet(): void
    {
        Auth::logout();

        $response = $this->get(route('dashboard'));

        $this->assertContains($response->getStatusCode(), [302, 401, 403, 419]);
        $this->assertNotEquals(500, $response->getStatusCode());
        $this->assertNotEquals(200, $response->getStatusCode());
    }

    public function test_cabinet_hides_assigned_packages_without_package_assignments_permission(): void
    {
        $student = $this->makeStudentWithTeams([$this->team]);
        $this->createAssignment($student, 5_500, 'Скрытый на консоли');

        $this->actingAs($student);
        $this->withSession(['current_partner' => $this->partner->id]);

        $html = $this->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringNotContainsString('Назначенные абонементы', $html);
        $this->assertStringNotContainsString('Скрытый на консоли', $html);
    }

    public function test_cabinet_shows_assigned_packages_with_package_assignments_permission(): void
    {
        $student = $this->makeStudentWithTeams([$this->team]);
        $this->grantPermissionForUser($student, 'setPrices.packageAssignments.view');
        $ulp = $this->createAssignment($student, 6_600, 'Видимый на консоли');

        $this->actingAs($student);
        $this->withSession(['current_partner' => $this->partner->id]);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
        $this->assertNotSame('', trim((string) $response->getContent()));

        $response->assertSee('Назначенные абонементы', false)
            ->assertSee('Видимый на консоли', false)
            ->assertSee('name="payment_kind" value="lesson_package"', false)
            ->assertSee('name="user_lesson_package_id" value="'.$ulp->id.'"', false)
            ->assertSee('6600', false);
    }

    public function test_user_without_dashboard_view_gets_403_even_with_package_assignments(): void
    {
        $actor = $this->createUserWithoutPermission('dashboard.view', $this->partner);
        $this->grantPermissionForUser($actor, 'setPrices.packageAssignments.view');

        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->get(route('dashboard'))->assertForbidden();
    }
}
