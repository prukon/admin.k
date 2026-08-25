<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Schedule;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Доступ к серверному поиску q на GET /schedule (кнопка «Найти» / Enter): гость / без schedule.view / viewer / admin.
 * Не 500 и не пустой 200; JSON/AJAX/HTML — ожидаемые статусы.
 *
 * @see ScheduleJournalSearchFeatureTest
 * @see ScheduleJournalPaginationFeatureTest
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ScheduleJournalSearchAccessFeatureTest extends ScheduleJournalTestCase
{
    private const SEARCH_PARAMS = [
        'year' => 2026,
        'month' => '08',
        'team' => 'all',
        'q' => 'ЖурналДоступ',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpScheduleJournal();
    }

    public function test_guest_web_search_is_redirected_not_server_error(): void
    {
        Auth::logout();

        $response = $this->get(route('schedule.index', self::SEARCH_PARAMS));
        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertStatus(302);
    }

    public function test_guest_json_search_is_unauthorized(): void
    {
        Auth::logout();

        $response = $this->getJson(route('schedule.index', self::SEARCH_PARAMS));
        $this->assertNotSame(500, $response->getStatusCode());
        $response->assertStatus(401);
    }

    public function test_guest_ajax_search_is_redirect_or_unauthorized(): void
    {
        Auth::logout();

        $response = $this->withHeaders($this->ajaxHeaders())
            ->get(route('schedule.index', self::SEARCH_PARAMS));
        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertContains($response->getStatusCode(), [302, 401]);
    }

    public function test_manager_without_schedule_view_gets_403_on_search(): void
    {
        $actor = $this->createUserWithoutPermission('schedule.view', $this->partner);
        $session = ['current_partner' => $this->partner->id, '2fa:passed' => true];

        $web = $this->actingAs($actor)->withSession($session)
            ->get(route('schedule.index', self::SEARCH_PARAMS));
        $this->assertNotSame(500, $web->getStatusCode());
        $web->assertStatus(403);

        $json = $this->actingAs($actor)->withSession($session)
            ->getJson(route('schedule.index', self::SEARCH_PARAMS));
        $this->assertNotSame(500, $json->getStatusCode());
        $json->assertStatus(403);

        $ajax = $this->actingAs($actor)->withSession($session)
            ->withHeaders($this->ajaxHeaders())
            ->get(route('schedule.index', self::SEARCH_PARAMS));
        $this->assertNotSame(500, $ajax->getStatusCode());
        $ajax->assertStatus(403);
    }

    public function test_viewer_with_schedule_view_can_search_journal(): void
    {
        $actor = $this->createUserWithoutPermission('schedule.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->grantScheduleView($actor);

        $student = $this->makeStudent();
        $student->update(['lastname' => 'ЖурналДоступ', 'name' => 'Viewer']);

        $page = $this->get(route('schedule.index', self::SEARCH_PARAMS));
        $page->assertOk();
        $html = (string) $page->getContent();
        $this->assertNotSame('', trim($html));
        $this->assertStringContainsString('id="table-search"', $html);
        $this->assertStringContainsString('id="schedule-table"', $html);
        $this->assertStringContainsString($student->full_name, $html);
        $this->assertStringNotContainsString('Whoops', $html);
    }

    public function test_admin_with_schedule_view_can_search_journal(): void
    {
        $this->grantScheduleView();
        $student = $this->makeStudent();
        $student->update(['lastname' => 'ЖурналДоступ', 'name' => 'Admin']);

        $page = $this->get(route('schedule.index', self::SEARCH_PARAMS));
        $page->assertOk();
        $html = (string) $page->getContent();
        $this->assertNotSame('', trim($html));
        $this->assertStringContainsString('value="ЖурналДоступ"', $html);
        $this->assertStringContainsString($student->full_name, $html);
    }

    public function test_invalid_q_on_json_search_returns_422_with_field_errors(): void
    {
        $this->grantScheduleView();

        $this->getJson(route('schedule.index', array_merge(self::SEARCH_PARAMS, [
            'q' => str_repeat('я', 192),
        ])))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['q'])
            ->assertJsonPath('errors.q.0', 'Поисковый запрос слишком длинный.');
    }

    public function test_invalid_q_on_html_search_redirects_with_error_under_search_field(): void
    {
        $this->grantScheduleView();

        $this->from(route('schedule.index'))
            ->get(route('schedule.index', array_merge(self::SEARCH_PARAMS, [
                'q' => str_repeat('я', 192),
            ])))
            ->assertRedirect(route('schedule.index'))
            ->assertSessionHasErrors(['q' => 'Поисковый запрос слишком длинный.']);

        $html = (string) $this->from(route('schedule.index'))
            ->followingRedirects()
            ->get(route('schedule.index', array_merge(self::SEARCH_PARAMS, [
                'q' => str_repeat('я', 192),
            ])))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Поисковый запрос слишком длинный.', $html);
        $this->assertMatchesRegularExpression(
            '/wrap-filter-search[\s\S]*Поисковый запрос слишком длинный\./',
            $html
        );
    }

    public function test_unsupported_methods_on_search_index_are_not_server_errors(): void
    {
        $this->grantScheduleView();

        foreach (['post', 'patch', 'put', 'delete'] as $method) {
            $response = $this->{$method}(route('schedule.index'), self::SEARCH_PARAMS);
            $this->assertNotSame(500, $response->getStatusCode(), $method);
            $this->assertNotSame(200, $response->getStatusCode(), $method);
            $this->assertSame(405, $response->status(), $method);
        }

        $this->postJson(route('schedule.index'), self::SEARCH_PARAMS)->assertStatus(405);
        $this->patchJson(route('schedule.index'), self::SEARCH_PARAMS)->assertStatus(405);
        $this->deleteJson(route('schedule.index'))->assertStatus(405);
    }

    public function test_user_without_partner_is_logged_out_from_search(): void
    {
        $actor = User::factory()->create(['partner_id' => null]);
        $this->actingAs($actor)->withSession([]);

        $this->get(route('schedule.index', self::SEARCH_PARAMS))
            ->assertRedirect()
            ->assertSessionHasErrors([
                'email' => 'Ваша организация недоступна.',
            ]);
        $this->assertGuest();
    }

    public function test_foreign_partner_student_is_not_found_by_search(): void
    {
        $this->grantScheduleView();
        $this->makeStudent()->update(['lastname' => 'ЖурналДоступ', 'name' => 'Свой']);

        $foreign = $this->foreignUser;
        $foreign->update([
            'lastname' => 'ЖурналДоступ',
            'name' => 'Чужой',
            'is_enabled' => 1,
            'role_id' => $this->studentRoleId(),
        ]);

        $html = (string) $this->get(route('schedule.index', self::SEARCH_PARAMS))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('ЖурналДоступ', $html);
        $this->assertStringNotContainsString('data-user-id="'.$foreign->id.'"', $html);
    }
}
