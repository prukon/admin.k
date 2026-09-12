<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Account;

use App\Models\Contract;
use Carbon\Carbon;
use Tests\Feature\Crm\Account\Concerns\InteractsWithAccountContractFill;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Карточка «Мои документы»: оставшиеся дни до fill_expires_at, красным при < 6.
 *
 * @see /docs/documentation/account-contract-fill.html#account-documents-fill-remaining-days
 */
final class AccountDocumentsFillRemainingDaysUxFeatureTest extends CrmTestCase
{
    use InteractsWithAccountContractFill;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useAccountContractFillStorage();
        $this->withSession($this->accountDocumentsSession());
        Carbon::setTestNow(Carbon::parse('2026-09-12 10:00:00', 'Europe/Moscow'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_six_days_left_shows_label_without_danger(): void
    {
        $contract = $this->makeAwaitingFillContract([
            ['key' => 'parent_lastname', 'label' => 'Фамилия', 'required' => true],
        ]);
        $contract->update([
            'fill_expires_at' => Carbon::parse('2026-09-18 15:00:00', 'Europe/Moscow'),
        ]);

        $card = $this->cardAroundContract(
            $this->get(route('account.documents.index'))->assertOk()->getContent(),
            $contract->id
        );

        $this->assertStringContainsString(Contract::CLIENT_FILL_REMAINING_CAPTION, $card);
        $this->assertStringContainsString('Осталось 6 дней', $card);
        $this->assertStringNotContainsString('text-danger', $this->remainingDaysValueHtml($card));
        $this->assertStringNotContainsString(Contract::CLIENT_FILL_REMAINING_LAST_DAY, $card);
    }

    public function test_five_days_left_highlights_value_in_red(): void
    {
        $contract = $this->makeAwaitingFillContract([
            ['key' => 'parent_lastname', 'label' => 'Фамилия', 'required' => true],
        ]);
        $contract->update([
            'fill_expires_at' => Carbon::parse('2026-09-17 15:00:00', 'Europe/Moscow'),
        ]);

        $card = $this->cardAroundContract(
            $this->get(route('account.documents.index'))->assertOk()->getContent(),
            $contract->id
        );

        $this->assertStringContainsString('Осталось 5 дней', $card);
        $this->assertStringContainsString('text-danger', $this->remainingDaysValueHtml($card));
    }

    public function test_last_day_is_highlighted(): void
    {
        $contract = $this->makeAwaitingFillContract([
            ['key' => 'parent_lastname', 'label' => 'Фамилия', 'required' => true],
        ]);
        $contract->update([
            'fill_expires_at' => Carbon::parse('2026-09-12 18:00:00', 'Europe/Moscow'),
        ]);

        $card = $this->cardAroundContract(
            $this->get(route('account.documents.index'))->assertOk()->getContent(),
            $contract->id
        );

        $this->assertStringContainsString(Contract::CLIENT_FILL_REMAINING_CAPTION, $card);
        $this->assertStringContainsString(Contract::CLIENT_FILL_REMAINING_LAST_DAY, $card);
        $this->assertStringContainsString('text-danger', $this->remainingDaysValueHtml($card));
        $this->assertStringNotContainsString('Осталось 0', $card);
    }

    public function test_sent_shows_remaining_days_until_signed(): void
    {
        $sent = $this->makeAwaitingFillContract([
            ['key' => 'parent_lastname', 'label' => 'Фамилия', 'required' => true],
        ]);
        $sent->update([
            'status' => Contract::STATUS_SENT,
            'fill_expires_at' => Carbon::parse('2026-09-18 15:00:00', 'Europe/Moscow'),
        ]);

        $signed = $this->makeAwaitingFillContract([
            ['key' => 'parent_lastname', 'label' => 'Фамилия', 'required' => true],
        ]);
        $signed->update([
            'status' => Contract::STATUS_SIGNED,
            'fill_expires_at' => Carbon::parse('2026-09-18 15:00:00', 'Europe/Moscow'),
        ]);

        $expiredFill = $this->makeAwaitingFillContract([
            ['key' => 'parent_lastname', 'label' => 'Фамилия', 'required' => true],
        ]);
        $expiredFill->update([
            'fill_expires_at' => Carbon::parse('2026-09-12 08:00:00', 'Europe/Moscow'),
        ]);

        $html = $this->get(route('account.documents.index'))->assertOk()->getContent();

        $sentCard = $this->cardAroundContract($html, $sent->id);
        $this->assertStringContainsString(Contract::CLIENT_FILL_REMAINING_CAPTION, $sentCard);
        $this->assertStringContainsString('Осталось 6 дней', $sentCard);

        $signedCard = $this->cardAroundContract($html, $signed->id);
        $this->assertStringNotContainsString(Contract::CLIENT_FILL_REMAINING_CAPTION, $signedCard);
        $this->assertStringNotContainsString('Осталось 6 дней', $signedCard);

        $expiredCard = $this->cardAroundContract($html, $expiredFill->id);
        $this->assertStringNotContainsString(Contract::CLIENT_FILL_REMAINING_CAPTION, $expiredCard);
        $this->assertStringNotContainsString('Осталось', $expiredCard);
        $this->assertStringContainsString(Contract::CLIENT_FILL_EXPIRED_NOTICE, $expiredCard);
    }

    private function remainingDaysValueHtml(string $card): string
    {
        $captionPos = strpos($card, Contract::CLIENT_FILL_REMAINING_CAPTION);
        $this->assertNotFalse($captionPos, 'Подпись срока подписания не найдена');

        return substr($card, $captionPos, 400);
    }

    private function cardAroundContract(string $html, int $id): string
    {
        $marker = 'data-id="' . $id . '"';
        $pos = strpos($html, $marker);
        $this->assertNotFalse($pos, 'Карточка договора #' . $id . ' не найдена');

        $prefix = substr($html, 0, $pos);
        $start = strrpos($prefix, 'col-12 col-md-6 col-lg-4');
        $this->assertNotFalse($start, 'Начало карточки #' . $id . ' не найдено');

        return substr($html, $start, ($pos + strlen($marker)) - $start);
    }
}
