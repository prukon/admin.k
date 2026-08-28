<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Schedule;

use App\Models\User;
use App\Models\UserTeamScheduleSlot;
use Illuminate\Support\Facades\Auth;

/**
 * Доступ к колонке «кол-во тренировок»: гость / без schedule.view / viewer / admin;
 * чужие методы не 500; AJAX GET журнала не пустой JSON.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 * @see ScheduleJournalConsumingCountColumnFeatureTest
 */
final class ScheduleJournalConsumingCountColumnFullAccessFeatureTest extends ScheduleJournalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpScheduleJournal();
    }

    public function test_guest_cannot_open_journal_consuming_column_page(): void
    {
        Auth::logout();

        $web = $this->get(route('schedule.index', ['year' => 2026, 'month' => '08', 'team' => 'all']));
        $this->assertNotSame(500, $web->getStatusCode());
        $this->assertNotSame(200, $web->getStatusCode());
        $web->assertStatus(302);

        $json = $this->getJson(route('schedule.index', ['year' => 2026, 'month' => '08', 'team' => 'all']));
        $this->assertNotSame(500, $json->getStatusCode());
        $json->assertStatus(401);

        $ajax = $this->withHeaders($this->ajaxHeaders())
            ->get(route('schedule.index', ['year' => 2026, 'month' => '08', 'team' => 'all']));
        $this->assertNotSame(500, $ajax->getStatusCode());
        $this->assertContains($ajax->getStatusCode(), [302, 401]);
    }

    public function test_guest_cannot_mutate_consuming_count_endpoints(): void
    {
        Auth::logout();
        [$student, $team] = $this->makeStudentWithTeam();
        $utss = $this->createTrialUtss($student, $team, '2026-08-03');

        $this->post(route('schedule.update'), [
            'user_id' => $student->id,
            'utss_id' => $utss->id,
            'occurrence_date' => '2026-08-03',
            'lesson_occurrence_status_id' => $this->visitedStatusId,
        ])->assertRedirect();

        $this->postJson(route('schedule.update'), [
            'user_id' => $student->id,
            'utss_id' => $utss->id,
            'occurrence_date' => '2026-08-03',
            'lesson_occurrence_status_id' => $this->visitedStatusId,
        ])->assertUnauthorized();

        $this->delete(route('schedule.occurrence.destroy', $utss), [
            'occurrence_date' => '2026-08-03',
        ])->assertRedirect();

        $this->deleteJson(route('schedule.occurrence.destroy', $utss), [
            'occurrence_date' => '2026-08-03',
        ])->assertUnauthorized();

        $this->assertDatabaseHas('user_team_schedule_slots', ['id' => $utss->id]);
    }

    public function test_manager_without_schedule_view_gets_403_on_journal_and_mutations(): void
    {
        $actor = $this->createUserWithoutPermission('schedule.view', $this->partner);
        $session = ['current_partner' => $this->partner->id, '2fa:passed' => true];
        [$student, $team] = $this->makeStudentWithTeam();
        $utss = $this->createTrialUtss($student, $team, '2026-08-03');

        $web = $this->actingAs($actor)->withSession($session)
            ->get(route('schedule.index', ['year' => 2026, 'month' => '08', 'team' => 'all']));
        $this->assertNotSame(500, $web->getStatusCode());
        $web->assertStatus(403);

        $json = $this->actingAs($actor)->withSession($session)
            ->getJson(route('schedule.index', ['year' => 2026, 'month' => '08', 'team' => 'all']));
        $this->assertNotSame(500, $json->getStatusCode());
        $json->assertStatus(403);

        $this->actingAs($actor)->withSession($session)
            ->postJson(route('schedule.update'), [
                'user_id' => $student->id,
                'utss_id' => $utss->id,
                'occurrence_date' => '2026-08-03',
                'lesson_occurrence_status_id' => $this->visitedStatusId,
            ])
            ->assertForbidden();

        $this->actingAs($actor)->withSession($session)
            ->deleteJson(route('schedule.occurrence.destroy', $utss), [
                'occurrence_date' => '2026-08-03',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('user_team_schedule_slots', ['id' => $utss->id]);
    }

    public function test_viewer_with_schedule_view_sees_consuming_count_column(): void
    {
        $actor = $this->createUserWithoutPermission('schedule.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->grantScheduleView($actor);
        [$student, $team] = $this->makeStudentWithTeam();
        $utss = $this->createTrialUtss($student, $team, '2026-08-03');
        $this->markUtssOccurrenceStatus($utss, (int) $this->visitedStatusId);

        $page = $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => 'all',
        ]));
        $page->assertOk();
        $html = (string) $page->getContent();
        $this->assertNotSame('', trim($html));
        $this->assertStringContainsString('fa-person-circle-check', $html);
        $this->assertStringContainsString('schedule-consuming-count', $html);
        $this->assertSame('1', $this->journalConsumingCellText($html, (int) $student->id));
    }

    public function test_admin_with_schedule_view_gets_non_empty_journal_with_column(): void
    {
        $this->grantScheduleView();
        $this->makeStudentWithTeam();

        $page = $this->get(route('schedule.index', ['year' => 2026, 'month' => '08']));
        $page->assertOk();
        $html = (string) $page->getContent();
        $this->assertNotSame('', trim($html));
        $page->assertSee('schedule-consuming-count', false)
            ->assertSee('fa-person-circle-check', false);
    }

    public function test_ajax_get_journal_returns_html_column_not_empty_json(): void
    {
        $this->grantScheduleView();
        [$student, $team] = $this->makeStudentWithTeam();
        $utss = $this->createTrialUtss($student, $team, '2026-08-03');
        $this->markUtssOccurrenceStatus($utss, (int) $this->visitedStatusId);

        $page = $this->withHeaders($this->ajaxHeaders())
            ->get(route('schedule.index', ['year' => 2026, 'month' => '08', 'team' => 'all']));
        $page->assertOk();
        $html = (string) $page->getContent();
        $this->assertNotSame('', trim($html));
        $this->assertStringContainsString('id="schedule-table"', $html);
        $this->assertStringContainsString('schedule-consuming-count', $html);
        $this->assertSame('1', $this->journalConsumingCellText($html, (int) $student->id));
        $this->assertStringNotContainsString('"success":true', $html);
    }

    public function test_viewer_update_and_destroy_return_consuming_count_json(): void
    {
        $this->grantScheduleView();
        [$student, $team] = $this->makeStudentWithTeam();
        $utss = $this->createTrialUtss($student, $team, '2026-08-03');

        $update = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.update'), [
                'user_id' => $student->id,
                'utss_id' => $utss->id,
                'occurrence_date' => '2026-08-03',
                'lesson_occurrence_status_id' => $this->visitedStatusId,
                'journal_team_filter' => 'all',
            ]);
        $update->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('result.consuming_count', 1);
        $this->assertIsInt($update->json('result.consuming_count'));

        $destroy = $this->withHeaders($this->ajaxHeaders())
            ->deleteJson(route('schedule.occurrence.destroy', $utss), [
                'occurrence_date' => '2026-08-03',
                'journal_team_filter' => 'all',
            ]);
        $destroy->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('result.consuming_count', 0);
        $this->assertDatabaseMissing('user_team_schedule_slots', ['id' => $utss->id]);
    }

    public function test_place_flexible_with_schedule_view_returns_consuming_count(): void
    {
        $this->grantScheduleView();
        [$student, $team] = $this->makeStudentWithTeam();
        $ulp = $this->makeMonthlyFlexibleAssignment($student, (int) $team->id, '2026-08-01', lessons: 2);

        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.abonement.place-flexible', $student), [
                'user_lesson_package_id' => $ulp->id,
                'team_id' => $team->id,
                'occurrence_date' => '2026-08-10',
                'lesson_occurrence_status_id' => $this->visitedStatusId,
                'journal_team_filter' => 'all',
            ]);
        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('result.consuming_count', 1);
    }

    public function test_place_trial_and_single_require_lesson_packages_view(): void
    {
        $actor = $this->createUserWithoutPermission('lessonPackages.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->grantScheduleView($actor);
        [$student, $team] = $this->makeStudentWithTeam();
        $template = $this->makeSingleLessonTemplate();

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(
                route('schedule.empty-cell.place-trial', $student),
                $this->placeTrialPayload((int) $team->id, '2026-08-10', [
                    'lesson_occurrence_status_id' => $this->visitedStatusId,
                    'journal_team_filter' => 'all',
                ])
            )
            ->assertForbidden();

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(
                route('schedule.empty-cell.place-single', $student),
                $this->placeSingleCreatePayload($template, (int) $team->id, '2026-08-11', 1000, [
                    'lesson_occurrence_status_id' => $this->visitedStatusId,
                    'journal_team_filter' => 'all',
                ])
            )
            ->assertForbidden();

        $this->assertSame(0, UserTeamScheduleSlot::query()->where('user_id', $student->id)->count());
    }

    public function test_place_trial_and_single_with_both_permissions_return_consuming_count(): void
    {
        $this->grantScheduleView();
        $this->grantLessonPackagesView();
        [$student, $team] = $this->makeStudentWithTeam();
        $template = $this->makeSingleLessonTemplate();

        $trial = $this->withHeaders($this->ajaxHeaders())
            ->postJson(
                route('schedule.empty-cell.place-trial', $student),
                $this->placeTrialPayload((int) $team->id, '2026-08-10', [
                    'lesson_occurrence_status_id' => $this->visitedStatusId,
                    'journal_team_filter' => 'all',
                ])
            );
        $trial->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('result.consuming_count', 1);

        $single = $this->withHeaders($this->ajaxHeaders())
            ->postJson(
                route('schedule.empty-cell.place-single', $student),
                $this->placeSingleCreatePayload($template, (int) $team->id, '2026-08-11', 1000, [
                    'lesson_occurrence_status_id' => $this->visitedStatusId,
                    'journal_team_filter' => 'all',
                ])
            );
        $single->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('result.consuming_count', 2);
    }

    public function test_unsupported_methods_on_journal_index_and_update_are_not_server_errors(): void
    {
        $this->grantScheduleView();
        [$student, $team] = $this->makeStudentWithTeam();
        $utss = $this->createTrialUtss($student, $team, '2026-08-03');

        foreach (['post', 'patch', 'put', 'delete'] as $method) {
            $response = $this->{$method}(route('schedule.index'), [
                'year' => 2026,
                'month' => '08',
            ]);
            $this->assertNotSame(500, $response->getStatusCode(), $method);
            $this->assertNotSame(200, $response->getStatusCode(), $method);
            $this->assertSame(405, $response->status(), $method);
        }

        $this->patchJson(route('schedule.update'), [
            'user_id' => $student->id,
            'utss_id' => $utss->id,
            'occurrence_date' => '2026-08-03',
            'lesson_occurrence_status_id' => $this->visitedStatusId,
        ])->assertStatus(405);

        $this->putJson(route('schedule.update'), [
            'user_id' => $student->id,
            'utss_id' => $utss->id,
            'occurrence_date' => '2026-08-03',
            'lesson_occurrence_status_id' => $this->visitedStatusId,
        ])->assertStatus(405);

        $getUpdate = $this->getJson(route('schedule.update'));
        $this->assertNotSame(500, $getUpdate->getStatusCode());
        $this->assertNotSame(200, $getUpdate->getStatusCode());
        $this->assertContains($getUpdate->getStatusCode(), [404, 405]);
    }

    public function test_user_without_partner_is_logged_out_from_journal(): void
    {
        $actor = User::factory()->create(['partner_id' => null]);
        $this->actingAs($actor)->withSession([]);

        $this->get(route('schedule.index', ['year' => 2026, 'month' => '08']))
            ->assertRedirect()
            ->assertSessionHasErrors([
                'email' => 'Ваша организация недоступна.',
            ]);
        $this->assertGuest();
    }
}
