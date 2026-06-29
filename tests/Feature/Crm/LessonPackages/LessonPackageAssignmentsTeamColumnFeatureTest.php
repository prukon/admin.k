<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\LessonPackage;
use App\Models\Team;
use App\Models\UserLessonPackage;
use App\Services\TeamUserSyncService;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

final class LessonPackageAssignmentsTeamColumnFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $permId = $this->permissionId('lessonPackages.view');
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
            'permission_id' => $permId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->user);
    }

    public function test_assignments_data_includes_team_label(): void
    {
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Группа Абонемент',
        ]);
        app(TeamUserSyncService::class)->attachTeamForStudent($this->user, (int) $team->id);

        $package = LessonPackage::factory()->create([
            'partner_id' => $this->partner->id,
            'schedule_type' => 'no_schedule',
        ]);

        UserLessonPackage::query()->create([
            'user_id' => $this->user->id,
            'team_id' => $team->id,
            'lesson_package_id' => $package->id,
            'lessons_total' => 1,
            'lessons_remaining' => 1,
            'fee_amount' => '1000.00',
            'is_paid' => false,
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson(route('admin.lesson-packages.assignments.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]));

        $response->assertOk();
        $row = collect($response->json('data'))->firstWhere('team_label', 'Группа Абонемент');
        $this->assertNotNull($row);
        $this->assertSame('Группа Абонемент', $row['team_label']);
    }

    public function test_assignments_index_renders_team_column_header(): void
    {
        $this->get(route('admin.lesson-packages.assignments'))
            ->assertOk()
            ->assertSee('Группа', false);
    }
}
