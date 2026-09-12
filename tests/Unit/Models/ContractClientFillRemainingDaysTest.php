<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Contract;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * Календарные дни до fill_expires_at на карточке «Мои документы».
 *
 * @see /docs/documentation/account-contract-fill.html#account-documents-fill-remaining-days
 */
final class ContractClientFillRemainingDaysTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_six_calendar_days_are_not_highlighted(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-12 10:00:00', 'Europe/Moscow'));
        $contract = $this->unsignedTemplate(Carbon::parse('2026-09-18 15:00:00', 'Europe/Moscow'));

        $this->assertTrue($contract->shouldShowClientFillRemainingDays());
        $this->assertSame(6, $contract->clientFillRemainingDays());
        $this->assertSame('Осталось 6 дней', $contract->clientFillRemainingDaysLabel());
        $this->assertFalse($contract->shouldHighlightClientFillRemainingDays());
    }

    public function test_five_calendar_days_are_highlighted(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-12 10:00:00', 'Europe/Moscow'));
        $contract = $this->unsignedTemplate(Carbon::parse('2026-09-17 15:00:00', 'Europe/Moscow'));

        $this->assertSame(5, $contract->clientFillRemainingDays());
        $this->assertSame('Осталось 5 дней', $contract->clientFillRemainingDaysLabel());
        $this->assertTrue($contract->shouldHighlightClientFillRemainingDays());
    }

    public function test_same_calendar_day_is_last_day_and_highlighted(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-12 10:00:00', 'Europe/Moscow'));
        $contract = $this->unsignedTemplate(Carbon::parse('2026-09-12 18:00:00', 'Europe/Moscow'));

        $this->assertSame(0, $contract->clientFillRemainingDays());
        $this->assertSame(Contract::CLIENT_FILL_REMAINING_LAST_DAY, $contract->clientFillRemainingDaysLabel());
        $this->assertTrue($contract->shouldHighlightClientFillRemainingDays());
    }

    public function test_past_deadline_hides_remaining_days(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-12 10:00:00', 'Europe/Moscow'));
        $contract = $this->unsignedTemplate(Carbon::parse('2026-09-12 08:00:00', 'Europe/Moscow'));

        $this->assertFalse($contract->shouldShowClientFillRemainingDays());
        $this->assertNull($contract->clientFillRemainingDays());
        $this->assertNull($contract->clientFillRemainingDaysLabel());
        $this->assertFalse($contract->shouldHighlightClientFillRemainingDays());
    }

    public function test_signed_hides_remaining_days_even_if_deadline_is_future(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-12 10:00:00', 'Europe/Moscow'));
        $contract = $this->unsignedTemplate(Carbon::parse('2026-09-18 15:00:00', 'Europe/Moscow'));
        $contract->status = Contract::STATUS_SIGNED;

        $this->assertFalse($contract->shouldShowClientFillRemainingDays());
        $this->assertNull($contract->clientFillRemainingDaysLabel());
    }

    public function test_sent_and_opened_still_show_remaining_days(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-12 10:00:00', 'Europe/Moscow'));
        $expires = Carbon::parse('2026-09-18 15:00:00', 'Europe/Moscow');

        foreach ([Contract::STATUS_SENT, Contract::STATUS_OPENED, Contract::STATUS_EXPIRED] as $status) {
            $contract = $this->unsignedTemplate($expires);
            $contract->status = $status;

            $this->assertSame('Осталось 6 дней', $contract->clientFillRemainingDaysLabel(), $status);
        }
    }

    public function test_pdf_mode_hides_remaining_days(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-12 10:00:00', 'Europe/Moscow'));
        $contract = $this->unsignedTemplate(Carbon::parse('2026-09-18 15:00:00', 'Europe/Moscow'));
        $contract->creation_mode = Contract::CREATION_MODE_PDF;

        $this->assertFalse($contract->shouldShowClientFillRemainingDays());
    }

    public function test_russian_day_word_forms(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-12 10:00:00', 'Europe/Moscow'));

        $this->assertSame(
            'Осталось 1 день',
            $this->unsignedTemplate(Carbon::parse('2026-09-13 12:00:00', 'Europe/Moscow'))->clientFillRemainingDaysLabel()
        );
        $this->assertSame(
            'Осталось 2 дня',
            $this->unsignedTemplate(Carbon::parse('2026-09-14 12:00:00', 'Europe/Moscow'))->clientFillRemainingDaysLabel()
        );
        $this->assertSame(
            'Осталось 21 день',
            $this->unsignedTemplate(Carbon::parse('2026-10-03 12:00:00', 'Europe/Moscow'))->clientFillRemainingDaysLabel()
        );
        $this->assertSame(
            'Осталось 22 дня',
            $this->unsignedTemplate(Carbon::parse('2026-10-04 12:00:00', 'Europe/Moscow'))->clientFillRemainingDaysLabel()
        );
        $this->assertSame(
            'Осталось 25 дней',
            $this->unsignedTemplate(Carbon::parse('2026-10-07 12:00:00', 'Europe/Moscow'))->clientFillRemainingDaysLabel()
        );
    }

    private function unsignedTemplate(Carbon $expiresAt): Contract
    {
        return new Contract([
            'creation_mode' => Contract::CREATION_MODE_TEMPLATE,
            'status' => Contract::STATUS_AWAITING_CLIENT_FILL,
            'fill_expires_at' => $expiresAt,
        ]);
    }
}
