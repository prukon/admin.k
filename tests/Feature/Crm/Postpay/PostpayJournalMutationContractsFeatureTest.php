<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Postpay;

use App\Models\UserLessonPackage;
use App\Models\UserPrice;
use App\Models\UserTeamScheduleSlot;

/**
 * AJAX / non-AJAX контракты schedule.update для create_postpay.
 *
 * @see \Tests\Feature\Crm\Schedule\ScheduleJournalMutationContractsFeatureTest
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class PostpayJournalMutationContractsFeatureTest extends PostpayTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPostpay(withScheduleView: true, withSetPricesView: false);
        $this->ensureUserPriceRow();
    }

    public function test_create_postpay_ajax_returns_json_contract_and_creates_utss(): void
    {
        $response = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.update'), [
                'user_id' => $this->student->id,
                'create_postpay' => 1,
                'team_id' => $this->team->id,
                'occurrence_date' => '2026-08-11',
                'lesson_occurrence_status_id' => $this->attendedStatusId,
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'result' => [
                    'utss_id',
                    'occurrence_date',
                    'comment',
                    'created',
                    'status' => ['id', 'title', 'icon', 'color'],
                ],
            ])
            ->assertJsonPath('result.created', true)
            ->assertJsonPath('result.occurrence_date', '2026-08-11')
            ->assertJsonPath('result.status.id', $this->attendedStatusId);

        $utss = UserTeamScheduleSlot::query()
            ->where('user_id', $this->student->id)
            ->whereDate('starts_at', '2026-08-11')
            ->first();
        $this->assertNotNull($utss);
        $response->assertJsonPath('result.utss_id', $utss->id);

        $this->assertSame(1, $this->countPostpayUtssForStudent());
        $this->assertSame(0, UserLessonPackage::query()->count());

        $row = UserPrice::query()
            ->where('user_id', $this->student->id)
            ->where('team_id', $this->team->id)
            ->whereDate('new_month', '2026-08-01')
            ->first();
        $this->assertNotNull($row);
        $this->assertSame(50000, (int) $row->price_cents);
    }

    public function test_create_postpay_ajax_validation_returns_422_with_errors(): void
    {
        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.update'), [
                'user_id' => $this->student->id,
                'create_postpay' => 1,
                'team_id' => $this->team->id,
                'occurrence_date' => '2026-08-11',
                // нет lesson_occurrence_status_id
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors'])
            ->assertJsonValidationErrors(['lesson_occurrence_status_id']);
    }

    public function test_create_postpay_non_ajax_redirects_and_creates_occurrence_not_empty_200(): void
    {
        $response = $this->post(route('schedule.update'), [
            '_token' => csrf_token(),
            'user_id' => $this->student->id,
            'create_postpay' => 1,
            'team_id' => $this->team->id,
            'occurrence_date' => '2026-08-12',
            'lesson_occurrence_status_id' => $this->attendedStatusId,
        ]);

        $response->assertStatus(302);
        $response->assertRedirect(route('schedule.index'));
        $this->assertNotSame(200, $response->getStatusCode());

        $this->assertSame(1, $this->countPostpayUtssForStudent());
        $this->assertDatabaseHas('user_lesson_occurrence_status_events', [
            'user_id' => $this->student->id,
            'occurrence_date' => '2026-08-12',
            'lesson_occurrence_status_id' => $this->attendedStatusId,
        ]);

        $row = UserPrice::query()
            ->where('user_id', $this->student->id)
            ->where('team_id', $this->team->id)
            ->whereDate('new_month', '2026-08-01')
            ->first();
        $this->assertNotNull($row);
        $this->assertSame(50000, (int) $row->price_cents);
        $this->assertSame(0, UserLessonPackage::query()->count());
    }

    public function test_create_postpay_non_ajax_validation_failure_redirects_with_errors_not_empty_200(): void
    {
        $before = UserTeamScheduleSlot::query()->count();

        $this->from(route('schedule.index'))
            ->post(route('schedule.update'), [
                '_token' => csrf_token(),
                'user_id' => $this->student->id,
                'create_postpay' => 1,
                'team_id' => $this->team->id,
                'occurrence_date' => '2026-08-12',
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['lesson_occurrence_status_id']);

        $this->assertSame($before, UserTeamScheduleSlot::query()->count());
    }

    public function test_create_postpay_ajax_business_error_when_no_assignment_returns_422(): void
    {
        UserPrice::query()
            ->where('user_id', $this->student->id)
            ->where('team_id', $this->team->id)
            ->delete();

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.update'), [
                'user_id' => $this->student->id,
                'create_postpay' => 1,
                'team_id' => $this->team->id,
                'occurrence_date' => '2026-08-15',
                'lesson_occurrence_status_id' => $this->attendedStatusId,
            ])
            ->assertStatus(422);

        $this->assertSame(0, $this->countPostpayUtssForStudent());
    }

    public function test_cell_context_returns_postpay_teams_for_assigned_month(): void
    {
        $this->getJson(route('schedule.cell-context', [
            'user_id' => $this->student->id,
            'date' => '2026-08-05',
        ]))
            ->assertOk()
            ->assertJsonPath('postpay_team_id', $this->team->id)
            ->assertJsonFragment([
                'id' => $this->team->id,
                'price_per_lesson' => 500.0,
            ]);
    }

    public function test_destroy_postpay_ajax_returns_result_and_recalculates_price(): void
    {
        $create = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.update'), [
                'user_id' => $this->student->id,
                'create_postpay' => 1,
                'team_id' => $this->team->id,
                'occurrence_date' => '2026-08-18',
                'lesson_occurrence_status_id' => $this->attendedStatusId,
            ]);
        $create->assertOk();
        $utssId = (int) $create->json('result.utss_id');
        $this->assertGreaterThan(0, $utssId);

        $rowBefore = UserPrice::query()
            ->where('user_id', $this->student->id)
            ->where('team_id', $this->team->id)
            ->whereDate('new_month', '2026-08-01')
            ->first();
        $this->assertNotNull($rowBefore);
        $this->assertSame(50000, (int) $rowBefore->price_cents);

        $response = $this->withHeaders($this->ajaxHeaders())
            ->deleteJson(route('schedule.occurrence.destroy', $utssId), [
                'occurrence_date' => '2026-08-18',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Занятие удалено.')
            ->assertJsonPath('result.deleted', true)
            ->assertJsonPath('result.utss_id', $utssId)
            ->assertJsonPath('result.occurrence_count', 0)
            ->assertJsonPath('result.is_postpay', true)
            ->assertJsonStructure([
                'result' => [
                    'utss_id',
                    'occurrence_date',
                    'deleted',
                    'occurrence_count',
                    'is_postpay',
                    'is_flexible',
                ],
            ]);

        $this->assertSame(0, $this->countPostpayUtssForStudent());
        $rowBefore->refresh();
        $this->assertSame(0, (int) $rowBefore->price_cents);
    }

    public function test_destroy_postpay_ajax_locked_when_paid_returns_422(): void
    {
        $create = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.update'), [
                'user_id' => $this->student->id,
                'create_postpay' => 1,
                'team_id' => $this->team->id,
                'occurrence_date' => '2026-08-19',
                'lesson_occurrence_status_id' => $this->attendedStatusId,
            ]);
        $create->assertOk();
        $utssId = (int) $create->json('result.utss_id');

        $row = UserPrice::query()
            ->where('user_id', $this->student->id)
            ->where('team_id', $this->team->id)
            ->whereDate('new_month', '2026-08-01')
            ->firstOrFail();
        $row->is_paid = 1;
        $row->save();

        $this->withHeaders($this->ajaxHeaders())
            ->deleteJson(route('schedule.occurrence.destroy', $utssId), [
                'occurrence_date' => '2026-08-19',
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);

        $this->assertDatabaseHas('user_team_schedule_slots', ['id' => $utssId]);
    }

    public function test_destroy_postpay_non_ajax_redirects_and_recalculates_price(): void
    {
        $create = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.update'), [
                'user_id' => $this->student->id,
                'create_postpay' => 1,
                'team_id' => $this->team->id,
                'occurrence_date' => '2026-08-21',
                'lesson_occurrence_status_id' => $this->attendedStatusId,
            ]);
        $create->assertOk();
        $utssId = (int) $create->json('result.utss_id');

        $response = $this->flushHeaders()->delete(route('schedule.occurrence.destroy', $utssId), [
            '_token' => csrf_token(),
            'occurrence_date' => '2026-08-21',
        ]);

        $response->assertStatus(302);
        $response->assertRedirect(route('schedule.index'));
        $response->assertSessionHas('status', 'Занятие удалено.');
        $this->assertDatabaseMissing('user_team_schedule_slots', ['id' => $utssId]);

        $row = UserPrice::query()
            ->where('user_id', $this->student->id)
            ->where('team_id', $this->team->id)
            ->whereDate('new_month', '2026-08-01')
            ->first();
        $this->assertNotNull($row);
        $this->assertSame(0, (int) $row->price_cents);
    }
}
