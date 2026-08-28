<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Schedule;

use App\Models\User;
use App\Models\UserPrice;
use App\Models\UserTeamScheduleSlot;
use Illuminate\Support\Facades\Auth;

/**
 * Доступ к сумме постоплаты в колонке оплаты: гость / без schedule.view / viewer / admin.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 * @see ScheduleJournalPostpayPaymentDueFeatureTest
 */
final class ScheduleJournalPostpayPaymentDueFullAccessFeatureTest extends ScheduleJournalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->setUpScheduleJournal();
    }

    public function test_guest_cannot_open_journal_payment_due_page(): void
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

    public function test_guest_cannot_mutate_postpay_payment_due_endpoints(): void
    {
        Auth::logout();
        [$student, $team] = $this->makeStudentWithPostpayMonth(0, false, 0, 50000);

        $this->post(route('schedule.update'), [
            'user_id' => $student->id,
            'create_postpay' => 1,
            'team_id' => $team->id,
            'occurrence_date' => '2026-08-03',
            'lesson_occurrence_status_id' => $this->visitedStatusId,
        ])->assertRedirect();

        $this->postJson(route('schedule.update'), [
            'user_id' => $student->id,
            'create_postpay' => 1,
            'team_id' => $team->id,
            'occurrence_date' => '2026-08-03',
            'lesson_occurrence_status_id' => $this->visitedStatusId,
        ])->assertUnauthorized();

        $this->assertSame(0, UserTeamScheduleSlot::query()->where('user_id', $student->id)->count());
        $row = UserPrice::query()
            ->where('user_id', $student->id)
            ->where('team_id', $team->id)
            ->whereDate('new_month', '2026-08-01')
            ->first();
        $this->assertNotNull($row);
        $this->assertSame(0, (int) $row->price_cents);
    }

    public function test_manager_without_schedule_view_gets_403_on_journal_and_mutations(): void
    {
        $actor = $this->createUserWithoutPermission('schedule.view', $this->partner);
        $session = ['current_partner' => $this->partner->id, '2fa:passed' => true];
        [$student, $team] = $this->makeStudentWithPostpayMonth(180000, false, 0, 50000);

        $web = $this->actingAs($actor)->withSession($session)
            ->get(route('schedule.index', ['year' => 2026, 'month' => '08', 'team' => $team->id]));
        $this->assertNotSame(500, $web->getStatusCode());
        $web->assertStatus(403);

        $json = $this->actingAs($actor)->withSession($session)
            ->getJson(route('schedule.index', ['year' => 2026, 'month' => '08', 'team' => $team->id]));
        $this->assertNotSame(500, $json->getStatusCode());
        $json->assertStatus(403);

        $this->actingAs($actor)->withSession($session)
            ->postJson(route('schedule.update'), [
                'user_id' => $student->id,
                'create_postpay' => 1,
                'team_id' => $team->id,
                'occurrence_date' => '2026-08-03',
                'lesson_occurrence_status_id' => $this->visitedStatusId,
            ])
            ->assertForbidden();

        $this->assertSame(0, UserTeamScheduleSlot::query()->where('user_id', $student->id)->count());
    }

    public function test_viewer_with_schedule_view_sees_due_amount_and_header_hints(): void
    {
        $actor = $this->createUserWithoutPermission('schedule.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->grantScheduleView($actor);
        [$student, $team] = $this->makeStudentWithPostpayMonth(50000, false, 0, 50000);

        $page = $this->get(route('schedule.index', [
            'year' => 2026,
            'month' => '08',
            'team' => $team->id,
        ]));
        $page->assertOk();
        $html = (string) $page->getContent();
        $this->assertNotSame('', trim($html));
        $this->assertSame('due', $this->journalPaymentStatusInHtml($html, (int) $student->id));
        $this->assertStringContainsString('500₽', $this->journalPaymentCellHtml($html, (int) $student->id));
        $this->assertStringContainsString('title="Статус оплаты"', $html);
        $this->assertStringContainsString('journal-col-header-hint', $html);
    }

    public function test_admin_with_schedule_view_gets_non_empty_journal_with_due_column(): void
    {
        $this->grantScheduleView();
        $this->makeStudentWithPostpayMonth(50000, false, 0, 50000);

        $page = $this->get(route('schedule.index', ['year' => 2026, 'month' => '08']));
        $page->assertOk();
        $html = (string) $page->getContent();
        $this->assertNotSame('', trim($html));
        $page->assertSee('schedule-payment-status', false)
            ->assertSee('journal-monthly-payment-due', false)
            ->assertSee('title="Статус оплаты"', false);
    }

    public function test_ajax_get_journal_returns_html_due_column_not_empty_json(): void
    {
        $this->grantScheduleView();
        [$student, $team] = $this->makeStudentWithPostpayMonth(50000, false, 0, 50000);

        $page = $this->withHeaders($this->ajaxHeaders())
            ->get(route('schedule.index', [
                'year' => 2026,
                'month' => '08',
                'team' => $team->id,
            ]));
        $page->assertOk();
        $html = (string) $page->getContent();
        $this->assertNotSame('', trim($html));
        $this->assertStringContainsString('id="schedule-table"', $html);
        $this->assertSame('due', $this->journalPaymentStatusInHtml($html, (int) $student->id));
        $this->assertStringNotContainsString('"success":true', $html);
    }

    public function test_viewer_create_postpay_and_destroy_return_payment_status_json(): void
    {
        $this->grantScheduleView();
        [$student, $team] = $this->makeStudentWithPostpayMonth(0, false, 0, 50000);

        $create = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.update'), [
                'user_id' => $student->id,
                'create_postpay' => 1,
                'team_id' => $team->id,
                'occurrence_date' => '2026-08-03',
                'lesson_occurrence_status_id' => $this->visitedStatusId,
                'journal_team_filter' => (string) $team->id,
            ]);
        $create->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('result.payment_status.state', 'due')
            ->assertJsonPath('result.payment_status.amount_cents', 50000);
        $utssId = (int) $create->json('result.utss_id');
        $this->assertGreaterThan(0, $utssId);

        $destroy = $this->withHeaders($this->ajaxHeaders())
            ->deleteJson(route('schedule.occurrence.destroy', $utssId), [
                'occurrence_date' => '2026-08-03',
                'journal_team_filter' => (string) $team->id,
            ]);
        $destroy->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('result.payment_status.state', 'none')
            ->assertJsonPath('result.payment_status.amount_cents', 0);
        $this->assertDatabaseMissing('user_team_schedule_slots', ['id' => $utssId]);
    }

    public function test_unsupported_methods_on_journal_index_and_update_are_not_server_errors(): void
    {
        $this->grantScheduleView();
        [$student, $team] = $this->makeStudentWithPostpayMonth(0, false, 0, 50000);

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
            'create_postpay' => 1,
            'team_id' => $team->id,
            'occurrence_date' => '2026-08-03',
            'lesson_occurrence_status_id' => $this->visitedStatusId,
        ])->assertStatus(405);

        $this->putJson(route('schedule.update'), [
            'user_id' => $student->id,
            'create_postpay' => 1,
            'team_id' => $team->id,
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
