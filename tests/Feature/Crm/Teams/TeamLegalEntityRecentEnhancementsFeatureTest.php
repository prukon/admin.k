<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Teams;

use App\Models\PartnerLegalEntity;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Недавние доработки колонки «Юр. лицо» на /admin/teams: displayTitle, fallback-hint, UI.
 */
final class TeamLegalEntityRecentEnhancementsFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        session(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->asAdmin();
        $this->grantPermissions(['groups.view', 'legal_entities.view']);
    }

    /** @param list<string> $permissions */
    private function grantPermissions(array $permissions, ?User $actor = null): void
    {
        $roleId = ($actor ?? $this->user)->role_id;

        foreach ($permissions as $permission) {
            DB::table('permission_role')->insertOrIgnore([
                'partner_id' => $this->partner->id,
                'role_id' => $roleId,
                'permission_id' => $this->permissionId($permission),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function test_teams_data_legal_entity_label_uses_organization_name_not_internal_title(): void
    {
        $entity = PartnerLegalEntity::factory()->for($this->partner)->create([
            'title' => 'Школа футбола «Исток»',
            'organization_name' => 'ИП Лютый Игорь Михаилович',
            'is_default' => true,
        ]);
        PartnerLegalEntity::factory()->for($this->partner)->create([
            'title' => 'Второе юрлицо',
            'organization_name' => 'ООО Второе',
            'is_default' => false,
        ]);

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Группа с явным юрлицом',
            'legal_entity_id' => $entity->id,
        ]);

        $response = $this->getJson('/admin/teams/data?draw=1&start=0&length=50&title=Группа с явным');

        $response->assertOk();
        $row = collect($response->json('data'))->firstWhere('id', $team->id);

        $this->assertNotNull($row);
        $this->assertFalse($row['legal_entity_fallback']);
        $this->assertSame('ИП Лютый Игорь Михаилович', $row['legal_entity_label']);
        $this->assertStringNotContainsString('Школа футбола', (string) $row['legal_entity_label']);
    }

    public function test_teams_data_fallback_uses_organization_name_without_default_prefix(): void
    {
        PartnerLegalEntity::factory()->for($this->partner)->create([
            'title' => 'Внутреннее основное',
            'organization_name' => 'ИП Основное публичное',
            'is_default' => true,
        ]);
        PartnerLegalEntity::factory()->for($this->partner)->create([
            'title' => 'Второе',
            'organization_name' => 'ООО Второе',
            'is_default' => false,
        ]);

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Группа fallback org name',
            'legal_entity_id' => null,
        ]);

        $response = $this->getJson('/admin/teams/data?draw=1&start=0&length=50&title=Группа fallback');

        $response->assertOk();
        $row = collect($response->json('data'))->firstWhere('id', $team->id);

        $this->assertNotNull($row);
        $this->assertTrue($row['legal_entity_fallback']);
        $this->assertSame('ИП Основное публичное', $row['legal_entity_label']);
        $this->assertStringNotContainsString('По умолчанию', (string) $row['legal_entity_label']);
    }

    public function test_teams_index_renders_default_legal_entity_hint_script(): void
    {
        PartnerLegalEntity::factory()->for($this->partner)->create(['is_default' => true]);
        PartnerLegalEntity::factory()->for($this->partner)->create(['is_default' => false]);

        $this->get(route('admin.team.index'))
            ->assertOk()
            ->assertSee('renderLegalEntityDefaultHint', false)
            ->assertSee('Юр лицо установленное по умолчанию', false)
            ->assertSee('initTeamsLegalEntityHints', false)
            ->assertSee('kids-tooltip-hint', false)
            ->assertSee('fa-info-circle', false)
            ->assertDontSee('text-bg-warning', false)
            ->assertDontSee('fallback</span>', false);
    }

    public function test_teams_index_modal_uses_display_title_for_legal_entity_options(): void
    {
        PartnerLegalEntity::factory()->for($this->partner)->create([
            'title' => 'Скрытое внутреннее',
            'organization_name' => 'ИП Для селекта',
            'is_default' => true,
        ]);
        PartnerLegalEntity::factory()->for($this->partner)->create([
            'title' => 'Второе скрытое',
            'organization_name' => 'ООО Для селекта 2',
            'is_default' => false,
        ]);

        $this->get(route('admin.team.index'))
            ->assertOk()
            ->assertSee('ИП Для селекта', false)
            ->assertSee('ООО Для селекта 2', false)
            ->assertDontSee('Скрытое внутреннее', false);
    }

    public function test_teams_data_without_legal_entities_view_returns_empty_legal_entity_label(): void
    {
        $actor = $this->createUserWithoutPermission('legal_entities.view', $this->partner);
        $this->actingAs($actor);
        session(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->grantPermissions(['groups.view'], $actor);

        PartnerLegalEntity::factory()->for($this->partner)->create(['is_default' => true]);
        PartnerLegalEntity::factory()->for($this->partner)->create(['is_default' => false]);

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Группа без права юрлиц',
        ]);

        $response = $this->getJson('/admin/teams/data?draw=1&start=0&length=50&title=Группа без права');

        $response->assertOk();
        $row = collect($response->json('data'))->firstWhere('id', $team->id);

        $this->assertNotNull($row);
        $this->assertArrayHasKey('legal_entity_label', $row);
        $this->assertSame('', $row['legal_entity_label']);
        $this->assertFalse($row['legal_entity_fallback']);
    }

    public function test_guest_cannot_access_teams_data_with_legal_entity_fields(): void
    {
        Auth::logout();

        $response = $this->getJson('/admin/teams/data?draw=1&start=0&length=10');

        $this->assertContains($response->getStatusCode(), [302, 401, 403]);
    }

    public function test_user_without_groups_view_gets_403_on_teams_page(): void
    {
        $actor = $this->createUserWithoutPermission('groups.view', $this->partner);
        $this->actingAs($actor);
        session(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->grantPermissions(['legal_entities.view'], $actor);

        $this->get(route('admin.team.index'))->assertForbidden();
        $this->getJson('/admin/teams/data?draw=1&start=0&length=10')->assertForbidden();
    }
}
