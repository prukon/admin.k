<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Account;

use App\Models\Contract;
use App\Models\ParentProfile;
use App\Services\Contracts\ContractTemplatePrefillSources;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Crm\Account\Concerns\InteractsWithAccountContractFill;
use Tests\Feature\Crm\CrmTestCase;

/**
 * UX модалки заполнения: подпись «Паспорт (серия и номер)» и fallback Email
 * (родителю пусто → email ученика). Оба JS-триггера открытия грузят тот же GET /fill.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class AccountContractFillPassportEmailUxFeatureTest extends CrmTestCase
{
    use InteractsWithAccountContractFill;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useAccountContractFillStorage();
        $this->withSession($this->accountDocumentsSession());
    }

    public function test_first_open_shows_passport_series_and_number_label_not_short_passport(): void
    {
        $contract = $this->makePassportAndEmailContract();

        $html = $this->getContractFillModalHtml($contract);

        $this->assertStringContainsString('Паспорт (серия и номер)', $html);
        $this->assertDoesNotMatchRegularExpression(
            '/>\s*Паспорт\s*</u',
            $html,
            'Короткая подпись «Паспорт» не должна оставаться в модалке',
        );
        $this->assertStringContainsString('Паспорт, кем и когда выдан', $html);
    }

    public function test_old_schema_label_without_prefill_source_still_shows_series_and_number(): void
    {
        $contract = $this->makeAwaitingFillContract(
            [
                ['key' => 'parent_passport', 'label' => 'Родитель: паспорт', 'required' => true],
            ],
            ['parent_passport'],
        );

        $html = $this->getContractFillModalHtml($contract);

        $this->assertStringContainsString('Паспорт (серия и номер)', $html);
        $this->assertDoesNotMatchRegularExpression('/>\s*Паспорт\s*</u', $html);
    }

    public function test_issued_passport_field_keeps_its_own_label(): void
    {
        $contract = $this->makeAwaitingFillContract(
            [
                [
                    'key'   => 'parent_passport_issued',
                    'label' => 'Родитель: паспорт, кем и когда выдан',
                    'required' => true,
                    'prefill_source' => ContractTemplatePrefillSources::PARENT_PASSPORT_ISSUED,
                ],
            ],
            ['parent_passport_issued'],
        );

        $html = $this->getContractFillModalHtml($contract);

        $this->assertStringContainsString('Паспорт, кем и когда выдан', $html);
        $this->assertStringNotContainsString('Паспорт (серия и номер)', $html);
    }

    public function test_empty_parent_email_is_prefilled_from_student_on_first_open(): void
    {
        $this->linkParentWithEmail(null);
        $this->user->forceFill(['email' => 'student-fallback@example.com'])->save();

        $html = $this->getContractFillModalHtml($this->makePassportAndEmailContract());

        $this->assertFillInputValue($html, 'parent_email', 'student-fallback@example.com');
    }

    public function test_second_fill_request_still_prefills_student_email_when_parent_email_empty(): void
    {
        $this->linkParentWithEmail(null);
        $this->user->forceFill(['email' => 'again-student@example.com'])->save();
        $contract = $this->makePassportAndEmailContract();

        $first = $this->getContractFillModalHtml($contract);
        $second = $this->getContractFillModalHtml($contract);

        $this->assertFillInputValue($first, 'parent_email', 'again-student@example.com');
        $this->assertFillInputValue($second, 'parent_email', 'again-student@example.com');
    }

    public function test_parent_email_wins_and_student_email_is_not_forced(): void
    {
        $this->linkParentWithEmail('parent-kept@example.com');
        $this->user->forceFill(['email' => 'student-ignored@example.com'])->save();

        $html = $this->getContractFillModalHtml($this->makePassportAndEmailContract());

        $this->assertFillInputValue($html, 'parent_email', 'parent-kept@example.com');
        $this->assertStringNotContainsString('value="student-ignored@example.com"', $html);
    }

    public function test_email_stays_empty_when_parent_and_student_emails_are_empty(): void
    {
        $this->linkParentWithEmail(null);
        $this->user->forceFill(['email' => null])->save();

        $html = $this->getContractFillModalHtml($this->makePassportAndEmailContract());

        $this->assertFillInputValue($html, 'parent_email', '');
    }

    public function test_whitespace_parent_email_falls_back_to_student(): void
    {
        $this->linkParentWithEmail('   ');
        $this->user->forceFill(['email' => 'trimmed-fallback@example.com'])->save();

        $html = $this->getContractFillModalHtml($this->makePassportAndEmailContract());

        $this->assertFillInputValue($html, 'parent_email', 'trimmed-fallback@example.com');
    }

    public function test_student_without_parent_profile_still_gets_own_email_in_parent_email_field(): void
    {
        $this->user->forceFill(['parent_id' => null, 'email' => 'orphan@example.com'])->save();

        $html = $this->getContractFillModalHtml($this->makePassportAndEmailContract());

        $this->assertFillInputValue($html, 'parent_email', 'orphan@example.com');
    }

    public function test_edit_mode_keeps_saved_parent_email_and_does_not_replace_with_student(): void
    {
        $this->linkParentWithEmail('saved-parent@example.com');
        $this->user->forceFill(['email' => 'student-later@example.com'])->save();

        $contract = $this->makeDraftEditableContract([
            'parent_email' => 'saved-parent@example.com',
        ]);

        $html = $this->getContractFillModalHtml($contract, 'edit');

        $this->assertFillInputValue($html, 'parent_email', 'saved-parent@example.com');
        $this->assertStringNotContainsString('value="student-later@example.com"', $html);
        $this->assertStringContainsString('Паспорт (серия и номер)', $html);
    }

    public function test_documents_index_exposes_both_fill_and_edit_open_triggers(): void
    {
        $awaiting = $this->makePassportAndEmailContract();
        $draft = $this->makeDraftEditableContract();

        $html = (string) $this->get(route('account.documents.index'))->assertOk()->getContent();

        $this->assertStringContainsString('js-open-contract-fill', $html);
        $this->assertStringContainsString('data-contract-id="' . $awaiting->id . '"', $html);
        $this->assertStringContainsString('js-open-contract-fill-edit', $html);
        $this->assertStringContainsString('data-contract-id="' . $draft->id . '"', $html);
        $this->assertStringContainsString('Заполнить договор', $html);
        $this->assertStringContainsString('Изменить', $html);
    }

    public function test_generate_saves_prefilled_student_email_into_parent_profile(): void
    {
        $parent = $this->linkParentWithEmail(null);
        $this->user->forceFill(['email' => 'sync-from-student@example.com'])->save();
        $contract = $this->makePassportAndEmailContract();

        $this->post(route('account.documents.generate', $contract), [
            'fields' => [
                'parent_passport' => '4010 123456',
                'parent_email'    => 'sync-from-student@example.com',
            ],
        ])->assertRedirect(route('account.documents.index', ['fill' => $contract->id]));

        $this->assertSame('sync-from-student@example.com', $parent->fresh()->email);
        $this->assertSame('sync-from-student@example.com', $contract->fresh()->filled_data['parent_email'] ?? null);
    }

    /**
     * @param array<string, string> $filledData
     */
    private function makeDraftEditableContract(array $filledData = []): Contract
    {
        $contract = $this->makePassportAndEmailContract();
        $pdfPath = 'documents/test/contract-' . $contract->id . '-filled.pdf';
        Storage::disk()->put($pdfPath, '%PDF-1.4 test');

        $contract->update([
            'status'          => Contract::STATUS_DRAFT,
            'source_pdf_path' => $pdfPath,
            'source_sha256'   => hash('sha256', '%PDF-1.4 test'),
            'filled_data'     => $filledData,
            'created_at'      => now()->subDay(),
        ]);

        return $contract->fresh();
    }

    private function makePassportAndEmailContract(): Contract
    {
        return $this->makeAwaitingFillContract(
            [
                [
                    'key'            => 'parent_passport',
                    'label'          => 'Родитель: паспорт',
                    'required'       => true,
                    'prefill_source' => ContractTemplatePrefillSources::PARENT_PASSPORT,
                ],
                [
                    'key'            => 'parent_passport_issued',
                    'label'          => 'Родитель: паспорт, кем и когда выдан',
                    'required'       => false,
                    'prefill_source' => ContractTemplatePrefillSources::PARENT_PASSPORT_ISSUED,
                ],
                [
                    'key'            => 'parent_email',
                    'label'          => 'Родитель: email',
                    'required'       => true,
                    'prefill_source' => ContractTemplatePrefillSources::PARENT_EMAIL,
                ],
            ],
            ['parent_passport', 'parent_passport_issued', 'parent_email'],
        );
    }

    private function linkParentWithEmail(?string $email): ParentProfile
    {
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'email'      => $email,
        ]);
        $this->user->forceFill(['parent_id' => $parent->id])->save();

        return $parent;
    }

    private function assertFillInputValue(string $html, string $fieldKey, string $expected): void
    {
        $this->assertMatchesRegularExpression(
            '/name="fields\[' . preg_quote($fieldKey, '/') . ']"[^>]*value="' . preg_quote($expected, '/') . '"/u',
            $html,
            'Ожидалось fields[' . $fieldKey . '] = «' . $expected . '»',
        );
    }
}
