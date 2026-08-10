<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LegalEntities;

use App\Enums\PartnerLegalEntityBusinessType;
use App\Models\PartnerLegalEntity;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Корреспондентский счёт юрлица (bank_corr_account): HTTP/AJAX/non-AJAX, UI, lock-правила после sm-register.
 *
 * @see /docs/documentation/admin-legal-entities.html §4.2
 */
final class LegalEntitiesBankCorrAccountFeatureTest extends CrmTestCase
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

    public function test_partner_legal_entities_table_has_bank_corr_account_column(): void
    {
        $this->assertTrue(Schema::hasColumn('partner_legal_entities', 'bank_corr_account'));
    }

    public function test_guest_cannot_save_bank_corr_account(): void
    {
        Auth::logout();

        $this->postJson(route('admin.legal-entities.store'), [
            'business_type' => 'IP',
            'organization_name' => 'ИП Гость',
            'bank_corr_account' => '30101810145250000974',
        ])->assertUnauthorized();
    }

    public function test_manager_without_manage_permission_gets_403_when_saving_bank_corr_account(): void
    {
        $actor = $this->createUserWithoutPermission('legal_entities.manage', $this->partner);
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $actor->role_id,
            'permission_id' => $this->permissionId('legal_entities.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($actor)->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ])->postJson(route('admin.legal-entities.store'), [
            'business_type' => 'IP',
            'organization_name' => 'ИП Без Manage',
            'bank_corr_account' => '30101810145250000974',
        ])->assertForbidden();
    }

    public function test_ajax_store_persists_bank_corr_account_and_returns_entity(): void
    {
        $response = $this->postJson(route('admin.legal-entities.store'), [
            'business_type' => 'IP',
            'organization_name' => 'ИП Коррсчёт Ajax',
            'tax_id' => '123456789013',
            'bank_name' => 'АО «Тинькофф Банк»',
            'bank_bik' => '044525974',
            'bank_account' => '40802810900000000001',
            'bank_corr_account' => '30101810145250000974',
            'is_enabled' => 1,
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Юр. лицо создано')
            ->assertJsonStructure(['message', 'legal_entity' => ['id']]);

        $entity = PartnerLegalEntity::query()
            ->where('partner_id', $this->partner->id)
            ->where('tax_id', '123456789013')
            ->first();

        $this->assertNotNull($entity);
        $this->assertSame('30101810145250000974', $entity->bank_corr_account);
    }

    public function test_ajax_update_persists_bank_corr_account_and_show_json_includes_it(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->create([
            'business_type' => PartnerLegalEntityBusinessType::IP,
            'organization_name' => 'ИП Коррсчёт Update',
            'tax_id' => '123456789014',
            'bank_corr_account' => '30101810145250000974',
        ]);

        $this->putJson(route('admin.legal-entities.update', $entity), [
            'business_type' => 'IP',
            'organization_name' => 'ИП Коррсчёт Update',
            'tax_id' => '123456789014',
            'bank_corr_account' => '30101810400000000555',
            'is_enabled' => 1,
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Юр. лицо обновлено');

        $this->assertSame('30101810400000000555', $entity->fresh()->bank_corr_account);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('admin.legal-entities.show', $entity))
            ->assertOk()
            ->assertJsonPath('bank_corr_account', '30101810400000000555');
    }

    public function test_ajax_update_clears_bank_corr_account_when_empty(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->create([
            'organization_name' => 'ИП Очистка КС',
            'bank_corr_account' => '30101810145250000974',
        ]);

        $this->putJson(route('admin.legal-entities.update', $entity), [
            'business_type' => PartnerLegalEntityBusinessType::OOO->value,
            'organization_name' => 'ИП Очистка КС',
            'bank_corr_account' => '',
            'is_enabled' => 1,
        ])->assertOk();

        $this->assertTrue(
            in_array($entity->fresh()->bank_corr_account, [null, ''], true),
            'Пустой корр. счёт должен очищаться'
        );
    }

    public function test_ajax_store_validation_returns_422_with_bank_corr_account_field_error(): void
    {
        $this->postJson(route('admin.legal-entities.store'), [
            'business_type' => 'IP',
            'organization_name' => 'ИП Длинный коррсчёт',
            'bank_corr_account' => str_repeat('1', 21),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['bank_corr_account']);
    }

    public function test_store_non_ajax_redirects_and_creates_entity_with_bank_corr_account(): void
    {
        $this->post(route('admin.legal-entities.store'), [
            'business_type' => 'IP',
            'organization_name' => 'ИП NonAjax КС',
            'tax_id' => '123456789015',
            'bank_corr_account' => '30101810145250000974',
            'is_enabled' => 1,
        ])->assertRedirect(route('admin.legal-entities.index'));

        $entity = PartnerLegalEntity::query()
            ->where('partner_id', $this->partner->id)
            ->where('tax_id', '123456789015')
            ->first();

        $this->assertNotNull($entity);
        $this->assertSame('30101810145250000974', $entity->bank_corr_account);
    }

    public function test_update_non_ajax_redirects_and_updates_bank_corr_account(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->create([
            'organization_name' => 'До non-ajax КС',
            'bank_corr_account' => '30101810145250000974',
        ]);

        $this->put(route('admin.legal-entities.update', $entity), [
            'business_type' => PartnerLegalEntityBusinessType::OOO->value,
            'organization_name' => 'После non-ajax КС',
            'bank_corr_account' => '30101810400000000555',
            'is_default' => true,
            'is_enabled' => true,
        ])
            ->assertRedirect(route('admin.legal-entities.show', $entity));

        $this->assertSame('30101810400000000555', $entity->fresh()->bank_corr_account);
    }

    public function test_store_non_ajax_validation_failure_redirects_with_bank_corr_account_error_not_empty_200(): void
    {
        $this->from(route('admin.legal-entities.index'))
            ->post(route('admin.legal-entities.store'), [
                'business_type' => 'IP',
                'organization_name' => 'ИП Bad КС',
                'bank_corr_account' => str_repeat('9', 21),
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['bank_corr_account']);
    }

    public function test_foreign_partner_entity_bank_corr_account_is_not_accessible(): void
    {
        $foreign = PartnerLegalEntity::factory()->for($this->foreignPartner)->create([
            'bank_corr_account' => '30101810145250000974',
        ]);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('admin.legal-entities.show', $foreign))
            ->assertNotFound();

        $this->putJson(route('admin.legal-entities.update', $foreign), [
            'business_type' => PartnerLegalEntityBusinessType::OOO->value,
            'organization_name' => $foreign->organization_name ?: $foreign->title,
            'bank_corr_account' => '30101810400000000555',
            'is_enabled' => 1,
        ])->assertNotFound();

        $this->assertSame('30101810145250000974', $foreign->fresh()->bank_corr_account);
    }

    /**
     * Правило: после sm-register банковские поля T‑Bank заблокированы в CRUD,
     * но bank_corr_account (CRM-only) остаётся редактируемым.
     */
    public function test_registered_entity_allows_bank_corr_account_change_via_crud(): void
    {
        $smDetails = 'Выплата по договору, НДС не облагается';
        $entity = PartnerLegalEntity::factory()
            ->for($this->partner)
            ->registered('SHOP-CORR-1')
            ->create([
                'bank_account' => '40802810900000000001',
                'bank_corr_account' => '30101810145250000974',
                'sm_details_template' => $smDetails,
            ]);

        $this->putJson(route('admin.legal-entities.update', $entity), [
            'business_type' => PartnerLegalEntityBusinessType::OOO->value,
            'organization_name' => $entity->organization_name ?: $entity->title,
            'tax_id' => $entity->tax_id,
            'bank_account' => '40802810900000000001',
            'bank_corr_account' => '30101810400000000555',
            'sm_details_template' => $smDetails,
            'is_default' => true,
            'is_enabled' => true,
        ])->assertOk();

        $this->assertSame('30101810400000000555', $entity->fresh()->bank_corr_account);
    }

    /**
     * Негатив к правилу: расчётный счёт после регистрации через CRM менять нельзя.
     */
    public function test_registered_entity_rejects_bank_account_change_via_crud_but_not_corr(): void
    {
        $smDetails = 'Выплата по договору, НДС не облагается';
        $entity = PartnerLegalEntity::factory()
            ->for($this->partner)
            ->registered('SHOP-CORR-2')
            ->create([
                'bank_account' => '40802810900000000001',
                'bank_corr_account' => '30101810145250000974',
                'sm_details_template' => $smDetails,
            ]);

        $this->putJson(route('admin.legal-entities.update', $entity), [
            'business_type' => PartnerLegalEntityBusinessType::OOO->value,
            'organization_name' => $entity->organization_name ?: $entity->title,
            'tax_id' => $entity->tax_id,
            'bank_account' => '40802810900000000999',
            'bank_corr_account' => '30101810145250000974',
            'sm_details_template' => $smDetails,
            'is_default' => true,
            'is_enabled' => true,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['bank_account']);

        $fresh = $entity->fresh();
        $this->assertSame('40802810900000000001', $fresh->bank_account);
        $this->assertSame('30101810145250000974', $fresh->bank_corr_account);
    }

    public function test_unregistered_entity_allows_both_bank_account_and_corr_change(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->create([
            'tinkoff_shop_code' => null,
            'bank_account' => '40802810900000000001',
            'bank_corr_account' => '30101810145250000974',
        ]);

        $this->putJson(route('admin.legal-entities.update', $entity), [
            'business_type' => PartnerLegalEntityBusinessType::OOO->value,
            'organization_name' => $entity->organization_name ?: $entity->title,
            'bank_account' => '40802810900000000999',
            'bank_corr_account' => '30101810400000000555',
            'is_default' => true,
            'is_enabled' => true,
        ])->assertOk();

        $fresh = $entity->fresh();
        $this->assertSame('40802810900000000999', $fresh->bank_account);
        $this->assertSame('30101810400000000555', $fresh->bank_corr_account);
    }

    public function test_index_modals_render_bank_corr_account_after_settlement_account(): void
    {
        $html = $this->get(route('admin.legal-entities.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('name="bank_corr_account"', $html);
        $this->assertStringContainsString('>Корреспондентский счёт<', $html);
        $this->assertStringContainsString('data-error-for="bank_corr_account"', $html);
        $this->assertStringContainsString('maxlength="20"', $html);

        $accountPos = strpos($html, 'name="bank_account"');
        $corrPos = strpos($html, 'name="bank_corr_account"');
        $this->assertNotFalse($accountPos);
        $this->assertNotFalse($corrPos);
        $this->assertLessThan($corrPos, $accountPos);
    }

    public function test_bank_corr_account_input_is_not_sm_locked_unlike_bank_account(): void
    {
        $crudPath = resource_path('views/admin/legal-entities/partials/crud-fields.blade.php');
        $content = (string) file_get_contents($crudPath);

        $this->assertMatchesRegularExpression(
            '/name="bank_account"[^>]*js-legal-entity-sm-locked|js-legal-entity-sm-locked[^>]*name="bank_account"/',
            $content
        );

        $corrChunkStart = strpos($content, 'name="bank_corr_account"');
        $this->assertNotFalse($corrChunkStart);
        $corrChunk = substr($content, max(0, $corrChunkStart - 120), 280);
        $this->assertStringNotContainsString(
            'js-legal-entity-sm-locked',
            $corrChunk,
            'Корр. счёт не должен блокироваться после sm-register (CRM-only)'
        );
    }

    public function test_index_js_fill_form_hydrates_bank_corr_account(): void
    {
        $path = resource_path('views/admin/legal-entities/index.blade.php');
        $content = (string) file_get_contents($path);

        $fillPos = strpos($content, 'function fillForm(form, data)');
        $this->assertNotFalse($fillPos);
        $fillChunk = substr($content, (int) $fillPos, 1200);

        $this->assertStringContainsString('bank_account: data.bank_account', $fillChunk);
        $this->assertStringContainsString('bank_corr_account: data.bank_corr_account', $fillChunk);

        // Не сбрасывать корр. счёт при пересборке формы из JSON show
        $this->assertStringContainsString('bank_corr_account', $fillChunk);
    }
}
