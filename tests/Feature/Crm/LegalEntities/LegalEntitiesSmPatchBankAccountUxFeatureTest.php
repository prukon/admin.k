<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LegalEntities;

use App\Models\PartnerLegalEntity;
use App\Models\User;
use App\Services\Tinkoff\SmRegisterClient;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Feature\Crm\CrmTestCase;

/**
 * UX формы «Обновить в sm-register»: напоминание полей, порядок р/с→к/с, inn в CRM но не в банк.
 */
final class LegalEntitiesSmPatchBankAccountUxFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->asAdmin();
        $this->grantPermissions($this->user, ['legal_entities.view', 'legal_entities.manage', 'legal_entities.sm_register']);
    }

    /** @param list<string> $permissions */
    private function grantPermissions(User $actor, array $permissions): void
    {
        foreach ($permissions as $permission) {
            DB::table('permission_role')->insertOrIgnore([
                'partner_id' => $this->partner->id,
                'role_id' => $actor->role_id,
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

    public function test_registered_card_warns_which_fields_go_to_the_bank(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->registered('SC-HINT-YES')->create();

        $html = $this->get(route('admin.legal-entities.show', $entity))
            ->assertOk()
            ->assertSee('id="legal-entity-sm-patch-fields-hint"', false)
            ->assertSee('Обновить в sm-register', false)
            ->getContent();

        $this->assertStringContainsString('только', $html);
        $this->assertStringContainsString('bankAccount', $html);
        $this->assertStringContainsString('банк, БИК, расчётный счёт, назначение платежа', $html);
        $this->assertStringContainsString('ИНН, адрес, email, телефон, руководитель', $html);
        $this->assertStringContainsString('не уходят', $html);
        $this->assertStringContainsString('В банк уйдут только: банк, БИК, р/с, назначение и к/с (если заполнен).', $html);
    }

    public function test_unregistered_card_does_not_show_patch_only_bank_hint(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->create([
            'tinkoff_shop_code' => null,
        ]);

        $this->get(route('admin.legal-entities.show', $entity))
            ->assertOk()
            ->assertSee('Зарегистрировать в sm-register', false)
            ->assertDontSee('id="legal-entity-sm-patch-fields-hint"', false)
            ->assertDontSee('id="legal-entity-tbank-shop-status"', false)
            ->assertDontSee('В банк уйдут только:', false);
    }

    public function test_corr_account_field_is_rendered_after_settlement_account(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->registered('SC-ORDER')->create();

        $html = $this->get(route('admin.legal-entities.show', $entity))->assertOk()->getContent();

        $accountPos = strpos($html, 'name="bank_account"');
        $corrPos = strpos($html, 'name="bank_corr_account"');
        $this->assertNotFalse($accountPos);
        $this->assertNotFalse($corrPos);
        $this->assertGreaterThan($accountPos, $corrPos, 'К/с должен идти после р/с в форме sm-register');
        $this->assertStringContainsString('data-error-for="bank_corr_account"', $html);
        $this->assertStringContainsString('bankAccount.korAccount', $html);
    }

    public function test_ceo_fields_are_disabled_after_shop_code_and_editable_before(): void
    {
        $registered = PartnerLegalEntity::factory()->for($this->partner)->registered('SC-CEO')->create([
            'ceo' => ['lastName' => 'Петров', 'firstName' => 'Пётр'],
        ]);
        $unregistered = PartnerLegalEntity::factory()->for($this->partner)->create([
            'tinkoff_shop_code' => null,
            'ceo' => ['lastName' => 'Сидоров', 'firstName' => 'Сидор'],
        ]);

        $registeredHtml = $this->get(route('admin.legal-entities.show', $registered))->assertOk()->getContent();
        $this->assertMatchesRegularExpression(
            '/name="ceo\[lastName\]"[^>]*\bdisabled\b/',
            $registeredHtml
        );

        $unregisteredHtml = $this->get(route('admin.legal-entities.show', $unregistered))->assertOk()->getContent();
        $this->assertDoesNotMatchRegularExpression(
            '/name="ceo\[lastName\]"[^>]*\bdisabled\b/',
            $unregisteredHtml
        );
    }

    public function test_patch_keeps_inn_in_crm_but_does_not_send_inn_to_the_bank(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->registered('SC-INN')->create([
            'tax_id' => '7700000001',
        ]);

        $sm = Mockery::mock(SmRegisterClient::class);
        $this->app->instance(SmRegisterClient::class, $sm);
        $sm->shouldReceive('patch')
            ->once()
            ->with('SC-INN', Mockery::on(function (array $payload): bool {
                $this->assertArrayNotHasKey('inn', $payload);
                $this->assertArrayNotHasKey('tax_id', $payload);
                $this->assertSame(['bankAccount'], array_keys($payload));

                return true;
            }))
            ->andReturn(['ok' => true]);

        $this->postJson(route('admin.legal-entities.sm-patch', $entity), $this->validSmPayload([
            'tax_id' => '7700000099',
        ]))
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertSame('7700000099', $entity->fresh()->tax_id);
    }

    public function test_non_ajax_patch_validation_failure_redirects_with_bank_field_error(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->registered('SC-PATCH-VAL')->create();

        $this->from(route('admin.legal-entities.show', $entity))
            ->post(route('admin.legal-entities.sm-patch', $entity), $this->validSmPayload([
                'bank_account' => '',
            ]))
            ->assertStatus(302)
            ->assertSessionHasErrors(['bank_account']);
    }

    public function test_ajax_patch_validation_returns_422_under_bank_account(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->registered('SC-PATCH-AJAX-VAL')->create();

        $this->postJson(route('admin.legal-entities.sm-patch', $entity), $this->validSmPayload([
            'bank_account' => '',
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['bank_account']);
    }
}
