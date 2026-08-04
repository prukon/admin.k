<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Postpay;

use App\Models\LessonOccurrenceStatus;
use App\Models\UserLessonPackage;
use App\Models\UserPrice;
use App\Services\Payments\UserPriceMonthlyFeePaymentResolver;
use App\Services\Postpay\PostpayMonth;
use Carbon\Carbon;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Сценарии постоплаты: consumes_lesson, lock при ручной оплате, pay-available, журнал.
 */
final class PostpayWorkflowFeatureTest extends PostpayTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPostpay(withScheduleView: true, withSetPricesView: true);
        $this->ensureUserPriceRow();
    }

    public function test_custom_consumes_lesson_status_counts_toward_postpay_amount(): void
    {
        $custom = $this->createConsumingCustomStatus();

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.update'), [
                'user_id' => $this->student->id,
                'create_postpay' => 1,
                'team_id' => $this->team->id,
                'occurrence_date' => '2026-08-08',
                'lesson_occurrence_status_id' => $custom->id,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $row = UserPrice::query()
            ->where('user_id', $this->student->id)
            ->where('team_id', $this->team->id)
            ->whereDate('new_month', '2026-08-01')
            ->first();

        $this->assertNotNull($row);
        $this->assertSame(500.0, (float) $row->price);
        $this->assertSame(0, UserLessonPackage::query()->count());
    }

    public function test_non_consuming_status_does_not_increase_postpay_amount(): void
    {
        $nonConsume = LessonOccurrenceStatus::query()->create([
            'partner_id' => $this->partner->id,
            'code' => 'skip_'.bin2hex(random_bytes(3)),
            'title' => 'Не списывает',
            'icon' => 'fa-solid fa-ban',
            'color' => '#aaaaaa',
            'is_system' => false,
            'is_active' => true,
            'consumes_lesson' => false,
            'sort_order' => 90,
        ]);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.update'), [
                'user_id' => $this->student->id,
                'create_postpay' => 1,
                'team_id' => $this->team->id,
                'occurrence_date' => '2026-08-09',
                'lesson_occurrence_status_id' => $nonConsume->id,
            ])
            ->assertOk();

        $row = UserPrice::query()
            ->where('user_id', $this->student->id)
            ->where('team_id', $this->team->id)
            ->whereDate('new_month', '2026-08-01')
            ->first();

        $this->assertNotNull($row);
        $this->assertSame(0.0, (float) $row->price);
    }

    public function test_manual_paid_locks_journal_create_postpay(): void
    {
        $this->ensureUserPriceRow(price: 500, paid: false, manualPaid: true);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.update'), [
                'user_id' => $this->student->id,
                'create_postpay' => 1,
                'team_id' => $this->team->id,
                'occurrence_date' => '2026-08-14',
                'lesson_occurrence_status_id' => $this->attendedStatusId,
            ])
            ->assertStatus(422);

        $this->assertSame(0, $this->countPostpayUtssForStudent());
    }

    public function test_schedule_index_marks_postpay_and_locked_cells(): void
    {
        $this->ensureUserPriceRow(price: 500, paid: true);

        $html = $this->get(route('schedule.index', ['year' => 2026, 'month' => '08']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-postpay="1"', $html);
        $this->assertStringContainsString('data-postpay-locked="1"', $html);
        $this->assertStringContainsString('Изменить данные нельзя, поскольку уже была произведена оплата', $html);
    }

    public function test_cell_context_existing_postpay_occurrence_hides_slot_time_and_flags_is_postpay(): void
    {
        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.update'), [
                'user_id' => $this->student->id,
                'create_postpay' => 1,
                'team_id' => $this->team->id,
                'occurrence_date' => '2026-08-16',
                'lesson_occurrence_status_id' => $this->attendedStatusId,
            ])
            ->assertOk();

        $ctx = $this->getJson(route('schedule.cell-context', [
            'user_id' => $this->student->id,
            'date' => '2026-08-16',
        ]))->assertOk()->json();

        $this->assertNotEmpty($ctx['occurrences'] ?? []);
        $occ = $ctx['occurrences'][0];
        $this->assertTrue((bool) ($occ['is_postpay'] ?? false));
        $this->assertArrayHasKey('time_start', $occ);
        $this->assertArrayHasKey('time_end', $occ);
        $this->assertNull($occ['time_start']);
        $this->assertNull($occ['time_end']);
        $this->assertSame('Постоплата тест', $occ['package_name'] ?? null);
    }

    public function test_payment_resolver_blocks_until_next_month_then_allows(): void
    {
        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.update'), [
                'user_id' => $this->student->id,
                'create_postpay' => 1,
                'team_id' => $this->team->id,
                'occurrence_date' => '2026-08-17',
                'lesson_occurrence_status_id' => $this->attendedStatusId,
            ])
            ->assertOk();

        $resolver = app(UserPriceMonthlyFeePaymentResolver::class);

        Carbon::setTestNow(Carbon::parse('2026-08-20 12:00:00', PostpayMonth::TIMEZONE));
        try {
            $resolver->resolveOrAbort(
                (int) $this->student->id,
                (int) $this->partner->id,
                '2026-08-01',
                (int) $this->team->id
            );
            $this->fail('Ожидался UnprocessableEntityHttpException до 1 числа следующего месяца');
        } catch (UnprocessableEntityHttpException $e) {
            $this->assertStringContainsString('Оплата будет доступна с', $e->getMessage());
        }

        Carbon::setTestNow(Carbon::parse('2026-09-01 00:00:00', PostpayMonth::TIMEZONE));
        $resolved = $resolver->resolveOrAbort(
            (int) $this->student->id,
            (int) $this->partner->id,
            '2026-08-01',
            (int) $this->team->id
        );
        $this->assertSame('500.00', $resolved['out_sum']);
        $this->assertSame((int) $this->team->id, $resolved['team_id']);

        Carbon::setTestNow();
    }

    public function test_second_visit_recalculates_amount_live(): void
    {
        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.update'), [
                'user_id' => $this->student->id,
                'create_postpay' => 1,
                'team_id' => $this->team->id,
                'occurrence_date' => '2026-08-03',
                'lesson_occurrence_status_id' => $this->attendedStatusId,
            ])
            ->assertOk();

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('schedule.update'), [
                'user_id' => $this->student->id,
                'create_postpay' => 1,
                'team_id' => $this->team->id,
                'occurrence_date' => '2026-08-04',
                'lesson_occurrence_status_id' => $this->attendedStatusId,
            ])
            ->assertOk();

        $row = UserPrice::query()
            ->where('user_id', $this->student->id)
            ->where('team_id', $this->team->id)
            ->whereDate('new_month', '2026-08-01')
            ->first();

        $this->assertNotNull($row);
        $this->assertSame(1000.0, (float) $row->price);
        $this->assertSame(2, $this->countPostpayUtssForStudent());
    }
}
