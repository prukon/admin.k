<?php

namespace Tests\Feature\Jobs;

use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\ContractTemplateVersion;
use App\Services\Contracts\ContractPdfGenerationService;
use Tests\Feature\Crm\CrmTestCase;

class GenerateContractPdfJobTest extends CrmTestCase
{
    public function test_fail_generation_restores_form_with_error_message(): void
    {
        $contract = $this->makeGeneratingContract();

        app(ContractPdfGenerationService::class)->failGeneration(
            $contract->id,
            'LibreOffice не смог сконвертировать DOCX в PDF.',
            ['parent_full_name' => 'Иванов Иван'],
            $this->user->id,
        );

        $contract->refresh();

        $this->assertSame(Contract::STATUS_AWAITING_CLIENT_FILL, $contract->status);
        $this->assertSame('LibreOffice не смог сконвертировать DOCX в PDF.', $contract->pdfGenerationError());
        $this->assertSame('Иванов Иван', $contract->filled_data['parent_full_name'] ?? null);
    }

    private function makeGeneratingContract(): Contract
    {
        $template = ContractTemplate::create([
            'partner_id'  => $this->partner->id,
            'title'       => 'Job test template',
            'is_archived' => false,
        ]);

        $version = ContractTemplateVersion::create([
            'contract_template_id' => $template->id,
            'version'              => 1,
            'docx_path'            => 'contract-templates/job-test.docx',
            'docx_sha256'          => str_repeat('c', 64),
            'fields_schema'        => [
                [
                    'key'            => 'parent_full_name',
                    'label'          => 'ФИО родителя',
                    'required'       => true,
                    'prefill_source' => null,
                ],
            ],
        ]);
        $template->current_version_id = $version->id;
        $template->save();

        return Contract::create([
            'school_id'                    => $this->partner->id,
            'user_id'                      => $this->user->id,
            'group_id'                     => null,
            'creation_mode'                => Contract::CREATION_MODE_TEMPLATE,
            'contract_template_version_id' => $version->id,
            'source_pdf_path'              => null,
            'source_sha256'                => null,
            'status'                       => Contract::STATUS_GENERATING_PDF,
            'filled_data'                  => ['parent_full_name' => 'Иванов Иван'],
            'fill_expires_at'              => now()->addDays(7),
            'provider'                     => 'podpislon',
        ]);
    }
}
