<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LegalEntities;

use App\Models\PartnerLegalEntity;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Контроль доступа к разделу «Юр. лица»: guest, legal_entities.view, legal_entities.manage.
 */
final class LegalEntitiesAccessFeatureTest extends CrmTestCase
{
    private PartnerLegalEntity $entity;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->entity = PartnerLegalEntity::factory()->for($this->partner)->create([
            'title' => 'Access test entity',
        ]);
    }

    /**
     * @return list<array{0: string, 1: string, 2?: array<string, mixed>}>
     */
    private function allLegalEntityRoutes(): array
    {
        $disposable = PartnerLegalEntity::factory()->for($this->partner)->create([
            'title' => 'Disposable access entity',
        ]);

        return [
            ['GET', route('admin.legal-entities.index')],
            ['GET', route('admin.legal-entities.data', ['draw' => 1, 'start' => 0, 'length' => 10])],
            ['GET', route('admin.legal-entities.columns-settings.get')],
            ['POST', route('admin.legal-entities.columns-settings.save'), ['columns' => ['title' => true]]],
            ['GET', route('logs.data.legal-entity', ['draw' => 1, 'start' => 0, 'length' => 10])],
            ['GET', route('admin.legal-entities.show', $this->entity)],
            ['POST', route('admin.legal-entities.store'), $this->minimalStorePayload()],
            ['PUT', route('admin.legal-entities.update', $this->entity), $this->minimalUpdatePayload()],
            ['DELETE', route('admin.legal-entities.destroy', $disposable)],
            ['POST', route('admin.legal-entities.sm-register', $this->entity), $this->minimalSmPayload()],
            ['POST', route('admin.legal-entities.sm-patch', $this->entity), $this->minimalSmPayload()],
            ['POST', route('admin.legal-entities.sm-refresh', $this->entity)],
            ['POST', route('admin.legal-entities.sm-pull', $this->entity)],
        ];
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

    public function test_guest_cannot_access_legal_entities_routes(): void
    {
        Auth::logout();

        foreach ($this->allLegalEntityRoutes() as $route) {
            [$method, $url] = $route;
            $data = $route[2] ?? [];

            $response = $this->call($method, $url, $data);

            $this->assertContains(
                $response->getStatusCode(),
                [302, 401, 403, 419],
                "Гость: {$method} {$url} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_user_without_legal_entities_view_gets_403_on_all_routes(): void
    {
        $denied = $this->createUserWithoutPermission('legal_entities.view', $this->partner);
        $this->actingAs($denied);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        foreach ($this->allLegalEntityRoutes() as $route) {
            [$method, $url] = $route;
            $data = $route[2] ?? [];

            $response = $this->call(
                $method,
                $url,
                $data,
                [],
                [],
                ['HTTP_ACCEPT' => 'application/json']
            );

            $this->assertSame(
                403,
                $response->getStatusCode(),
                "Без legal_entities.view: {$method} {$url} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_user_with_view_only_can_read_but_not_mutate(): void
    {
        $actor = $this->createUserWithoutPermission('legal_entities.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->grantPermissions($actor, ['legal_entities.view']);

        $this->get(route('admin.legal-entities.index'))
            ->assertOk()
            ->assertSee('Юр. лица', false);

        $this->getJson(route('admin.legal-entities.data', ['draw' => 1, 'start' => 0, 'length' => 10]))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);

        $this->getJson(route('admin.legal-entities.columns-settings.get'))
            ->assertOk();

        $this->postJson(route('admin.legal-entities.columns-settings.save'), [
            'columns' => ['title' => true, 'tax_id' => false],
        ])->assertOk()
            ->assertJsonPath('success', true);

        $this->getJson(route('logs.data.legal-entity', ['draw' => 1, 'start' => 0, 'length' => 10]))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);

        $this->get(route('admin.legal-entities.show', $this->entity))
            ->assertForbidden();

        $this->getJson(route('admin.legal-entities.show', $this->entity))
            ->assertForbidden();

        $this->postJson(route('admin.legal-entities.store'), $this->minimalStorePayload())
            ->assertForbidden();

        $this->putJson(route('admin.legal-entities.update', $this->entity), $this->minimalUpdatePayload())
            ->assertForbidden();

        $disposable = PartnerLegalEntity::factory()->for($this->partner)->create();
        $this->deleteJson(route('admin.legal-entities.destroy', $disposable))
            ->assertForbidden();

        $this->postJson(route('admin.legal-entities.sm-register', $this->entity), $this->minimalSmPayload())
            ->assertForbidden();

        $this->postJson(route('admin.legal-entities.sm-patch', $this->entity), $this->minimalSmPayload())
            ->assertForbidden();

        $this->postJson(route('admin.legal-entities.sm-refresh', $this->entity))
            ->assertForbidden();

        $this->postJson(route('admin.legal-entities.sm-pull', $this->entity))
            ->assertForbidden();
    }

    public function test_user_with_view_and_sm_register_can_open_card_but_not_mutate(): void
    {
        $actor = $this->createUserWithoutPermission('legal_entities.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->grantPermissions($actor, ['legal_entities.view', 'legal_entities.sm_register']);

        $this->get(route('admin.legal-entities.show', $this->entity))
            ->assertOk()
            ->assertSee('Access test entity', false);

        $this->getJson(route('admin.legal-entities.show', $this->entity))
            ->assertForbidden();

        $this->postJson(route('admin.legal-entities.store'), $this->minimalStorePayload())
            ->assertForbidden();
    }

    public function test_user_with_view_and_manage_can_access_read_endpoints(): void
    {
        $this->asAdmin();
        $this->grantPermissions($this->user, ['legal_entities.view', 'legal_entities.manage', 'legal_entities.sm_register']);

        $this->get(route('admin.legal-entities.index'))->assertOk();
        $this->getJson(route('admin.legal-entities.data', ['draw' => 1, 'start' => 0, 'length' => 10]))->assertOk();
        $this->get(route('admin.legal-entities.show', $this->entity))->assertOk();
    }

    public function test_user_with_manage_but_without_sm_register_cannot_open_show_html(): void
    {
        $actor = $this->createUserWithoutPermission('legal_entities.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->grantPermissions($actor, ['legal_entities.view', 'legal_entities.manage']);

        $this->get(route('admin.legal-entities.show', $this->entity))
            ->assertForbidden();
    }

    public function test_user_with_manage_but_without_sm_register_can_load_entity_json_for_edit_modal(): void
    {
        $actor = $this->createUserWithoutPermission('legal_entities.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->grantPermissions($actor, ['legal_entities.view', 'legal_entities.manage']);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('admin.legal-entities.show', $this->entity))
            ->assertOk()
            ->assertJsonPath('id', $this->entity->id)
            ->assertJsonPath('title', 'Access test entity');
    }

    public function test_user_with_sm_register_but_without_manage_cannot_call_sm_register(): void
    {
        $actor = $this->createUserWithoutPermission('legal_entities.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->grantPermissions($actor, ['legal_entities.view', 'legal_entities.sm_register']);

        $this->get(route('admin.legal-entities.show', $this->entity))->assertOk();

        $this->postJson(route('admin.legal-entities.sm-register', $this->entity), $this->minimalSmPayload())
            ->assertForbidden();
    }

    /**
     * @return array<string, mixed>
     */
    private function minimalStorePayload(): array
    {
        return [
            'business_type' => 'OOO',
            'organization_name' => 'ООО Access',
            'is_enabled' => 1,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function minimalUpdatePayload(): array
    {
        return [
            'business_type' => 'OOO',
            'organization_name' => $this->entity->organization_name ?: 'Access test entity',
            'is_default' => true,
            'is_enabled' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function minimalSmPayload(): array
    {
        return [
            'business_type' => 'OOO',
            'title' => 'ООО SM Access',
            'organization_name' => 'ООО SM Access',
            'email' => 'sm-access@example.test',
            'tax_id' => '7700000099',
            'registration_number' => '1234567890123',
            'address' => 'ул. Тестовая, 1',
            'city' => 'Москва',
            'zip' => '101000',
            'bank_name' => 'Т-Банк',
            'bank_bik' => '044525974',
            'bank_account' => '40702810900000000001',
            'sm_details_template' => 'Назначение платежа',
        ];
    }
}
