<?php

namespace Tests\Feature\Crm\Contracts;

use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Services\Signatures\SignatureProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Mockery;

class ContractTemplateWorkflowFeatureTest extends ContractsFeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['contracts.pdf_converter' => 'fake']);
        config(['billing.contract_create_fee' => 70.00]);
        $this->partner->wallet_balance = 200;
        $this->partner->save();

        $this->user->email = 'client-workflow@example.com';
        $this->user->is_enabled = 1;
        $this->user->save();
    }

    /** @test */
    public function full_template_flow_admin_create_client_generate_and_sign(): void
    {
        Mail::fake();

        $storeTemplate = $this->post(route('contract-templates.store'), [
            'title'         => 'Оферта E2E',
            'docx'          => $this->fakeDocxUploadedFile(['fio_parent']),
            'email_subject' => 'Заполните договор',
        ]);
        $storeTemplate->assertSessionHasNoErrors();
        $storeTemplate->assertRedirect();

        $template = ContractTemplate::query()
            ->where('partner_id', $this->partner->id)
            ->where('title', 'Оферта E2E')
            ->firstOrFail();

        $this->post('/client-contracts', [
            'creation_mode'        => Contract::CREATION_MODE_TEMPLATE,
            'user_id'              => $this->user->id,
            'contract_template_id' => $template->id,
        ])->assertRedirect();

        $contract = Contract::query()->latest('id')->firstOrFail();
        $this->assertSame(Contract::STATUS_AWAITING_CLIENT_FILL, $contract->status);
        $this->assertSame(Contract::CREATION_MODE_TEMPLATE, $contract->creation_mode);
        Mail::assertSent(\App\Mail\ContractClientFillInvitationMail::class);

        $this->actingAs($this->user)
            ->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true])
            ->get(route('account.documents.fill', $contract))
            ->assertOk()
            ->assertSee('Сформировать договор');

        $this->post(route('account.documents.generate', $contract), [
            'fields' => ['fio_parent' => 'Смирнов Алексей'],
        ])->assertRedirect(route('account.documents.fill', $contract));

        $contract->refresh();
        $this->assertSame(Contract::STATUS_DRAFT, $contract->status);
        $this->assertNotNull($contract->source_pdf_path);
        Storage::assertExists($contract->source_pdf_path);

        Http::fake(['*' => Http::response([['status' => 15, 'status_text' => 'sent']], 200)]);

        $provider = Mockery::mock(SignatureProvider::class);
        $provider->shouldReceive('send')->once()->andReturnUsing(function (Contract $c) {
            $c->provider_doc_id = 'pkg-e2e-1';
            $c->save();

            return ['ok' => true];
        });
        $this->app->instance(SignatureProvider::class, $provider);

        $this->post(route('account.documents.sign', $contract), [
            'signer_lastname'   => 'Смирнов',
            'signer_firstname'  => 'Алексей',
            'signer_middlename' => 'Сергеевич',
            'signer_phone'      => '+7 (900) 222-33-44',
        ])
            ->assertRedirect(route('account.documents.index'))
            ->assertSessionHas('success');

        $contract->refresh();
        $this->assertSame(Contract::STATUS_SENT, $contract->status);

        $this->actingAs($this->user)
            ->get(route('account.documents.index'))
            ->assertOk()
            ->assertSee('data-id="' . $contract->id . '"', false);
    }

    /** @test */
    public function create_contract_page_shows_template_creation_mode(): void
    {
        $this->createContractTemplateWithVersion(['title' => 'Шаблон для выбора']);

        $this->get(route('contracts.index', ['create' => 1]))
            ->assertOk()
            ->assertSee('creation_mode', false)
            ->assertSee(Contract::CREATION_MODE_TEMPLATE, false);
    }

    /** @test */
    public function show_displays_awaiting_client_fill_for_template_contract(): void
    {
        $template = $this->createContractTemplateWithVersion();

        $contract = Contract::create([
            'school_id'                    => $this->partner->id,
            'user_id'                      => $this->user->id,
            'group_id'                     => null,
            'creation_mode'                => Contract::CREATION_MODE_TEMPLATE,
            'contract_template_version_id' => $template->current_version_id,
            'source_pdf_path'              => null,
            'source_sha256'                => null,
            'status'                       => Contract::STATUS_AWAITING_CLIENT_FILL,
            'fill_expires_at'              => now()->addDays(7),
            'provider'                     => 'podpislon',
        ]);

        $this->get('/client-contracts/' . $contract->id)
            ->assertOk()
            ->assertSee('Клиент заполняет договор в личном кабинете', false);
    }
}
