<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Contract;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * Вторая строка колонки «Срок подписания» в списке /client-contracts.
 */
final class ContractSchoolListFillRemainingDaysTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_future_deadline_uses_short_day_forms(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-12 10:00:00', 'Europe/Moscow'));

        $this->assertSame(
            '2 дня',
            $this->unsignedTemplate(Carbon::parse('2026-09-14 12:00:00', 'Europe/Moscow'))
                ->schoolListFillRemainingDaysLabel()
        );
        $this->assertSame(
            '1 день',
            $this->unsignedTemplate(Carbon::parse('2026-09-13 12:00:00', 'Europe/Moscow'))
                ->schoolListFillRemainingDaysLabel()
        );
        $this->assertSame(
            '6 дней',
            $this->unsignedTemplate(Carbon::parse('2026-09-18 15:00:00', 'Europe/Moscow'))
                ->schoolListFillRemainingDaysLabel()
        );
        $this->assertFalse(
            $this->unsignedTemplate(Carbon::parse('2026-09-18 15:00:00', 'Europe/Moscow'))
                ->shouldHighlightSchoolListFillRemainingDays()
        );
        $this->assertTrue(
            $this->unsignedTemplate(Carbon::parse('2026-09-17 15:00:00', 'Europe/Moscow'))
                ->shouldHighlightSchoolListFillRemainingDays()
        );
    }

    public function test_same_calendar_day_is_last_day_and_highlighted(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-12 10:00:00', 'Europe/Moscow'));
        $contract = $this->unsignedTemplate(Carbon::parse('2026-09-12 18:00:00', 'Europe/Moscow'));

        $this->assertSame(Contract::CLIENT_FILL_REMAINING_LAST_DAY, $contract->schoolListFillRemainingDaysLabel());
        $this->assertTrue($contract->shouldHighlightSchoolListFillRemainingDays());
    }

    public function test_past_deadline_is_expired_and_highlighted(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-12 10:00:00', 'Europe/Moscow'));
        $contract = $this->unsignedTemplate(Carbon::parse('2026-09-12 08:00:00', 'Europe/Moscow'));

        $this->assertSame(Contract::SCHOOL_LIST_FILL_REMAINING_EXPIRED, $contract->schoolListFillRemainingDaysLabel());
        $this->assertTrue($contract->shouldHighlightSchoolListFillRemainingDays());
    }

    public function test_signed_hides_remaining_even_if_deadline_is_future_or_past(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-12 10:00:00', 'Europe/Moscow'));

        $future = $this->unsignedTemplate(Carbon::parse('2026-09-18 15:00:00', 'Europe/Moscow'));
        $future->status = Contract::STATUS_SIGNED;
        $this->assertNull($future->schoolListFillRemainingDaysLabel());
        $this->assertFalse($future->shouldHighlightSchoolListFillRemainingDays());

        $past = $this->unsignedTemplate(Carbon::parse('2026-09-10 15:00:00', 'Europe/Moscow'));
        $past->status = Contract::STATUS_SIGNED;
        $this->assertNull($past->schoolListFillRemainingDaysLabel());
        $this->assertFalse($past->shouldHighlightSchoolListFillRemainingDays());
    }

    public function test_null_deadline_hides_remaining(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-12 10:00:00', 'Europe/Moscow'));
        $contract = $this->unsignedTemplate(null);

        $this->assertNull($contract->schoolListFillRemainingDaysLabel());
        $this->assertFalse($contract->shouldHighlightSchoolListFillRemainingDays());
    }

    private function unsignedTemplate(?Carbon $expiresAt): Contract
    {
        return new Contract([
            'creation_mode' => Contract::CREATION_MODE_TEMPLATE,
            'status' => Contract::STATUS_AWAITING_CLIENT_FILL,
            'fill_expires_at' => $expiresAt,
        ]);
    }
}
