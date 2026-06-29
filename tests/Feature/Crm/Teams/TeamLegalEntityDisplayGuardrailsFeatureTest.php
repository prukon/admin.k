<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Teams;

use App\Models\PartnerLegalEntity;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Отображение юр. лица в таблице групп vs резолвер.
 */
final class TeamLegalEntityDisplayGuardrailsFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        session(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->asAdmin();
        $this->grantPermissions(['groups.view', 'legal_entities.view']);
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

    public function test_teams_data_shows_fallback_when_bound_entity_is_disabled(): void
    {
        PartnerLegalEntity::factory()
            ->for($this->partner)
            ->registered('SHOP-TEAM-OTHER')
            ->create([
                'title' => 'Второе активное',
                'organization_name' => 'Второе активное',
                'is_default' => false,
            ]);

        PartnerLegalEntity::factory()
            ->for($this->partner)
            ->registered('SHOP-TEAM-DEFAULT')
            ->create([
                'title' => 'Основное юрлицо',
                'organization_name' => 'Основное юрлицо',
                'is_default' => true,
            ]);

        $disabled = PartnerLegalEntity::factory()
            ->for($this->partner)
            ->registered('SHOP-TEAM-DISPLAY')
            ->disabled()
            ->create([
                'title' => 'Отключённое юрлицо',
                'organization_name' => 'Отключённое юрлицо',
                'is_default' => false,
            ]);

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Группа с битой привязкой',
            'legal_entity_id' => $disabled->id,
        ]);

        $response = $this->getJson('/admin/teams/data?draw=1&start=0&length=50&title=Группа с битой');

        $response->assertOk();
        $row = collect($response->json('data'))->firstWhere('id', $team->id);

        $this->assertNotNull($row);
        $this->assertTrue($row['legal_entity_fallback']);
        $this->assertSame('Основное юрлицо', $row['legal_entity_label']);
        $this->assertStringNotContainsString('Отключённое юрлицо', (string) $row['legal_entity_label']);
    }

    public function test_teams_data_shows_entity_title_when_explicit_binding_is_active(): void
    {
        $entity = PartnerLegalEntity::factory()
            ->for($this->partner)
            ->registered('SHOP-TEAM-EXPLICIT')
            ->create([
                'title' => 'Явное юрлицо',
                'organization_name' => 'Явное юрлицо',
                'is_default' => false,
            ]);

        PartnerLegalEntity::factory()
            ->for($this->partner)
            ->registered('SHOP-TEAM-OTHER')
            ->create([
                'title' => 'Другое',
                'organization_name' => 'Другое',
                'is_default' => true,
            ]);

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Группа явная',
            'legal_entity_id' => $entity->id,
        ]);

        $response = $this->getJson('/admin/teams/data?draw=1&start=0&length=50&title=Группа явная');

        $response->assertOk();
        $row = collect($response->json('data'))->firstWhere('id', $team->id);

        $this->assertNotNull($row);
        $this->assertFalse($row['legal_entity_fallback']);
        $this->assertSame('Явное юрлицо', $row['legal_entity_label']);
    }
}
