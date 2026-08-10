<?php

namespace Tests\Feature\Crm\Contracts;

use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\ContractTemplateVersion;
use App\Models\PartnerLegalEntity;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class ContractStoreTemplateModeTest extends ContractsFeatureTestCase
{
    /** @test */
    public function store_template_mode_charges_balance_and_sets_awaiting_fill(): void
    {
        Mail::fake();
        config(['billing.contract_create_fee' => 70.00]);
        $this->partner->wallet_balance_cents = 10000;
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
        $this->assertSame(30.0, (float) ($this->partner->wallet_balance_cents / 100));

        Mail::assertSent(\App\Mail\ContractClientFillInvitationMail::class);
    }

    /** @test */
    public function store_template_with_legal_entity_fields_fails_when_group_has_no_legal_entity(): void
    {
        Mail::fake();
        config(['billing.contract_create_fee' => 70.00]);
        $this->partner->wallet_balance_cents = 10000;
        $this->partner->save();

        PartnerLegalEntity::factory()->for($this->partner)->create(['is_default' => true]);
        PartnerLegalEntity::factory()->for($this->partner)->create(['is_default' => false]);

        $team = Team::factory()->for($this->partner)->create([
            'legal_entity_id' => null,
        ]);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id'    => $team->id,
            'is_enabled' => 1,
            'email'      => 'client-le@example.com',
        ]);

        $template = $this->makeUsableTemplate([
            ['key' => 'legal_entity_inn', 'label' => 'ИНН', 'required' => false, 'prefill_source' => null],
            ['key' => 'parent_full_name', 'label' => 'ФИО', 'required' => true, 'prefill_source' => null],
        ]);

        $resp = $this->from('/client-contracts')->post('/client-contracts', [
            'creation_mode'        => Contract::CREATION_MODE_TEMPLATE,
            'user_id'              => $student->id,
            'group_id'             => $team->id,
            'contract_template_id' => $template->id,
        ]);

        $resp->assertStatus(302);
        $resp->assertSessionHasErrors('group_id');
        $this->assertStringContainsString(
            'не привязано юр. лицо',
            session('errors')->first('group_id'),
        );
        $this->assertSame(0, Contract::query()->count());
        $this->partner->refresh();
        $this->assertSame(10000, (int) $this->partner->wallet_balance_cents);
        Mail::assertNothingSent();
    }

    /** @test */
    public function store_template_with_legal_entity_fields_succeeds_when_group_has_legal_entity(): void
    {
        Mail::fake();
        config(['billing.contract_create_fee' => 70.00]);
        $this->partner->wallet_balance_cents = 10000;
        $this->partner->save();

        $entity = PartnerLegalEntity::factory()->for($this->partner)->create();
        $team = Team::factory()->for($this->partner)->create([
            'legal_entity_id' => $entity->id,
        ]);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id'    => $team->id,
            'is_enabled' => 1,
            'email'      => 'client-ok@example.com',
        ]);

        $template = $this->makeUsableTemplate([
            ['key' => 'legal_entity_inn', 'label' => 'ИНН', 'required' => false, 'prefill_source' => null],
        ]);

        $resp = $this->post('/client-contracts', [
            'creation_mode'        => Contract::CREATION_MODE_TEMPLATE,
            'user_id'              => $student->id,
            'group_id'             => $team->id,
            'contract_template_id' => $template->id,
        ]);

        $resp->assertStatus(302);
        $resp->assertSessionHasNoErrors();
        $contract = Contract::query()->firstOrFail();
        $this->assertSame((int) $team->id, (int) $contract->group_id);
        Mail::assertSent(\App\Mail\ContractClientFillInvitationMail::class);
    }

    /**
     * @param list<array<string, mixed>>|null $fieldsSchema
     */
    private function makeUsableTemplate(?array $fieldsSchema = null): ContractTemplate
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
            'fields_schema'        => $fieldsSchema ?? [
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
