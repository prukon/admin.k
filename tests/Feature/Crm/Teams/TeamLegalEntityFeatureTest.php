<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Teams;

use App\Models\PartnerLegalEntity;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

final class TeamLegalEntityFeatureTest extends CrmTestCase
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

    public function test_teams_index_shows_legal_entity_selector_when_two_or_more_entities(): void
    {
        PartnerLegalEntity::factory()->for($this->partner)->create([
            'title' => 'ЮЛ 1',
            'organization_name' => 'ЮЛ 1',
            'is_default' => true,
        ]);
        PartnerLegalEntity::factory()->for($this->partner)->create([
            'title' => 'ЮЛ 2',
            'organization_name' => 'ЮЛ 2',
            'is_default' => false,
        ]);

        $this->get(route('admin.team.index'))
            ->assertOk()
            ->assertViewHas('multiLegalEntityMode', true)
            ->assertSee('id="legal_entity_id"', false)
            ->assertSee('id="edit-legal-entity-id"', false)
            ->assertSee('ЮЛ 1', false);
    }

    public function test_teams_index_hides_legal_entity_selector_with_single_entity(): void
    {
        PartnerLegalEntity::factory()->for($this->partner)->create(['title' => 'Единственное ЮЛ']);

        $this->get(route('admin.team.index'))
            ->assertOk()
            ->assertViewHas('multiLegalEntityMode', false)
            ->assertDontSee('id="legal_entity_id"', false);
    }

    public function test_store_team_with_legal_entity_id(): void
    {
        $entityA = PartnerLegalEntity::factory()->for($this->partner)->create(['title' => 'ЮЛ A', 'is_default' => true]);
        PartnerLegalEntity::factory()->for($this->partner)->create(['title' => 'ЮЛ B', 'is_default' => false]);

        $this->postJson(route('admin.team.store'), [
            'title' => 'Группа с юр. лицом',
            'default_duration_minutes' => 60,
            'order_by' => 1,
            'is_enabled' => 1,
            'legal_entity_id' => $entityA->id,
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();

        $this->assertDatabaseHas('teams', [
            'partner_id' => $this->partner->id,
            'title' => 'Группа с юр. лицом',
            'legal_entity_id' => $entityA->id,
        ]);
    }

    public function test_store_rejects_foreign_partner_legal_entity(): void
    {
        PartnerLegalEntity::factory()->for($this->partner)->create(['title' => 'ЮЛ 1']);
        PartnerLegalEntity::factory()->for($this->partner)->create(['title' => 'ЮЛ 2']);

        $foreignEntity = PartnerLegalEntity::factory()->for($this->foreignPartner)->create();

        $this->postJson(route('admin.team.store'), [
            'title' => 'Bad legal entity link',
            'default_duration_minutes' => 60,
            'order_by' => 1,
            'is_enabled' => 1,
            'legal_entity_id' => $foreignEntity->id,
        ], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['legal_entity_id']);
    }

    public function test_data_marks_fallback_when_no_legal_entity_bound(): void
    {
        PartnerLegalEntity::factory()->for($this->partner)->create([
            'title' => 'Основное',
            'organization_name' => 'Основное',
            'is_default' => true,
        ]);
        PartnerLegalEntity::factory()->for($this->partner)->create([
            'title' => 'Второе',
            'organization_name' => 'Второе',
            'is_default' => false,
        ]);

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Без привязки',
            'legal_entity_id' => null,
        ]);

        $response = $this->getJson('/admin/teams/data?draw=1&start=0&length=50&title=Без привязки');

        $response->assertOk();
        $row = collect($response->json('data'))->firstWhere('id', $team->id);
        $this->assertNotNull($row);
        $this->assertTrue($row['legal_entity_fallback']);
        $this->assertSame('Основное', $row['legal_entity_label']);
    }

    public function test_update_team_legal_entity_id(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->create(['title' => 'ЮЛ для группы']);
        PartnerLegalEntity::factory()->for($this->partner)->create(['title' => 'ЮЛ 2']);

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'legal_entity_id' => null,
        ]);

        $this->patchJson(route('admin.team.update', $team->id), [
            'title' => $team->title,
            'default_duration_minutes' => 60,
            'order_by' => 10,
            'is_enabled' => 1,
            'legal_entity_id' => $entity->id,
        ])->assertOk();

        $this->assertSame($entity->id, $team->fresh()->legal_entity_id);
    }
}
