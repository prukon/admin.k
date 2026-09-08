<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Contracts;

use App\Models\Contract;
use Illuminate\Support\Facades\Auth;

/**
 * Кнопка «Памятка» на /client-contracts: модалка со схематичным путём договора
 * в визуале timeline карточки платежа T‑Bank.
 */
final class ContractStatusMemoFeatureTest extends ContractsFeatureTestCase
{
    public function test_guest_is_redirected_from_contracts_index(): void
    {
        Auth::logout();

        $this->get(route('contracts.index'))->assertStatus(302);
    }

    public function test_user_without_contracts_view_gets_403_and_no_memo_markup(): void
    {
        $actor = $this->createUserWithoutPermission(self::PERM_CONTRACTS_VIEW, $this->partner);

        $this->actingAs($actor)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->get(route('contracts.index'))
            ->assertForbidden()
            ->assertDontSee('id="contractStatusMemoModal"', false)
            ->assertDontSee('data-bs-target="#contractStatusMemoModal"', false)
            ->assertDontSee('id="contractPathModal"', false)
            ->assertDontSee('js-contract-path-open', false);
    }

    public function test_contracts_view_sees_memo_button_standard_modal_and_all_status_labels(): void
    {
        $response = $this->get(route('contracts.index'));

        $response->assertOk()
            ->assertSee('data-bs-target="#contractStatusMemoModal"', false)
            ->assertSee('id="contractStatusMemoModal"', false)
            ->assertSee('id="contractStatusMemoModalLabel"', false)
            ->assertSee('>Памятка</span>', false)
            ->assertSee('>Памятка</h5>', false)
            ->assertSee('class="modal-dialog"', false)
            ->assertSee('contract-memo-timeline', false)
            ->assertSee('contract-memo-timeline__step--done', false)
            ->assertSee('contract-memo-timeline__step--active', false)
            ->assertSee('contract-memo-timeline__step--pending', false)
            ->assertSee('contract-memo-timeline__step--failed', false)
            ->assertSee('contract-memo-timeline__arrow', false)
            ->assertDontSee('Путь с готовым PDF', false)
            ->assertDontSee('Создание договора стоит', false)
            ->assertDontSee('Вернуть их можно только кнопкой', false)
            ->assertSee('Путь с формой клиенту', false)
            ->assertSee('Админ отправил договор родителю', false)
            ->assertSee('Родителю ушло письмо', false)
            ->assertSee('Сформировать договор', false)
            ->assertSee('Подписать договор', false)
            ->assertSee('Родитель открыл договор, но ещё не подписал.', false)
            ->assertSee('Родитель подписал договор. Можно скачать подписанный PDF.', false)
            ->assertDontSee('Родитель заполняет форму в кабинете', false)
            ->assertDontSee('Родитель сам отправляет SMS на подпись из кабинета', false)
            ->assertDontSee('Клиент открыл документ, ещё не подписал.', false)
            ->assertDontSee('Клиент подписал. Можно скачать подписанный PDF.', false)
            ->assertSee('Другие статусы', false)
            ->assertSee('70', false)
            ->assertSee('#contractStatusMemoModal .modal-dialog', false)
            ->assertSee('max-width: min(1100px, 96vw)', false)
            ->assertSee('js-contract-path-open', false)
            ->assertSee('(посмотреть)', false)
            ->assertSee('id="contractPathModal"', false)
            ->assertSee('id="contractPathModalBody"', false)
            ->assertSee('renderContractPathTimeline', false);

        $html = (string) $response->getContent();
        $start = strpos($html, 'id="contractStatusMemoModal"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="contractPathModal"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringNotContainsString('modal-xl', $chunk);
        $this->assertStringNotContainsString('modal-fullscreen', $chunk);
        $this->assertStringNotContainsString('modal-lg', $chunk);

        foreach (Contract::$STATUS_RU as $label) {
            $this->assertStringContainsString($label, $chunk);
        }
    }

    public function test_templates_tab_does_not_show_status_memo(): void
    {
        $this->get(route('contract-templates.index'))
            ->assertOk()
            ->assertDontSee('id="contractStatusMemoModal"', false)
            ->assertDontSee('data-bs-target="#contractStatusMemoModal"', false)
            ->assertDontSee('id="contractPathModal"', false)
            ->assertDontSee('js-contract-path-open', false);
    }
}
