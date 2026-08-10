<?php

namespace Tests\Feature\Crm\Account;

use App\Models\Contract;
use App\Services\Contracts\ContractPdfConverterInterface;
use App\Services\Contracts\DocxPlaceholderExtractor;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Crm\Account\Concerns\InteractsWithAccountContractFill;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Support\Contracts\CapturingFakeContractPdfConverter;

/**
 * Системный плейсхолдер {{contract_date}}: форма родителя, generate HTTP/AJAX,
 * UX-баг Word (скобки {{ в разных w:t) — все вхождения подставляются в DOCX.
 */
class AccountContractDatePlaceholderFeatureTest extends CrmTestCase
{
    use InteractsWithAccountContractFill;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useAccountContractFillStorage();
        $this->withSession($this->accountDocumentsSession());
        CapturingFakeContractPdfConverter::reset();
        $this->app->instance(ContractPdfConverterInterface::class, new CapturingFakeContractPdfConverter());
    }

    public function test_fill_form_hides_system_contract_date_field_from_parent(): void
    {
        $contract = $this->makeAwaitingFillContract(
            [
                ['key' => 'contract_date', 'label' => 'Дата договора', 'required' => false],
                ['key' => 'parent_lastname', 'label' => 'Фамилия', 'required' => true],
                ['key' => 'parent_firstname', 'label' => 'Имя', 'required' => true],
            ],
            ['contract_date', 'parent_full_name'],
        );

        $html = $this->getContractFillModalHtml($contract);

        $this->assertStringNotContainsString('name="fields[contract_date]"', $html);
        $this->assertStringContainsString('name="fields[parent_lastname]"', $html);
        $this->assertStringContainsString('Сформировать договор', $html);
    }

    public function test_documents_index_with_fill_query_renders_modal_shell_and_open_hooks(): void
    {
        $contract = $this->makeAwaitingFillContract(
            [
                ['key' => 'parent_lastname', 'label' => 'Фамилия', 'required' => true],
                ['key' => 'parent_firstname', 'label' => 'Имя', 'required' => true],
            ],
            ['parent_full_name', 'contract_date'],
        );

        $html = (string) $this->get(route('account.documents.index', ['fill' => $contract->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="contractFillModal"', $html);
        $this->assertStringContainsString('js-open-contract-fill', $html);
        $this->assertStringContainsString('loadContractFill', $html);
        $this->assertStringContainsString((string) $contract->id, $html);
    }

    public function test_guest_is_redirected_from_generate_when_contract_uses_contract_date(): void
    {
        $contract = $this->makeContractWithSplitBraceContractDates();

        Auth::logout();

        $this->post(route('account.documents.generate', $contract), [
            'fields' => [
                'parent_lastname'  => 'Иванов',
                'parent_firstname' => 'Иван',
            ],
        ])->assertRedirect(route('login'));
    }

    public function test_user_without_documents_permission_gets_403_on_generate(): void
    {
        $contract = $this->makeContractWithSplitBraceContractDates();
        $actor = $this->createUserWithoutPermission('account.documents.view', $this->partner);

        $this->actingAs($actor)
            ->withSession($this->accountDocumentsSession())
            ->post(route('account.documents.generate', $contract), [
                'fields' => [
                    'parent_lastname'  => 'Иванов',
                    'parent_firstname' => 'Иван',
                ],
            ])
            ->assertForbidden();
    }

    public function test_generate_ajax_returns_json_and_injects_today_contract_date_into_filled_data(): void
    {
        $contract = $this->makeAwaitingFillContract(
            [
                ['key' => 'contract_date', 'label' => 'Дата', 'required' => false],
                ['key' => 'parent_lastname', 'label' => 'Фамилия', 'required' => true],
                ['key' => 'parent_firstname', 'label' => 'Имя', 'required' => true],
            ],
            ['contract_date', 'parent_full_name'],
        );

        $today = now()->format('d.m.Y');

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->postJson(route('account.documents.generate', $contract), [
                'fields' => [
                    'parent_lastname'  => 'Сидоров',
                    'parent_firstname' => 'Пётр',
                ],
            ])
            ->assertOk()
            ->assertJsonStructure(['message', 'poll'])
            ->assertJsonPath('poll', false);

        $contract->refresh();
        $this->assertSame(Contract::STATUS_DRAFT, $contract->status);
        $this->assertSame($today, $contract->filled_data['contract_date'] ?? null);
        $this->assertSame((string) $contract->id, $contract->filled_data['contract_id'] ?? null);
        $this->assertSame('Сидоров Пётр', $contract->filled_data['parent_full_name'] ?? null);
        Storage::disk()->assertExists((string) $contract->source_pdf_path);
    }

    public function test_generate_non_ajax_redirects_to_documents_and_creates_pdf(): void
    {
        $contract = $this->makeAwaitingFillContract(
            [
                ['key' => 'contract_date', 'label' => 'Дата', 'required' => false],
                ['key' => 'parent_lastname', 'label' => 'Фамилия', 'required' => true],
                ['key' => 'parent_firstname', 'label' => 'Имя', 'required' => true],
            ],
            ['contract_date', 'parent_full_name'],
        );

        $this->from(route('account.documents.index', ['fill' => $contract->id]))
            ->post(route('account.documents.generate', $contract), [
                'fields' => [
                    'parent_lastname'  => 'Козлов',
                    'parent_firstname' => 'Илья',
                ],
            ])
            ->assertRedirect(route('account.documents.index', ['fill' => $contract->id]))
            ->assertSessionHas('success');

        $contract->refresh();
        $this->assertSame(Contract::STATUS_DRAFT, $contract->status);
        $this->assertNotNull($contract->source_pdf_path);
        $this->assertSame(now()->format('d.m.Y'), $contract->filled_data['contract_date'] ?? null);
    }

    public function test_generate_ajax_returns_422_with_field_errors_when_required_parent_name_missing(): void
    {
        $contract = $this->makeAwaitingFillContract(
            [
                ['key' => 'contract_date', 'label' => 'Дата', 'required' => false],
                ['key' => 'parent_lastname', 'label' => 'Фамилия', 'required' => true],
                ['key' => 'parent_firstname', 'label' => 'Имя', 'required' => true],
            ],
            ['contract_date', 'parent_full_name'],
        );

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->postJson(route('account.documents.generate', $contract), [
                'fields' => [
                    'parent_lastname' => '',
                    'parent_firstname' => 'Иван',
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['fields.parent_lastname']);

        $contract->refresh();
        $this->assertSame(Contract::STATUS_AWAITING_CLIENT_FILL, $contract->status);
        $this->assertNull($contract->source_pdf_path);
    }

    /**
     * UX-баг: Word рвёт {{ на отдельные { + { — до фикса подставлялось только первое вхождение.
     */
    public function test_generate_replaces_all_contract_date_occurrences_when_word_splits_opening_braces(): void
    {
        $contract = $this->makeContractWithSplitBraceContractDates();
        $today = now()->format('d.m.Y');

        $this->post(route('account.documents.generate', $contract), [
            'fields' => [
                'parent_lastname'  => 'Шилова',
                'parent_firstname' => 'Клим',
            ],
        ])->assertRedirect(route('account.documents.index', ['fill' => $contract->id]));

        $contract->refresh();
        $this->assertSame(Contract::STATUS_DRAFT, $contract->status);
        $this->assertSame($today, $contract->filled_data['contract_date'] ?? null);

        $filledXml = CapturingFakeContractPdfConverter::$lastDocumentXml;
        $this->assertNotNull($filledXml, 'Заполненный DOCX должен быть перехвачен конвертером');
        $this->assertSame(3, substr_count($filledXml, $today), 'Все три даты должны быть подставлены');
        $this->assertStringNotContainsString('contract_date', $filledXml);
        $this->assertSame(2, substr_count($filledXml, 'Шилова Клим'));
        $this->assertStringNotContainsString('{{parent_full_name}}', $filledXml);
        $this->assertStringNotContainsString('parent_full_name', $filledXml);
    }

    public function test_generate_still_replaces_intact_repeated_contract_date_placeholders(): void
    {
        $documentXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:body><w:p><w:r><w:t>'
            . 'А {{contract_date}} Б {{contract_date}} В {{parent_full_name}}'
            . '</w:t></w:r></w:p></w:body></w:document>';

        $contract = $this->makeAwaitingFillContractWithDocumentXml(
            [
                ['key' => 'contract_date', 'label' => 'Дата', 'required' => false],
                ['key' => 'parent_lastname', 'label' => 'Фамилия', 'required' => true],
                ['key' => 'parent_firstname', 'label' => 'Имя', 'required' => true],
            ],
            $documentXml,
        );

        $today = now()->format('d.m.Y');

        $this->post(route('account.documents.generate', $contract), [
            'fields' => [
                'parent_lastname'  => 'Тестов',
                'parent_firstname' => 'Иван',
            ],
        ])->assertRedirect();

        $filledXml = CapturingFakeContractPdfConverter::$lastDocumentXml;
        $this->assertNotNull($filledXml);
        $this->assertSame(2, substr_count($filledXml, $today));
        $this->assertStringNotContainsString('contract_date', $filledXml);
    }

    public function test_extractor_finds_contract_date_when_opening_braces_are_split_in_docx(): void
    {
        $this->useAccountContractFillStorage();

        $rel = $this->createFillTestDocxFromDocumentXml($this->splitBraceContractDateDocumentXml());
        $abs = Storage::disk()->path($rel);

        $keys = (new DocxPlaceholderExtractor())->extractFromPath($abs);

        $this->assertContains('contract_date', $keys);
        $this->assertContains('parent_full_name', $keys);
    }

    public function test_after_generate_fill_json_shows_sign_block_not_contract_date_input(): void
    {
        $contract = $this->makeContractWithSplitBraceContractDates();

        $this->post(route('account.documents.generate', $contract), [
            'fields' => [
                'parent_lastname'  => 'Иванов',
                'parent_firstname' => 'Иван',
            ],
        ])->assertRedirect();

        $html = $this->getContractFillModalHtml($contract->fresh());

        $this->assertStringContainsString('contract-fill-sign-block', $html);
        $this->assertStringContainsString('Скачать', $html);
        $this->assertStringNotContainsString('name="fields[contract_date]"', $html);
        $this->assertStringNotContainsString('Сформировать договор', $html);
    }

    private function makeContractWithSplitBraceContractDates(): Contract
    {
        return $this->makeAwaitingFillContractWithDocumentXml(
            [
                ['key' => 'contract_date', 'label' => 'Дата договора', 'required' => false],
                ['key' => 'parent_lastname', 'label' => 'Фамилия', 'required' => true],
                ['key' => 'parent_firstname', 'label' => 'Имя', 'required' => true],
            ],
            $this->splitBraceContractDateDocumentXml(),
        );
    }

    /**
     * Разметка как в реальном Word: первое {{contract_date}} цельное,
     * следующие — { и { в разных run (+ proofErr).
     */
    private function splitBraceContractDateDocumentXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:body>'
            . '<w:p><w:r><w:t>{{</w:t></w:r><w:proofErr w:type="spellStart"/>'
            . '<w:r><w:t>contract_date</w:t></w:r><w:proofErr w:type="spellEnd"/>'
            . '<w:r><w:t>}}</w:t></w:r></w:p>'
            . '<w:p><w:r><w:t>от </w:t></w:r>'
            . '<w:r><w:t>{</w:t></w:r><w:proofErr w:type="gramEnd"/>'
            . '<w:r><w:t>{</w:t></w:r><w:proofErr w:type="spellStart"/>'
            . '<w:r><w:t>contract_date</w:t></w:r><w:proofErr w:type="spellEnd"/>'
            . '<w:r><w:t>}}</w:t></w:r></w:p>'
            . '<w:p><w:r><w:t>{</w:t></w:r><w:r><w:t>{</w:t></w:r>'
            . '<w:r><w:t>contract_date</w:t></w:r>'
            . '<w:r><w:t>}</w:t></w:r><w:r><w:t>}</w:t></w:r></w:p>'
            . '<w:p><w:r><w:t>{{parent_full_name}} и снова {{parent_full_name}}</w:t></w:r></w:p>'
            . '</w:body></w:document>';
    }
}
