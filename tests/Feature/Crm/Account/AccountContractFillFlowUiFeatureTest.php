<?php

namespace Tests\Feature\Crm\Account;

use App\Models\Contract;
use Tests\Feature\Crm\Account\Concerns\InteractsWithAccountContractFill;
use Tests\Feature\Crm\CrmTestCase;

/**
 * UI и состояния модалки заполнения договора родителем после generate / во время генерации.
 */
class AccountContractFillFlowUiFeatureTest extends CrmTestCase
{
    use InteractsWithAccountContractFill;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useAccountContractFillStorage();
        config(['queue.default' => 'sync']);
        $this->withSession($this->accountDocumentsSession());
    }

    public function test_fill_json_shows_parent_and_child_panels_for_split_fields(): void
    {
        $contract = $this->makeAwaitingFillContract(
            [
                ['key' => 'parent_lastname', 'label' => 'Фамилия', 'required' => true],
                ['key' => 'child_lastname', 'label' => 'Фамилия ребёнка', 'required' => true],
            ],
            ['parent_full_name', 'child_full_name'],
        );

        $html = $this->getContractFillModalHtml($contract);

        $this->assertStringContainsString('contract-fill-panel--parent', $html);
        $this->assertStringContainsString('contract-fill-panel--child', $html);
        $this->assertStringContainsString('Заказчик по договору', $html);
        $this->assertStringContainsString('>Ученик<', $html);
    }

    public function test_fill_json_after_generate_shows_sign_block_with_download_link(): void
    {
        $contract = $this->makeAwaitingFillContract(
            [
                ['key' => 'parent_lastname', 'label' => 'Фамилия', 'required' => true],
                ['key' => 'parent_firstname', 'label' => 'Имя', 'required' => true],
            ],
            ['parent_full_name'],
        );

        $this->post(route('account.documents.generate', $contract), [
            'fields' => [
                'parent_lastname'  => 'Иванов',
                'parent_firstname' => 'Иван',
            ],
        ])->assertRedirect(route('account.documents.index', ['fill' => $contract->id]));

        $contract->refresh();
        $html = $this->getContractFillModalHtml($contract);

        $this->assertStringContainsString('contract-fill-sign-block', $html);
        $this->assertStringContainsString('Договор сформирован', $html);
        $this->assertStringContainsString('name="signer_lastname"', $html);
        $this->assertStringContainsString('name="signer_phone"', $html);
        $this->assertStringContainsString('Подписать договор (отправить SMS)', $html);
        $this->assertStringContainsString(route('account.documents.downloadOriginal', $contract), $html);
        $this->assertStringNotContainsString('Сформировать договор', $html);
    }

    public function test_fill_json_returns_poll_flag_while_pdf_is_generating(): void
    {
        $contract = $this->makeAwaitingFillContract(
            [['key' => 'parent_lastname', 'label' => 'Фамилия', 'required' => true]],
        );
        $contract->update(['status' => Contract::STATUS_GENERATING_PDF]);

        $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->getJson(route('account.documents.fill', $contract))
            ->assertOk();

        $this->assertTrue((bool) $response->json('poll'));
        $html = (string) $response->json('html');
        $this->assertStringContainsString('data-contract-fill-poll="1"', $html);
        $this->assertStringContainsString('Формируем договор', $html);
    }

    public function test_fill_json_shows_generation_error_from_filled_data_on_poll(): void
    {
        $contract = $this->makeAwaitingFillContract(
            [['key' => 'parent_lastname', 'label' => 'Фамилия', 'required' => true]],
        );
        $contract->update([
            'filled_data' => ['_generation_error' => 'Не удалось сформировать PDF.'],
        ]);

        $html = (string) $this->withSession($this->accountDocumentsSession())
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->getJson(route('account.documents.fill', ['contract' => $contract, 'poll' => 1]))
            ->assertOk()
            ->json('html');

        $this->assertStringContainsString('Не удалось сформировать PDF.', $html);
    }

    public function test_generate_without_ajax_still_redirects_with_success(): void
    {
        $contract = $this->makeAwaitingFillContract(
            [
                ['key' => 'parent_lastname', 'label' => 'Фамилия', 'required' => true],
                ['key' => 'parent_firstname', 'label' => 'Имя', 'required' => true],
            ],
            ['parent_full_name'],
        );

        $this->post(route('account.documents.generate', $contract), [
            'fields' => [
                'parent_lastname'  => 'Иванов',
                'parent_firstname' => 'Иван',
            ],
        ])
            ->assertRedirect(route('account.documents.index', ['fill' => $contract->id]))
            ->assertSessionHas('success');
    }

    public function test_generate_ajax_returns_json_and_poll_flag(): void
    {
        $contract = $this->makeAwaitingFillContract(
            [
                ['key' => 'parent_lastname', 'label' => 'Фамилия', 'required' => true],
                ['key' => 'parent_firstname', 'label' => 'Имя', 'required' => true],
            ],
            ['parent_full_name'],
        );

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->postJson(route('account.documents.generate', $contract), [
                'fields' => [
                    'parent_lastname'  => 'Иванов',
                    'parent_firstname' => 'Иван',
                ],
            ])
            ->assertOk()
            ->assertJsonStructure(['message', 'poll']);

        $contract->refresh();
        $this->assertSame(Contract::STATUS_DRAFT, $contract->status);
    }

    public function test_fill_json_returns_422_when_contract_already_sent(): void
    {
        $contract = $this->makeAwaitingFillContract(
            [['key' => 'parent_lastname', 'label' => 'Фамилия', 'required' => true]],
        );
        $contract->update([
            'status'          => Contract::STATUS_SENT,
            'source_pdf_path' => 'documents/sent-' . uniqid() . '.pdf',
            'source_sha256'   => str_repeat('a', 64),
        ]);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('account.documents.fill', $contract))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Договор недоступен для заполнения.');
    }

    public function test_documents_index_with_fill_query_renders_modal_shell(): void
    {
        $contract = $this->makeAwaitingFillContract(
            [['key' => 'parent_lastname', 'label' => 'Фамилия', 'required' => true]],
        );

        $html = (string) $this->get(route('account.documents.index', ['fill' => $contract->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="contractFillModal"', $html);
        $this->assertStringContainsString('loadContractFill', $html);
        $this->assertStringContainsString((string) $contract->id, $html);
    }
}
