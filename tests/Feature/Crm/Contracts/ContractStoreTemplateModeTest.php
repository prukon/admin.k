<?php

namespace Tests\Feature\Crm\Contracts;

use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\ContractTemplateVersion;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class ContractStoreTemplateModeTest extends ContractsFeatureTestCase
{
    /** @test */
    public function store_template_mode_charges_balance_and_sets_awaiting_fill(): void
    {
        Mail::fake();
        config(['billing.contract_create_fee' => 70.00]);
        $this->partner->wallet_balance = 100;
        $this->partner->save();

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
            'email'      => 'client@example.com',
        ]);

        $template = $this->makeUsableTemplate();

        $resp = $this->post('/client-contracts', [
            'creation_mode'          => Contract::CREATION_MODE_TEMPLATE,
            'user_id'                => $student->id,
            'contract_template_id'   => $template->id,
        ]);

        $resp->assertStatus(302);
        $contract = Contract::query()->firstOrFail();

        $this->assertSame(Contract::CREATION_MODE_TEMPLATE, $contract->creation_mode);
        $this->assertSame(Contract::STATUS_AWAITING_CLIENT_FILL, $contract->status);
        $this->assertNull($contract->source_pdf_path);
        $this->assertNotNull($contract->fill_expires_at);

        $this->partner->refresh();
        $this->assertSame(30.0, (float) $this->partner->wallet_balance);

        Mail::assertSent(\App\Mail\ContractClientFillInvitationMail::class);
    }

    private function makeUsableTemplate(): ContractTemplate
    {
        $template = ContractTemplate::create([
            'partner_id'  => $this->partner->id,
            'title'       => 'Test template',
            'is_archived' => false,
        ]);

        $version = ContractTemplateVersion::create([
            'contract_template_id' => $template->id,
            'version'              => 1,
            'docx_path'            => 'contract-templates/test.docx',
            'docx_sha256'          => str_repeat('a', 64),
            'fields_schema'        => [
                ['key' => 'fio', 'label' => 'ФИО', 'required' => true, 'prefill_source' => null],
            ],
            'email_subject'        => 'Тема',
            'email_body_html'      => '<p>Текст</p>',
        ]);

        $template->current_version_id = $version->id;
        $template->save();

        return $template->fresh();
    }
}
