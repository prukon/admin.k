<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Schedule;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Доступ к HTML прелоадера журнала: гость / без schedule.view / viewer / admin;
 * чужие методы не 500; AJAX GET не пустой 200 JSON.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 * @see ScheduleJournalTablePreloaderFeatureTest
 */
final class ScheduleJournalTablePreloaderFullAccessFeatureTest extends ScheduleJournalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpScheduleJournal();
    }

    public function test_guest_cannot_open_journal_preloader_page(): void
    {
        Auth::logout();

        $web = $this->get(route('schedule.index', ['year' => 2026, 'month' => '05']));
        $this->assertNotSame(500, $web->getStatusCode());
        $this->assertNotSame(200, $web->getStatusCode());
        $web->assertStatus(302);

        $json = $this->getJson(route('schedule.index', ['year' => 2026, 'month' => '05']));
        $this->assertNotSame(500, $json->getStatusCode());
        $json->assertStatus(401);

        $ajax = $this->withHeaders($this->ajaxHeaders())
            ->get(route('schedule.index', ['year' => 2026, 'month' => '05']));
        $this->assertNotSame(500, $ajax->getStatusCode());
        $this->assertContains($ajax->getStatusCode(), [302, 401]);
    }

    public function test_manager_without_schedule_view_gets_403_on_journal_preloader_page(): void
    {
        $actor = $this->createUserWithoutPermission('schedule.view', $this->partner);
        $session = ['current_partner' => $this->partner->id, '2fa:passed' => true];

        $web = $this->actingAs($actor)->withSession($session)
            ->get(route('schedule.index', ['year' => 2026, 'month' => '05']));
        $this->assertNotSame(500, $web->getStatusCode());
        $web->assertStatus(403);

        $json = $this->actingAs($actor)->withSession($session)
            ->getJson(route('schedule.index', ['year' => 2026, 'month' => '05']));
        $this->assertNotSame(500, $json->getStatusCode());
        $json->assertStatus(403);
    }

    public function test_viewer_with_schedule_view_sees_journal_preloader_markup(): void
    {
        $actor = $this->createUserWithoutPermission('schedule.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->grantScheduleView($actor);
        $this->makeStudentTeamAndTrainer();

        $page = $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '05',
            'team' => 'all',
        ]));
        $page->assertOk();
        $html = (string) $page->getContent();
        $this->assertNotSame('', trim($html));
        $this->assertStringContainsString('id="schedule-journal-stage"', $html);
        $this->assertStringContainsString('schedule-journal-preloader', $html);
        $this->assertStringContainsString('aria-busy="true"', $html);
        $this->assertStringContainsString('Журнал расписания', $html);
    }

    public function test_admin_with_schedule_view_gets_non_empty_journal_page(): void
    {
        $this->grantScheduleView();
        $this->makeStudentTeamAndTrainer();

        $page = $this->get(route('schedule.index'));
        $page->assertOk();
        $this->assertNotSame('', trim((string) $page->getContent()));
        $page->assertSee('id="schedule-journal-stage"', false);
        $page->assertSee('schedule-journal-preloader', false);
    }

    public function test_unsupported_methods_on_journal_index_are_not_server_errors(): void
    {
        $this->grantScheduleView();

        foreach (['post', 'patch', 'put', 'delete'] as $method) {
            $response = $this->{$method}(route('schedule.index'), [
                'year' => 2026,
                'month' => '05',
            ]);
            $this->assertNotSame(500, $response->getStatusCode(), $method);
            $this->assertNotSame(200, $response->getStatusCode(), $method);
            $this->assertSame(405, $response->status(), $method);
        }

        $this->postJson(route('schedule.index'), ['year' => 2026])->assertStatus(405);
        $this->patchJson(route('schedule.index'), ['year' => 2026])->assertStatus(405);
        $this->deleteJson(route('schedule.index'))->assertStatus(405);
    }

    public function test_user_without_partner_is_logged_out_from_journal_preloader_page(): void
    {
        $actor = User::factory()->create(['partner_id' => null]);
        $this->actingAs($actor)->withSession([]);

        $this->get(route('schedule.index', ['year' => 2026, 'month' => '05']))
            ->assertRedirect()
            ->assertSessionHasErrors([
                'email' => 'Ваша организация недоступна.',
            ]);
        $this->assertGuest();
    }
}
