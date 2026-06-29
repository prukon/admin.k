<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LegalEntities;

use App\Models\PartnerLegalEntity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * UI и CRUD-улучшения раздела «Юр. лица»: колонки, подписи полей, sm_details по умолчанию.
 */
final class LegalEntitiesUiAndEnhancementsFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
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

    public function test_index_shows_registration_in_bank_column_header(): void
    {
        $this->grantPermissions($this->user, ['legal_entities.view']);

        $this->get(route('admin.legal-entities.index'))
            ->assertOk()
            ->assertSee('Регистрация в банке', false);
    }

    public function test_index_hides_shop_code_column_without_sm_register_permission(): void
    {
        $this->grantPermissions($this->user, ['legal_entities.view', 'legal_entities.manage']);

        $this->get(route('admin.legal-entities.index'))
            ->assertOk()
            ->assertDontSee('<th>ShopCode</th>', false)
            ->assertSee('canSmRegister = false', false);
    }

    public function test_index_shows_shop_code_column_with_sm_register_permission(): void
    {
        $this->grantPermissions($this->user, [
            'legal_entities.view',
            'legal_entities.manage',
            'legal_entities.sm_register',
        ]);

        $this->get(route('admin.legal-entities.index'))
            ->assertOk()
            ->assertSee('<th>ShopCode</th>', false)
            ->assertSee('canSmRegister = true', false);
    }

    public function test_index_datatable_script_uses_registration_action_button_label(): void
    {
        $this->grantPermissions($this->user, [
            'legal_entities.view',
            'legal_entities.manage',
            'legal_entities.sm_register',
        ]);

        $this->get(route('admin.legal-entities.index'))
            ->assertOk()
            ->assertSee('>Регистрация</a>', false);
    }

    public function test_create_modal_has_organization_placeholder_without_school_title(): void
    {
        $this->grantPermissions($this->user, ['legal_entities.view', 'legal_entities.manage']);

        $this->get(route('admin.legal-entities.index'))
            ->assertOk()
            ->assertSee('Наименование организации*', false)
            ->assertDontSee('Название школы/секции*', false)
            ->assertDontSee('name="sms_name"', false)
            ->assertSee('placeholder="ИП Иванов Иван..."', false)
            ->assertSee('Реквизиты для банка', false)
            ->assertSee('Ставка НДС (онлайн-чек)', false)
            ->assertSee('НДС 22%', false)
            ->assertSee('Расчётный НДС 20/120', false);
    }

    public function test_partner_edit_page_has_school_title_and_sms_name_fields(): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
            'permission_id' => $this->permissionId('account.partner.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
            'permission_id' => $this->permissionId('account.partner.update'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->get(route('admin.cur.company.edit'))
            ->assertOk()
            ->assertSee('Название школы/секции*', false)
            ->assertSee('Название для SMS/выписок', false)
            ->assertSee('name="sms_name"', false);
    }

    public function test_store_applies_default_sm_details_template_when_omitted(): void
    {
        $this->grantPermissions($this->user, ['legal_entities.view', 'legal_entities.manage']);

        $this->partner->update(['title' => 'Школа тестовая']);

        $this->postJson(route('admin.legal-entities.store'), [
            'business_type' => 'IP',
            'organization_name' => 'ИП Тестовая школа',
            'is_enabled' => 1,
        ])->assertOk();

        $entity = PartnerLegalEntity::query()
            ->where('partner_id', $this->partner->id)
            ->where('title', 'Школа тестовая')
            ->first();

        $this->assertNotNull($entity);
        $this->assertSame('Выплата по договору, НДС не облагается', $entity->sm_details_template);
    }

    public function test_data_endpoint_includes_registration_labels(): void
    {
        $this->grantPermissions($this->user, ['legal_entities.view']);

        $registered = PartnerLegalEntity::factory()->for($this->partner)->registered('SC-DT-1')->create([
            'title' => 'Зарегистрированное юр. лицо',
        ]);
        PartnerLegalEntity::factory()->for($this->partner)->create([
            'title' => 'Незарегистрированное юр. лицо',
            'tinkoff_shop_code' => null,
        ]);

        $response = $this->getJson(route('admin.legal-entities.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
        ]));

        $response->assertOk();

        $rows = collect($response->json('data'));
        $registeredRow = $rows->firstWhere('id', $registered->id);

        $this->assertNotNull($registeredRow);
        $this->assertSame(1, $registeredRow['is_registered']);
        $this->assertSame('Да', $registeredRow['is_registered_label']);
        $this->assertSame('SC-DT-1', $registeredRow['tinkoff_shop_code']);

        $unregisteredRow = $rows->first(fn (array $row) => ($row['is_registered'] ?? null) === 0);
        $this->assertNotNull($unregisteredRow);
        $this->assertSame('Нет', $unregisteredRow['is_registered_label']);
    }
}
