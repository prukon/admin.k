<?php

namespace Tests\Feature\Crm\Contracts;

use App\Mail\ContractClientFillInvitationMail;
use App\Models\Contract;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * Создание договора по шаблону из модалки «Создать договор» (/client-contracts).
 */
class ContractCreateTemplateModeModalFeatureTest extends ContractsFeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['billing.contract_create_fee' => 70.00]);
        config(['queue.default' => 'sync']);
        $this->partner->wallet_balance = 500;
        $this->partner->save();
    }

    /** @test */
    public function store_template_mode_from_modal_redirects_to_show_with_success_message(): void
    {
        Mail::fake();

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
            'email'      => 'modal-template@example.com',
        ]);

        $template = $this->createContractTemplateWithVersion(['title' => 'Шаблон из модалки']);

        $response = $this->from(route('contracts.index', ['create' => 1]))
            ->post(route('contracts.store'), [
                'creation_mode'        => Contract::CREATION_MODE_TEMPLATE,
                'user_id'              => $student->id,
                'contract_template_id' => $template->id,
            ]);

        $contract = Contract::query()->latest('id')->firstOrFail();

        $response->assertSessionHasNoErrors()
            ->assertRedirect(route('contracts.show', $contract))
            ->assertSessionHas('success');

        $this->followRedirects($response)
            ->assertOk()
            ->assertSee('Клиент заполняет договор в личном кабинете', false);

        Mail::assertSent(ContractClientFillInvitationMail::class);
    }

    /** @test */
    public function store_template_mode_validation_requires_template_id(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);

        $this->from(route('contracts.index', ['create' => 1, 'user_id' => $student->id]))
            ->post(route('contracts.store'), [
                'creation_mode' => Contract::CREATION_MODE_TEMPLATE,
                'user_id'       => $student->id,
            ])
            ->assertSessionHasErrors('contract_template_id')
            ->assertRedirect(route('contracts.index', [
                'create'  => 1,
                'user_id' => $student->id,
            ]));

        $this->get(route('contracts.index', ['create' => 1, 'user_id' => $student->id]))
            ->assertOk()
            ->assertViewHas('shouldOpenCreateModal', true);
    }

    /** @test */
    public function store_template_mode_fails_when_insufficient_balance(): void
    {
        Mail::fake();
        $this->partner->wallet_balance = 0;
        $this->partner->save();

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);

        $template = $this->createContractTemplateWithVersion();

        $this->from(route('contracts.index', ['create' => 1]))
            ->post(route('contracts.store'), [
                'creation_mode'        => Contract::CREATION_MODE_TEMPLATE,
                'user_id'              => $student->id,
                'contract_template_id' => $template->id,
            ])
            ->assertRedirect(route('contracts.index', [
                'create'  => 1,
                'user_id' => $student->id,
            ]))
            ->assertSessionHasErrors('wallet')
            ->assertSessionHas('error');

        $this->assertSame(0, Contract::query()->count());
        Mail::assertNothingSent();
    }

    /** @test */
    public function store_template_mode_rejects_foreign_student(): void
    {
        $foreignStudent = User::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'is_enabled' => 1,
        ]);

        $template = $this->createContractTemplateWithVersion();

        $this->from(route('contracts.index', ['create' => 1]))
            ->post(route('contracts.store'), [
                'creation_mode'        => Contract::CREATION_MODE_TEMPLATE,
                'user_id'              => $foreignStudent->id,
                'contract_template_id' => $template->id,
            ])
            ->assertStatus(422);

        $this->assertSame(0, Contract::query()->count());
    }

    /** @test */
    public function contracts_index_modal_shows_both_creation_modes(): void
    {
        $this->createContractTemplateWithVersion(['title' => 'Для radio']);

        $this->get(route('contracts.index', ['create' => 1]))
            ->assertOk()
            ->assertSee('id="mode_template"', false)
            ->assertSee('id="mode_pdf"', false)
            ->assertSee('value="' . Contract::CREATION_MODE_TEMPLATE . '"', false)
            ->assertSee('value="' . Contract::CREATION_MODE_PDF . '"', false)
            ->assertSee('id="contract_template_id"', false)
            ->assertSee('Для radio', false);
    }
}
