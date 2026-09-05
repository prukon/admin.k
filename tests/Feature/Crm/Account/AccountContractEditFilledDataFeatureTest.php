<?php

namespace Tests\Feature\Crm\Account;

use App\Enums\AuditEvent;
use App\Models\Contract;
use Tests\Feature\Crm\Account\Concerns\InteractsWithAccountContractFill;
use Tests\Feature\Crm\CrmTestCase;

class AccountContractEditFilledDataFeatureTest extends CrmTestCase
{
    use InteractsWithAccountContractFill;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useAccountContractFillStorage();
        config(['queue.default' => 'sync']);
        $this->withSession($this->accountDocumentsSession());
    }

    public function test_documents_page_shows_edit_button_for_draft_with_pdf(): void
    {
        $contract = $this->makeDraftContractWithPdf();

        $this->get(route('account.documents.index'))
            ->assertOk()
            ->assertSee('js-open-contract-fill-edit', false)
            ->assertSee('Изменить');
    }

    public function test_edit_unavailable_after_podpislon_upload(): void
    {
        $contract = $this->makeDraftContractWithPdf();
        $contract->update(['provider_doc_id' => 'pkg-123']);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->getJson(route('account.documents.fill', ['contract' => $contract, 'mode' => 'edit']))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Договор уже отправлен на подпись и недоступен для изменения.');
    }

    public function test_fill_json_edit_mode_shows_prefilled_form_without_sign_block(): void
    {
        $contract = $this->makeDraftContractWithPdf([
            'parent_lastname'  => 'Иванов',
            'parent_firstname' => 'Иван',
            'parent_full_name' => 'Иванов Иван',
        ]);

        $html = $this->getContractFillModalHtml($contract, 'edit');

        $this->assertStringContainsString('Сохранить и обновить PDF', $html);
        $this->assertStringContainsString('value="Иванов"', $html);
        $this->assertStringContainsString('value="Иван"', $html);
        $this->assertStringNotContainsString('contract-fill-sign-block', $html);
        $this->assertStringNotContainsString('Подписать договор (отправить SMS)', $html);
    }

    public function test_regenerate_updates_pdf_filled_data_and_writes_my_log(): void
    {
        $contract = $this->makeDraftContractWithPdf([
            'parent_lastname'  => 'Иванов',
            'parent_firstname' => 'Иван',
            'parent_full_name' => 'Иванов Иван',
        ]);

        $oldPdfPath = $contract->source_pdf_path;
        $oldSha = $contract->source_sha256;

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->postJson(route('account.documents.generate', $contract), [
                'fields' => [
                    'parent_lastname'  => 'Петров',
                    'parent_firstname' => 'Пётр',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('poll', false);

        $contract->refresh();

        $this->assertSame(Contract::STATUS_DRAFT, $contract->status);
        $this->assertSame('Петров Пётр', $contract->filled_data['parent_full_name'] ?? null);
        $this->assertNotSame($oldSha, $contract->source_sha256);
        \Illuminate\Support\Facades\Storage::disk()->assertMissing($oldPdfPath);
        \Illuminate\Support\Facades\Storage::disk()->assertExists($contract->source_pdf_path);

        $this->assertDatabaseHas('my_logs', [
            'event'       => AuditEvent::ContractPdfRegeneratedByClient->value,
            'user_id'     => $this->user->id,
            'author_id'   => $this->user->id,
            'target_type' => Contract::class,
            'target_id'   => $contract->id,
        ]);
    }

    public function test_edit_unavailable_after_30_days(): void
    {
        $contract = $this->makeDraftContractWithPdf();
        $contract->update(['created_at' => now()->subDays(31)]);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->getJson(route('account.documents.fill', ['contract' => $contract, 'mode' => 'edit']))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Срок изменения данных договора истёк. Обратитесь в организацию.');
    }

    public function test_documents_page_hides_edit_when_contract_is_opened(): void
    {
        $contract = $this->makeDraftContractWithPdf();
        $contract->update([
            'status' => Contract::STATUS_OPENED,
            'provider_doc_id' => 'pkg-opened-edit',
        ]);

        $html = $this->get(route('account.documents.index'))->assertOk()->getContent();

        $this->assertDoesNotMatchRegularExpression(
            '/js-open-contract-fill-edit[^>]*data-contract-id="'.$contract->id.'"|data-contract-id="'.$contract->id.'"[^>]*js-open-contract-fill-edit/',
            $html
        );
    }

    public function test_fill_json_is_unavailable_when_opened_or_revoked(): void
    {
        $opened = $this->makeDraftContractWithPdf();
        $opened->update([
            'status' => Contract::STATUS_OPENED,
            'provider_doc_id' => 'pkg-opened-fill',
        ]);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->getJson(route('account.documents.fill', $opened))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Договор недоступен для заполнения.');

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->getJson(route('account.documents.fill', ['contract' => $opened, 'mode' => 'edit']))
            ->assertStatus(422);

        $revoked = $this->makeDraftContractWithPdf();
        $revoked->update(['status' => Contract::STATUS_REVOKED]);

        $html = $this->get(route('account.documents.index'))->assertOk()->getContent();
        $this->assertDoesNotMatchRegularExpression(
            '/js-open-contract-fill-edit[^>]*data-contract-id="'.$revoked->id.'"|data-contract-id="'.$revoked->id.'"[^>]*js-open-contract-fill-edit/',
            $html
        );

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->getJson(route('account.documents.fill', $revoked))
            ->assertStatus(422);
    }

    /**
     * @param array<string, string> $filledData
     */
    private function makeDraftContractWithPdf(array $filledData = []): Contract
    {
        $contract = $this->makeAwaitingFillContract(
            [
                ['key' => 'parent_lastname', 'label' => 'Фамилия', 'required' => true],
                ['key' => 'parent_firstname', 'label' => 'Имя', 'required' => true],
            ],
            ['parent_full_name'],
        );

        $pdfPath = 'documents/test/contract-' . $contract->id . '-filled.pdf';
        \Illuminate\Support\Facades\Storage::disk()->put($pdfPath, '%PDF-1.4 test');

        $contract->update([
            'status'          => Contract::STATUS_DRAFT,
            'source_pdf_path' => $pdfPath,
            'source_sha256'   => hash('sha256', '%PDF-1.4 test'),
            'filled_data'     => $filledData,
            'created_at'      => now()->subDay(),
        ]);

        return $contract->fresh();
    }

    private function getContractFillModalHtml(Contract $contract, ?string $mode = null): string
    {
        $url = route('account.documents.fill', $contract);
        if ($mode !== null) {
            $url .= '?mode=' . urlencode($mode);
        }

        return (string) $this->withSession($this->accountDocumentsSession())
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->getJson($url)
            ->assertOk()
            ->json('html');
    }
}
