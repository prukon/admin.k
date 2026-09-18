<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LegalEntities;

use App\Models\PartnerLegalEntity;
use App\Services\Tinkoff\SmRegisterClient;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Feature\Crm\CrmTestCase;

/**
 * sm-register / sm-patch / sm-refresh / sm-pull для юр. лиц (AJAX-контракт + доступ).
 */
final class LegalEntitiesSmRegisterFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->asAdmin();
        $this->grantPermissions(['legal_entities.view', 'legal_entities.manage', 'legal_entities.sm_register']);
    }

    /** @param list<string> $permissions */
    private function grantPermissions(array $permissions): void
    {
        foreach ($permissions as $permission) {
            DB::table('permission_role')->insertOrIgnore([
                'partner_id' => $this->partner->id,
                'role_id' => $this->user->role_id,
                'permission_id' => $this->permissionId($permission),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validSmPayload(array $overrides = []): array
    {
        return array_merge([
            'business_type' => 'OOO',
            'title' => 'ООО SM Test',
            'organization_name' => 'ООО SM Test',
            'email' => 'sm@example.test',
            'tax_id' => '7700000001',
            'registration_number' => '1234567890123',
            'address' => 'ул. Пушкина, д. 1',
            'city' => 'Москва',
            'zip' => '101000',
            'bank_name' => 'Т-Банк',
            'bank_bik' => '044525974',
            'bank_account' => '40702810900000000001',
            'sm_details_template' => 'Назначение платежа',
            'phone' => '+79990000000',
            'website' => 'https://example.test',
            'kpp' => '770101001',
            'ceo' => [
                'lastName' => 'Иванов',
                'firstName' => 'Иван',
                'middleName' => 'Иванович',
                'phone' => '+79990000001',
            ],
        ], $overrides);
    }

    private function bindSmMock(): Mockery\MockInterface
    {
        $sm = Mockery::mock(SmRegisterClient::class);
        $this->app->instance(SmRegisterClient::class, $sm);

        return $sm;
    }

    public function test_sm_register_ajax_json_contract(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->create([
            'tinkoff_shop_code' => null,
        ]);

        $sm = $this->bindSmMock();
        $sm->shouldReceive('register')
            ->once()
            ->andReturn(['shopCode' => 'SC-LEGAL-001', 'status' => 'REGISTERED']);

        $this->postJson(route('admin.legal-entities.sm-register', $entity), $this->validSmPayload())
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('shopCode', 'SC-LEGAL-001')
            ->assertJsonPath('status', 'REGISTERED');

        $entity->refresh();
        $this->assertSame('SC-LEGAL-001', $entity->tinkoff_shop_code);
    }

    public function test_sm_register_when_already_registered_returns_422(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->registered('SC-EXISTING')->create();

        $sm = $this->bindSmMock();
        $sm->shouldNotReceive('register');

        $this->postJson(route('admin.legal-entities.sm-register', $entity), $this->validSmPayload())
            ->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonStructure(['message', 'errors']);
    }

    public function test_sm_register_validation_returns_422_with_field_errors(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->create();

        $this->postJson(route('admin.legal-entities.sm-register', $entity), [
            'business_type' => 'OOO',
            'title' => '',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'organization_name', 'email']);
    }

    public function test_sm_patch_ajax_json_contract(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->registered('SC-PATCH-001')->create();

        $sm = $this->bindSmMock();
        $sm->shouldReceive('patch')
            ->once()
            ->with('SC-PATCH-001', Mockery::on(function (array $payload): bool {
                $this->assertSame(['bankAccount'], array_keys($payload));
                $this->assertSame('40702810900000000001', $payload['bankAccount']['account']);
                $this->assertSame('Т-Банк', $payload['bankAccount']['bankName']);
                $this->assertSame('044525974', $payload['bankAccount']['bik']);
                $this->assertSame('Назначение платежа', $payload['bankAccount']['details']);
                $this->assertArrayNotHasKey('inn', $payload);
                $this->assertArrayNotHasKey('email', $payload);
                $this->assertArrayNotHasKey('addresses', $payload);
                $this->assertArrayNotHasKey('disableReimbursement', $payload['bankAccount']);

                return true;
            }))
            ->andReturn(['ok' => true]);

        $this->postJson(route('admin.legal-entities.sm-patch', $entity), $this->validSmPayload([
            'title' => 'ООО SM Patched',
            'organization_name' => 'ООО SM Patched',
        ]))
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertSame('ООО SM Patched', $entity->fresh()->title);
    }

    public function test_sm_patch_without_shop_code_returns_422(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->create([
            'tinkoff_shop_code' => null,
        ]);

        $sm = $this->bindSmMock();
        $sm->shouldNotReceive('patch');

        $this->postJson(route('admin.legal-entities.sm-patch', $entity), $this->validSmPayload())
            ->assertStatus(422)
            ->assertJsonPath('ok', false);
    }

    public function test_sm_refresh_ajax_json_contract(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->registered('SC-REFRESH')->create([
            'sm_register_status' => 'REGISTERED',
        ]);

        $sm = $this->bindSmMock();
        $sm->shouldReceive('getStatus')
            ->once()
            ->with('SC-REFRESH')
            ->andReturn([
                'name' => 'ИП Тест',
                'inn' => '470322227410',
                'bankAccount' => [
                    'account' => '40802810900000001544',
                    'korAccount' => '301018104000000005104',
                    'bankName' => 'ООО "Банк Точка"',
                    'bik' => '044525104',
                    'details' => 'Возмещение по договору',
                    'disableReimbursement' => true,
                ],
            ]);

        $this->postJson(route('admin.legal-entities.sm-refresh', $entity))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('status', 'REGISTERED')
            ->assertJsonPath('disable_reimbursement', true);

        $entity->refresh();
        $this->assertSame('REGISTERED', $entity->sm_register_status);
        $this->assertTrue($entity->tinkoffPayoutsBlocked());
        $this->assertNotNull($entity->tinkoff_shop_checked_at);
        $this->assertSame('044525104', data_get($entity->tinkoff_shop_snapshot, 'bankAccount.bik'));
    }

    public function test_sm_refresh_non_ajax_flash_mentions_blocked_payouts(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->registered('SC-REFRESH-HTML')->create();

        $sm = $this->bindSmMock();
        $sm->shouldReceive('getStatus')
            ->once()
            ->andReturn([
                'bankAccount' => [
                    'disableReimbursement' => false,
                    'account' => '40802810900000001544',
                    'bankName' => 'Т-Банк',
                    'bik' => '044525974',
                ],
            ]);

        $this->from(route('admin.legal-entities.show', $entity))
            ->post(route('admin.legal-entities.sm-refresh', $entity))
            ->assertRedirect(route('admin.legal-entities.show', $entity))
            ->assertSessionHas('ok');

        $this->assertFalse($entity->fresh()->tinkoffPayoutsBlocked());
    }

    public function test_show_html_displays_reimbursement_block_banner(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->registered('SC-BLOCKED')->create([
            'tinkoff_disable_reimbursement' => true,
            'tinkoff_shop_checked_at' => now(),
            'tinkoff_shop_snapshot' => [
                'bankAccount' => [
                    'bankName' => 'ООО "Банк Точка"',
                    'bik' => '044525104',
                    'account' => '40802810900000001544',
                    'korAccount' => '301018104000000005104',
                    'details' => 'Возмещение по договору',
                    'disableReimbursement' => true,
                ],
            ],
        ]);

        $this->get(route('admin.legal-entities.show', $entity))
            ->assertOk()
            ->assertSee('id="legal-entity-tbank-shop-status"', false)
            ->assertSee('id="legal-entity-reimbursement-blocked"', false)
            ->assertSee('Выплаты заблокированы банком', false)
            ->assertSee('Банк Точка', false)
            ->assertSee('044525104', false);
    }

    public function test_sm_pull_ajax_json_contract(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->registered('SC-PULL')->create([
            'organization_name' => 'Старое имя',
        ]);

        $sm = $this->bindSmMock();
        $sm->shouldReceive('getStatus')
            ->once()
            ->with('SC-PULL')
            ->andReturn([
                'fullName' => 'Новое имя из банка',
                'inn' => '7700000099',
                'kpp' => '770101001',
                'ogrn' => '1234567890123',
                'status' => 'ACTIVE',
                'addresses' => [[
                    'city' => 'Москва',
                    'zip' => '101000',
                    'street' => 'ул. Банковская, 2',
                ]],
                'bankAccount' => [
                    'bankName' => 'Т-Банк',
                    'bik' => '044525974',
                    'account' => '40702810900000000002',
                    'korAccount' => '30101810400000000225',
                    'details' => 'Назначение из банка',
                    'disableReimbursement' => false,
                ],
                'phones' => [['phone' => '+79991112233']],
            ]);

        $this->postJson(route('admin.legal-entities.sm-pull', $entity))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['changed']);

        $entity->refresh();
        $this->assertSame('Новое имя из банка', $entity->organization_name);
        $this->assertSame('30101810400000000225', $entity->bank_corr_account);
        $this->assertFalse($entity->tinkoffPayoutsBlocked());
    }

    public function test_sm_register_non_ajax_redirects_to_show_after_success(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->create([
            'tinkoff_shop_code' => null,
        ]);

        $sm = $this->bindSmMock();
        $sm->shouldReceive('register')
            ->once()
            ->andReturn(['shopCode' => 'SC-NON-AJAX', 'status' => 'REGISTERED']);

        $this->post(route('admin.legal-entities.sm-register', $entity), $this->validSmPayload())
            ->assertRedirect(route('admin.legal-entities.show', $entity))
            ->assertSessionHas('ok');

        $this->assertSame('SC-NON-AJAX', $entity->fresh()->tinkoff_shop_code);
    }

    public function test_sm_patch_non_ajax_redirects_to_show_after_success(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->registered('SC-PATCH-NON-AJAX')->create([
            'title' => 'До non-ajax patch',
        ]);

        $sm = $this->bindSmMock();
        $sm->shouldReceive('patch')
            ->once()
            ->with('SC-PATCH-NON-AJAX', Mockery::type('array'))
            ->andReturn(['ok' => true]);

        $this->post(route('admin.legal-entities.sm-patch', $entity), $this->validSmPayload([
            'title' => 'После non-ajax patch',
            'organization_name' => 'После non-ajax patch',
        ]))
            ->assertRedirect(route('admin.legal-entities.show', $entity))
            ->assertSessionHas('ok');

        $this->assertSame('После non-ajax patch', $entity->fresh()->title);
    }

    public function test_sm_patch_sends_kor_account_when_filled(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->registered('SC-PATCH-KOR')->create();

        $sm = $this->bindSmMock();
        $sm->shouldReceive('patch')
            ->once()
            ->with('SC-PATCH-KOR', Mockery::on(function (array $payload): bool {
                $this->assertSame('30101810400000000225', $payload['bankAccount']['korAccount'] ?? null);

                return true;
            }))
            ->andReturn(['ok' => true]);

        $this->postJson(route('admin.legal-entities.sm-patch', $entity), $this->validSmPayload([
            'bank_corr_account' => '30101810400000000225',
        ]))
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertSame('30101810400000000225', $entity->fresh()->bank_corr_account);
    }

    public function test_show_html_displays_sm_patch_fields_hint(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->registered('SC-HINT')->create();

        $this->get(route('admin.legal-entities.show', $entity))
            ->assertOk()
            ->assertSee('id="legal-entity-sm-patch-fields-hint"', false)
            ->assertSee('только', false)
            ->assertSee('bankAccount', false)
            ->assertSee('ИНН, адрес, email', false);
    }

    public function test_show_html_displays_enable_reimbursement_button_when_blocked(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->registered('SC-UNBLOCK-UI')->create([
            'tinkoff_disable_reimbursement' => true,
            'tinkoff_shop_checked_at' => now(),
            'bank_name' => 'ООО "Банк Точка"',
            'bank_bik' => '044525104',
            'bank_account' => '40802810420000841544',
            'sm_details_template' => 'Выплата по договору',
        ]);

        $this->get(route('admin.legal-entities.show', $entity))
            ->assertOk()
            ->assertSee('id="legal-entity-enable-reimbursement"', false)
            ->assertSee('Снять блокировку выплат', false);
    }

    public function test_sm_enable_reimbursement_ajax_json_contract(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->registered('SC-UNBLOCK')->create([
            'tinkoff_disable_reimbursement' => true,
            'bank_name' => 'ООО Банк Точка',
            'bank_bik' => '044525104',
            'bank_account' => '40802810420000841544',
            'bank_corr_account' => '30101810400000000225',
            'sm_details_template' => 'Выплата по договору',
        ]);

        $sm = $this->bindSmMock();
        $sm->shouldReceive('patch')
            ->once()
            ->with('SC-UNBLOCK', Mockery::on(function (array $payload): bool {
                $this->assertSame(['bankAccount'], array_keys($payload));
                $this->assertFalse($payload['bankAccount']['disableReimbursement']);
                $this->assertSame('40802810420000841544', $payload['bankAccount']['account']);
                $this->assertSame('ООО Банк Точка', $payload['bankAccount']['bankName']);
                $this->assertSame('044525104', $payload['bankAccount']['bik']);
                $this->assertSame('Выплата по договору', $payload['bankAccount']['details']);
                $this->assertSame('30101810400000000225', $payload['bankAccount']['korAccount']);

                return true;
            }))
            ->andReturn(['ok' => true]);
        $sm->shouldReceive('getStatus')
            ->once()
            ->with('SC-UNBLOCK')
            ->andReturn([
                'bankAccount' => [
                    'disableReimbursement' => false,
                    'account' => '40802810420000841544',
                    'bankName' => 'ООО Банк Точка',
                    'bik' => '044525104',
                    'details' => 'Выплата по договору',
                ],
            ]);

        $this->postJson(route('admin.legal-entities.sm-enable-reimbursement', $entity))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('disable_reimbursement', false);

        $this->assertFalse($entity->fresh()->tinkoffPayoutsBlocked());
    }

    public function test_sm_enable_reimbursement_without_bank_fields_returns_422_under_fields(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->registered('SC-UNBLOCK-EMPTY')->create([
            'tinkoff_disable_reimbursement' => true,
            'bank_name' => null,
            'bank_bik' => null,
            'bank_account' => null,
        ]);

        $sm = $this->bindSmMock();
        $sm->shouldNotReceive('patch');

        $this->postJson(route('admin.legal-entities.sm-enable-reimbursement', $entity))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['bank_account', 'bank_name', 'bank_bik']);
    }
}
