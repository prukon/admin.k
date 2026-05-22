<?php

namespace Tests\Feature\Crm\Schedule;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Tests\Feature\Crm\CrmTestCase;

final class ScheduleJournalAccessFeatureTest extends ScheduleJournalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpScheduleJournal();
    }

    public function test_guest_cannot_access_schedule_journal(): void
    {
        Auth::logout();

        $this->get(route('schedule.index'))->assertStatus(302);
        $this->getJson(route('schedule.cell-context', ['user_id' => 1, 'date' => '2026-05-01']))->assertStatus(401);
        $this->postJson(route('schedule.update'), [])->assertStatus(401);
        $this->getJson(route('statuses.index'))->assertStatus(401);
    }

    public function test_schedule_journal_forbidden_without_schedule_view_permission(): void
    {
        $actor = $this->createUserWithoutPermission('schedule.view', $this->partner);
        [$student] = $this->makeStudentTeamAndTrainer();

        $this->actingAs($actor)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true])
            ->get(route('schedule.index'))
            ->assertStatus(403);

        $session = ['current_partner' => $this->partner->id, '2fa:passed' => true];

        $this->actingAs($actor)->withSession($session)
            ->getJson(route('schedule.cell-context', ['user_id' => $student->id, 'date' => '2026-05-01']))
            ->assertStatus(403);

        $this->actingAs($actor)->withSession($session)
            ->postJson(route('schedule.update'), [
                'user_id' => $student->id,
                'date' => '2026-05-01',
                'status_id' => $this->visitedStatusId,
            ])
            ->assertStatus(403);

        $this->actingAs($actor)->withSession($session)
            ->getJson(route('logs.data.schedule', ['draw' => 1]))
            ->assertStatus(403);

        $this->actingAs($actor)->withSession($session)
            ->getJson(route('user.schedule.info', $student))
            ->assertStatus(403);

        $this->actingAs($actor)->withSession($session)
            ->postJson(route('user.set.group', $student), ['team_id' => null])
            ->assertStatus(403);

        $this->actingAs($actor)->withSession($session)
            ->postJson(route('user.update.schedule', $student), [
                'weekdays' => [1],
                'date_from' => '2026-05-01',
                'date_to' => '2026-05-01',
            ])
            ->assertStatus(403);

        $this->actingAs($actor)->withSession($session)
            ->getJson(route('statuses.index'))
            ->assertStatus(403);

        $this->actingAs($actor)->withSession($session)
            ->postJson(route('statuses.store'), ['name' => 'X', 'sort_order' => 99])
            ->assertStatus(403);
    }

    public function test_schedule_page_and_all_endpoints_return_ok_with_schedule_view(): void
    {
        $this->grantScheduleView();
        [$student, $team] = $this->makeStudentTeamAndTrainer();
        $date = '2026-05-15';

        $this->get(route('schedule.index'))
            ->assertOk()
            ->assertSee('id="cellEditModal"', false)
            ->assertSee('id="cell-trainer-wrap"', false)
            ->assertSee('id="cell-trainer-profile-id"', false)
            ->assertSee('SCHEDULE_VISITED_STATUS_ID', false);

        $this->getJson(route('schedule.cell-context', [
            'user_id' => $student->id,
            'date' => $date,
        ]))
            ->assertOk()
            ->assertJsonStructure([
                'visited_status_id',
                'team_id',
                'team_default_trainer_profile_id',
                'trainer_profile_id_for_select',
                'trainers',
            ]);

        $this->postJson(route('schedule.update'), [
            'user_id' => $student->id,
            'date' => $date,
            'status_id' => $this->visitedStatusId,
            'description' => 'Комментарий',
            'trainer_profile_id' => '',
        ])->assertOk()->assertJson(['success' => true]);

        $this->getJson(route('logs.data.schedule', ['draw' => 1]))->assertOk();

        $this->getJson(route('user.schedule.info', $student))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('user.id', $student->id);

        $this->postJson(route('user.set.group', $student), [
            'team_id' => $team->id,
        ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $weekday = (int) now()->isoWeekday();
        $today = now()->toDateString();

        $this->postJson(route('user.update.schedule', $student), [
            'weekdays' => [$weekday],
            'date_from' => $today,
            'date_to' => $today,
        ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->getJson(route('statuses.index'))
            ->assertOk()
            ->assertJsonStructure(['statuses']);

        $create = $this->postJson(route('statuses.store'), [
            'name' => 'Статус доступа',
            'icon' => 'fas fa-circle',
            'color' => '#abcdef',
            'sort_order' => 77,
        ])->assertOk()->assertJson(['success' => true]);

        $statusId = (int) $create->json('status.id');
        $this->assertGreaterThan(0, $statusId);

        $this->patchJson(route('statuses.update', $statusId), [
            'name' => 'Статус доступа (изм.)',
            'icon' => 'fas fa-circle',
            'color' => '#112233',
            'sort_order' => 78,
        ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->deleteJson(route('statuses.destroy', $statusId))
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_schedule_page_shows_visited_status_radio_marker(): void
    {
        $this->grantScheduleView();

        $this->get(route('schedule.index'))
            ->assertOk()
            ->assertSee('data-is-visited="1"', false)
            ->assertSee('id="status-' . $this->visitedStatusId . '"', false);
    }
}
