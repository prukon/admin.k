<?php

namespace Tests\Feature\Phone;

use App\Models\Contract;
use App\Models\User;
use App\Services\Signatures\SignatureProvider;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\Feature\Crm\Account\Concerns\InteractsWithAccountContractFill;
use Tests\Feature\Crm\Contracts\ContractsFeatureTestCase;
use Tests\Feature\Phone\Concerns\InteractsWithPhoneInput;

/**
 * Договоры: сохранение телефона в fill/sign и проверка при повторной загрузке.
 */
final class PhoneFormContractPersistenceFeatureTest extends ContractsFeatureTestCase
{
    use InteractsWithPhoneInput;
    use InteractsWithAccountContractFill;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useAccountContractFillStorage();
    }

    public function test_contract_fill_form_reload_shows_saved_parent_phone_after_generate(): void
    {
        $masked = $this->randomRuPhoneMasked();

        $contract = $this->makeAwaitingFillContract([
            ['key' => 'parent_lastname', 'label' => 'Фамилия', 'required' => true],
            ['key' => 'parent_phone', 'label' => 'Телефон', 'required' => true],
        ], ['parent_lastname', 'parent_phone']);

        $this->withSession($this->accountDocumentsSession())
            ->post(route('account.documents.generate', $contract), [
                'fields' => [
                    'parent_lastname' => 'Иванов',
                    'parent_phone'    => $masked,
                ],
                'signer_lastname'   => 'Иванов',
                'signer_firstname'  => 'Иван',
                'signer_middlename' => 'Иванович',
                'signer_phone'      => $masked,
            ])
            ->assertRedirect();

        $contract->refresh();
        $this->assertSame($masked, data_get($contract->filled_data, 'parent_phone'));

        $html = $this->getContractFillModalHtml(Contract::query()->findOrFail($contract->id));
        $this->assertStringContainsString('name="signer_phone"', $html);
        $this->assertHtmlContainsFormattedPhone($html, $masked);
    }

    public function test_contract_admin_send_persists_signer_phone_in_sign_request(): void
    {
        Http::fake(['*' => Http::response([['status' => 15, 'status_text' => 'sent']], 200)]);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);

        $contract = Contract::create([
            'school_id'       => $this->partner->id,
            'user_id'         => $student->id,
            'group_id'        => null,
            'source_pdf_path' => 'documents/2026/01/source.pdf',
            'source_sha256'   => str_repeat('f', 64),
            'provider'        => 'podpislon',
            'status'          => Contract::STATUS_DRAFT,
        ]);

        $masked = $this->randomRuPhoneMasked();

        $provider = Mockery::mock(SignatureProvider::class);
        $provider->shouldReceive('send')->once()->andReturnUsing(function (Contract $c) {
            $c->provider_doc_id = 'pkg-phone-test';
            $c->save();

            return ['ok' => true];
        });
        $this->app->instance(SignatureProvider::class, $provider);

        $this->postJson('/client-contracts/' . $contract->id . '/send', [
            'signer_lastname'   => 'Иванов',
            'signer_firstname'  => 'Иван',
            'signer_middlename' => 'Иванович',
            'signer_phone'      => $masked,
        ])->assertOk();

        $request = $contract->signRequests()->latest('id')->first();
        $this->assertNotNull($request);
        $this->assertSame(
            (string) \App\Support\RuPhone::normalizeDigits($masked),
            (string) $request->signer_phone
        );
    }
}
