<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LegalEntities;

use App\Models\PartnerLegalEntity;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Недавние доработки раздела «Юр. лица»:
 * displayTitle, обязательное organization_name, подсказка регистрации, showErrorModal при удалении.
 */
final class LegalEntitiesRecentEnhancementsFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->asAdmin();
        $this->grantPermissions(['legal_entities.view', 'legal_entities.manage']);
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

    public function test_display_title_prefers_organization_name_over_internal_title(): void
    {
        $entity = PartnerLegalEntity::factory()->make([
            'title' => 'Школа футбола «Исток»',
            'organization_name' => 'ИП Лютый Игорь Михаилович',
        ]);

        $this->assertSame('ИП Лютый Игорь Михаилович', $entity->displayTitle());
    }

    public function test_display_title_falls_back_to_title_when_organization_name_empty(): void
    {
        $entity = PartnerLegalEntity::factory()->make([
            'title' => 'ООО Fallback Title',
            'organization_name' => null,
        ]);

        $this->assertSame('ООО Fallback Title', $entity->displayTitle());
    }

    public function test_data_endpoint_title_uses_display_title(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->create([
            'title' => 'Внутреннее название',
            'organization_name' => 'ИП Публичное наименование',
        ]);

        $response = $this->getJson(route('admin.legal-entities.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
        ]));

        $response->assertOk();
        $row = collect($response->json('data'))->firstWhere('id', $entity->id);

        $this->assertNotNull($row);
        $this->assertSame('ИП Публичное наименование', $row['title']);
        $this->assertStringNotContainsString('Внутреннее название', (string) $row['title']);
    }

    public function test_store_ajax_requires_organization_name(): void
    {
        $this->postJson(route('admin.legal-entities.store'), [
            'business_type' => 'OOO',
            'is_enabled' => 1,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['organization_name']);
    }

    public function test_update_ajax_requires_organization_name(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->create([
            'title' => 'До update org required',
            'organization_name' => 'Было заполнено',
        ]);

        $this->putJson(route('admin.legal-entities.update', $entity), [
            'business_type' => 'OOO',
            'organization_name' => '',
            'is_default' => true,
            'is_enabled' => true,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['organization_name']);
    }

    public function test_store_non_ajax_without_organization_name_redirects_with_errors(): void
    {
        $this->from(route('admin.legal-entities.index'))
            ->post(route('admin.legal-entities.store'), [
                'business_type' => 'OOO',
                'is_enabled' => 1,
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['organization_name']);

        $this->assertDatabaseMissing('partner_legal_entities', [
            'partner_id' => $this->partner->id,
            'organization_name' => '',
        ]);
    }

    public function test_destroy_ajax_when_teams_linked_returns_structured_422(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->create([
            'organization_name' => 'ООО С привязанными группами',
        ]);

        Team::factory()->create([
            'partner_id' => $this->partner->id,
            'legal_entity_id' => $entity->id,
        ]);

        $this->deleteJson(route('admin.legal-entities.destroy', $entity))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['legal_entity'])
            ->assertJsonPath('message', 'Нельзя удалить юр. лицо, привязанное к группам')
            ->assertJsonPath('errors.legal_entity.0', 'Сначала отвяжите группы от этого юр. лица');

        $this->assertDatabaseHas('partner_legal_entities', [
            'id' => $entity->id,
            'deleted_at' => null,
        ]);
    }

    public function test_index_create_modal_organization_name_is_required(): void
    {
        $this->get(route('admin.legal-entities.index'))
            ->assertOk()
            ->assertSee('Наименование организации*', false)
            ->assertSee('name="organization_name"', false)
            ->assertSee('name="organization_name" placeholder="ИП Иванов Иван..."', false)
            ->assertSee('required', false);
    }

    public function test_index_registration_column_uses_styled_hint_markup(): void
    {
        $this->get(route('admin.legal-entities.index'))
            ->assertOk()
            ->assertSee('initLegalEntitiesRegisteredHints', false)
            ->assertSee('Обратитесь к администратору платформы', false)
            ->assertSee('kids-tooltip-hint', false)
            ->assertSee('fa-info-circle', false)
            ->assertSee('ulp-assignment-paid-tooltip', false);
    }

    public function test_index_delete_error_handler_uses_show_error_modal(): void
    {
        $this->get(route('admin.legal-entities.index'))
            ->assertOk()
            ->assertSee('showErrorModal', false)
            ->assertSee("showErrorModal('Удаление юр. лица', msg, 0)", false)
            ->assertSee('hidden.bs.modal.return', false)
            ->assertDontSee('alert(msg)', false);
    }
}
