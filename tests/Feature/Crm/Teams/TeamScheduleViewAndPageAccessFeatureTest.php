<?php

namespace Tests\Feature\Crm\Teams;

use App\Models\Team;
use App\Models\User;
use App\Models\Weekday;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Права schedule.view: колонка/модалки «Расписание» на странице групп и защита обновления weekdays.
 * Доступ к странице /admin/teams — middleware can:groups.view (пользователь admin в CrmTestCase имеет groups.view).
 */
class TeamScheduleViewAndPageAccessFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        session(['current_partner' => $this->partner->id]);
        $this->asAdmin();
    }

    private function grantScheduleViewForAdminOnCurrentPartner(): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $this->roleId('admin'),
            'permission_id' => $this->permissionId('schedule.view'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    public function test_teams_page_and_related_endpoints_return_200_for_user_with_groups_view(): void
    {
        $this->get('/admin/teams')->assertOk();

        $this->get('/admin/teams/data')->assertOk();

        $this->getJson('/admin/teams/columns-settings')->assertOk();

        $this->postJson('/admin/teams/columns-settings', [
            'columns' => [
                'order_by'     => true,
                'title'        => true,
                'status_label' => true,
                'actions'      => true,
            ],
        ])->assertOk()->assertJson(['success' => true]);

        $this->get(route('logs.data.team'))->assertOk();

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Endpoint smoke group',
        ]);

        $this->getJson("/admin/team/{$team->id}/edit")->assertOk();

        $this->patchJson("/admin/team/{$team->id}", [
            'title'                    => 'Endpoint smoke group updated',
            'default_duration_minutes' => 60,
            'order_by'                 => $team->order_by,
            'is_enabled'               => (int) $team->is_enabled,
            'weekdays'                 => [],
        ])->assertOk();

        $this->deleteJson("/admin/team/{$team->id}")->assertOk();
    }

    public function test_teams_index_shows_schedule_table_header_when_schedule_view_granted(): void
    {
        $this->grantScheduleViewForAdminOnCurrentPartner();

        $this->get('/admin/teams')
            ->assertOk()
            ->assertSee('<th>Расписание</th>', false);
    }

    public function test_teams_index_hides_schedule_table_header_without_schedule_view(): void
    {
        $this->get('/admin/teams')
            ->assertOk()
            ->assertDontSee('<th>Расписание</th>', false);
    }

    public function test_teams_index_shows_trainer_table_header_when_trainers_view_granted(): void
    {
        $this->get('/admin/teams')
            ->assertOk()
            ->assertSee('<th>Тренер</th>', false);
    }

    public function test_teams_index_hides_trainer_table_header_without_trainers_view(): void
    {
        $actor = $this->createUserWithoutPermission('trainers.view', $this->partner);
        $this->grantGroupsViewForUser($actor);
        $this->actingAs($actor);

        $this->get('/admin/teams')
            ->assertOk()
            ->assertDontSee('<th>Тренер</th>', false)
            ->assertDontSee('data-column-key="trainer_label"', false);
    }

    private function grantGroupsViewForUser(User $user): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $user->role_id,
            'permission_id' => $this->permissionId('groups.view'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    public function test_teams_index_hides_create_modal_schedule_block_without_schedule_view(): void
    {
        $this->get('/admin/teams')
            ->assertOk()
            ->assertDontSee('id="weekdays"', false);
    }

    public function test_teams_index_shows_create_modal_schedule_block_when_schedule_view_granted(): void
    {
        $this->grantScheduleViewForAdminOnCurrentPartner();

        $this->get('/admin/teams')
            ->assertOk()
            ->assertSee('id="weekdays"', false);
    }

    public function test_update_without_schedule_view_does_not_change_weekdays_even_when_sent(): void
    {
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Weekday lock',
        ]);

        $originalWeekdayIds = Weekday::take(2)->pluck('id')->all();
        $team->weekdays()->sync($originalWeekdayIds);

        $otherWeekdayIds = Weekday::skip(3)->take(2)->pluck('id')->all();

        $this->patchJson("/admin/team/{$team->id}", [
            'title'                    => 'Weekday lock renamed',
            'default_duration_minutes' => 60,
            'order_by'                 => $team->order_by,
            'is_enabled'               => (int) $team->is_enabled,
            'weekdays'                 => $otherWeekdayIds,
        ])->assertOk();

        $this->assertEqualsCanonicalizing(
            $originalWeekdayIds,
            $team->fresh()->weekdays->pluck('id')->all()
        );
        $this->assertSame('Weekday lock renamed', $team->fresh()->title);
    }

    public function test_update_with_schedule_view_changes_weekdays(): void
    {
        $this->grantScheduleViewForAdminOnCurrentPartner();

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Weekday editable',
        ]);

        $oldIds = Weekday::take(2)->pluck('id')->all();
        $team->weekdays()->sync($oldIds);

        $newIds = Weekday::skip(2)->take(3)->pluck('id')->all();

        $this->patchJson("/admin/team/{$team->id}", [
            'title'                    => $team->title,
            'default_duration_minutes' => 60,
            'order_by'                 => $team->order_by,
            'is_enabled'               => (int) $team->is_enabled,
            'weekdays'                 => $newIds,
        ])->assertOk();

        $this->assertEqualsCanonicalizing(
            $newIds,
            $team->fresh()->weekdays->pluck('id')->all()
        );
    }

    public function test_store_without_weekdays_succeeds_when_schedule_view_denied(): void
    {
        $this->postJson('/admin/teams', [
            'title'                    => 'No weekdays in form',
            'default_duration_minutes' => 60,
            'order_by'                 => 10,
            'is_enabled'               => 1,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk();

        $team = Team::where('title', 'No weekdays in form')->firstOrFail();
        $this->assertCount(0, $team->weekdays);
    }

    public function test_data_endpoint_still_returns_weekdays_label_in_json_without_schedule_view(): void
    {
        $weekdays = Weekday::take(2)->pluck('id', 'title');

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'API still has label',
        ]);
        $team->weekdays()->sync($weekdays->values()->all());

        $json = $this->get('/admin/teams/data')->assertOk()->json();

        $row = collect($json['data'])->firstWhere('id', $team->id);
        $this->assertNotNull($row);
        $this->assertArrayHasKey('weekdays_label', $row);
        foreach ($weekdays->keys()->all() as $titlePart) {
            $this->assertStringContainsString((string) $titlePart, (string) $row['weekdays_label']);
        }
    }

    public function test_data_ordering_by_status_column_name_returns_200_without_schedule_column_layout(): void
    {
        Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Order test A',
            'is_enabled' => 1,
        ]);

        $query = http_build_query([
            'order'   => [
                ['column' => 3, 'dir' => 'desc'],
            ],
            'columns' => [
                ['name' => 'rownum'],
                ['name' => 'order_by'],
                ['name' => 'title'],
                ['name' => 'status_label'],
                ['name' => 'actions'],
            ],
            'draw'   => 1,
            'start'  => 0,
            'length' => 10,
        ]);

        $response = $this->get('/admin/teams/data?' . $query);
        $response->assertOk();
        $json = $response->json();
        $this->assertArrayHasKey('data', $json);
        $this->assertArrayHasKey('recordsTotal', $json);
    }

    public function test_guest_cannot_access_teams_page(): void
    {
        Auth::logout();

        $response = $this->get('/admin/teams');
        $this->assertContains($response->getStatusCode(), [302, 401, 403]);
    }

    public function test_guest_cannot_access_teams_data_and_columns_settings(): void
    {
        Auth::logout();

        $this->assertContains($this->get('/admin/teams/data')->getStatusCode(), [302, 401, 403]);
        $this->assertContains($this->getJson('/admin/teams/columns-settings')->getStatusCode(), [302, 401, 403]);
    }
}
