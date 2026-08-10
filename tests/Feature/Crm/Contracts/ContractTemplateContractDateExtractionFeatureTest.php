<?php

namespace Tests\Feature\Crm\Contracts;

use App\Models\ContractTemplate;
use App\Services\Contracts\ContractTemplateVariablePresets;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use ZipArchive;

/**
 * Загрузка DOCX шаблона: {{contract_date}} извлекается даже при разрыве скобок Word.
 */
class ContractTemplateContractDateExtractionFeatureTest extends ContractsFeatureTestCase
{
    /** @test */
    public function store_docx_with_word_split_contract_date_braces_adds_system_schema_field(): void
    {
        $this->post(route('contract-templates.store'), [
            'title' => 'Шаблон с датой',
            'docx'  => $this->docxWithSplitContractDateBraces(),
        ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('contract-templates.index'));

        $template = ContractTemplate::query()
            ->where('partner_id', $this->partner->id)
            ->where('title', 'Шаблон с датой')
            ->firstOrFail();

        $schema = collect($template->currentVersion->fields_schema ?? [])->keyBy('key');

        $this->assertTrue($schema->has('contract_date'));
        $this->assertTrue($schema->has('parent_full_name'));
        $this->assertSame(
            ContractTemplateVariablePresets::FILL_MODE_SYSTEM,
            ContractTemplateVariablePresets::fillModeForKey('contract_date'),
        );
        $this->assertFalse((bool) ($schema['contract_date']['required'] ?? true));
    }

    /** @test */
    public function guest_cannot_store_template_with_contract_date_docx(): void
    {
        Auth::logout();

        $this->post(route('contract-templates.store'), [
            'title' => 'Гость',
            'docx'  => $this->docxWithSplitContractDateBraces(),
        ])->assertRedirect(route('login'));

        $this->assertSame(0, ContractTemplate::query()->where('title', 'Гость')->count());
    }

    /** @test */
    public function user_without_contracts_view_gets_403_on_store(): void
    {
        $actor = $this->createUserWithoutPermission(self::PERM_CONTRACTS_VIEW, $this->partner);

        $this->actingAs($actor)
            ->withSession([
                'current_partner' => $this->partner->id,
                '2fa:passed'      => true,
            ])
            ->post(route('contract-templates.store'), [
                'title' => 'Без права',
                'docx'  => $this->docxWithSplitContractDateBraces(),
            ])
            ->assertForbidden();
    }

    private function docxWithSplitContractDateBraces(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'docx_split_') . '.docx';
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"></Types>');
        $zip->addFromString(
            'word/document.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:body>'
            . '<w:p><w:r><w:t>{</w:t></w:r><w:r><w:t>{</w:t></w:r>'
            . '<w:r><w:t>contract_date</w:t></w:r><w:r><w:t>}}</w:t></w:r></w:p>'
            . '<w:p><w:r><w:t>{{parent_full_name}}</w:t></w:r></w:p>'
            . '</w:body></w:document>'
        );
        $zip->close();

        return new UploadedFile(
            $path,
            'split-date.docx',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            null,
            true
        );
    }
}
