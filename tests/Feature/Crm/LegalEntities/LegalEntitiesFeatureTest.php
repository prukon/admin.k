<?php

namespace Tests\Feature\Crm\LegalEntities;

use App\Enums\PartnerLegalEntityBusinessType;
use App\Models\PartnerLegalEntity;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

final class LegalEntitiesFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    private function grantPermission(string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_index_denied_without_view_permission(): void
    {
        $user = $this->createUserWithoutPermission('legal_entities.view');
        $this->actingAs($user);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->get(route('admin.legal-entities.index'))->assertStatus(403);
    }

    public function test_index_ok_with_view_permission(): void
    {
        $this->grantPermission('legal_entities.view');

        $this->get(route('admin.legal-entities.index'))
            ->assertOk()
            ->assertSee('Юр. лица')
            ->assertSee('id="legal-entities-table"', false);
    }

    public function test_data_returns_partner_scoped_entities(): void
    {
        $this->grantPermission('legal_entities.view');

        $own = PartnerLegalEntity::factory()->for($this->partner)->create([
            'title' => 'ООО Тестовое',
            'organization_name' => 'ООО Тестовое',
            'business_type' => PartnerLegalEntityBusinessType::OOO,
            'tax_id' => '7701234567',
        ]);

        PartnerLegalEntity::factory()->for($this->foreignPartner)->create([
            'title' => 'Чужое юр. лицо',
        ]);

        $this->getJson(route('admin.legal-entities.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]))
            ->assertOk()
            ->assertJsonPath('recordsTotal', 1)
            ->assertJsonPath('data.0.id', $own->id)
            ->assertJsonPath('data.0.title', 'ООО Тестовое')
            ->assertJsonPath('data.0.business_type_label', 'ООО');
    }

    public function test_store_requires_manage_permission(): void
    {
        $this->grantPermission('legal_entities.view');

        $this->postJson(route('admin.legal-entities.store'), [
            'business_type' => 'OOO',
            'title' => 'Новое ООО',
        ])->assertStatus(403);
    }

    public function test_store_creates_entity_and_first_is_default(): void
    {
        $this->grantPermission('legal_entities.view');
        $this->grantPermission('legal_entities.manage');

        $this->postJson(route('admin.legal-entities.store'), [
            'business_type' => 'IP',
            'title' => 'ИП Иванов',
            'organization_name' => 'ИП Иванов',
            'tax_id' => '123456789012',
            'is_enabled' => 1,
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Юр. лицо создано');

        $entity = PartnerLegalEntity::query()->where('partner_id', $this->partner->id)->first();
        $this->assertNotNull($entity);
        $this->assertTrue($entity->is_default);
        $this->assertSame(PartnerLegalEntityBusinessType::IP, $entity->business_type);
    }

    public function test_store_validation_errors_returned_to_frontend(): void
    {
        $this->grantPermission('legal_entities.view');
        $this->grantPermission('legal_entities.manage');

        $this->postJson(route('admin.legal-entities.store'), [
            'business_type' => 'INVALID',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['business_type']);
    }

    public function test_destroy_blocked_when_teams_linked(): void
    {
        $this->grantPermission('legal_entities.view');
        $this->grantPermission('legal_entities.manage');

        $entity = PartnerLegalEntity::factory()->for($this->partner)->create();
        Team::factory()->create([
            'partner_id' => $this->partner->id,
            'legal_entity_id' => $entity->id,
        ]);

        $this->deleteJson(route('admin.legal-entities.destroy', $entity))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['legal_entity']);
    }

    public function test_show_page_accessible_for_own_entity(): void
    {
        $this->grantPermission('legal_entities.view');
        $this->grantPermission('legal_entities.sm_register');

        $entity = PartnerLegalEntity::factory()->for($this->partner)->create([
            'title' => 'АНО Спорт',
            'business_type' => PartnerLegalEntityBusinessType::ANO,
        ]);

        $this->get(route('admin.legal-entities.show', $entity))
            ->assertOk()
            ->assertSee('АНО Спорт')
            ->assertSee('sm-register');
    }

    public function test_show_foreign_entity_returns_404(): void
    {
        $this->grantPermission('legal_entities.view');
        $this->grantPermission('legal_entities.sm_register');

        $foreign = PartnerLegalEntity::factory()->for($this->foreignPartner)->create();

        $this->get(route('admin.legal-entities.show', $foreign))->assertStatus(404);
    }

    public function test_directories_tab_visible_on_index(): void
    {
        $this->grantPermission('legal_entities.view');

        $this->get(route('admin.legal-entities.index'))
            ->assertOk()
            ->assertSee('Юр. лица', false)
            ->assertSee('directoriesSectionTabs', false);
    }
}
