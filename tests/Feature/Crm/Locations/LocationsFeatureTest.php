<?php

namespace Tests\Feature\Crm\Locations;

use App\Models\Location;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

final class LocationsFeatureTest extends CrmTestCase
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
        $permId = $this->permissionId($permissionName);

        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
            'permission_id' => $permId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_index_denied_without_view_permission(): void
    {
        $user = $this->createUserWithoutPermission('locations.view');
        $this->actingAs($user);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->get(route('admin.locations.index'))->assertStatus(403);
    }

    public function test_index_ok_with_view_permission(): void
    {
        $this->grantPermission('locations.view');

        $this->get(route('admin.locations.index'))
            ->assertOk()
            ->assertSee('Локации');
    }

    public function test_index_ok_for_admin_by_default_base_permissions(): void
    {
        $this->asAdmin();
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->get(route('admin.locations.index'))
            ->assertOk();
    }

    public function test_store_forbidden_without_manage_permission(): void
    {
        $this->grantPermission('locations.view');

        $this->post(route('admin.locations.store'), [
            'name' => 'Кабинет 1',
            'is_enabled' => 1,
        ])->assertStatus(403);
    }

    public function test_store_creates_partner_scoped_location(): void
    {
        $this->grantPermission('locations.view');
        $this->grantPermission('locations.manage');

        $this->postJson(route('admin.locations.store'), [
            'name' => 'Кабинет 1',
            'address' => 'Адрес',
            'is_enabled' => 1,
        ])->assertOk();

        $this->assertDatabaseHas('locations', [
            'partner_id' => $this->partner->id,
            'name' => 'Кабинет 1',
        ]);
    }

    public function test_store_rejects_duplicate_name_within_partner(): void
    {
        $this->grantPermission('locations.view');
        $this->grantPermission('locations.manage');

        Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Кабинет 1',
        ]);

        $this->postJson(route('admin.locations.store'), [
            'name' => 'Кабинет 1',
            'is_enabled' => 1,
        ])->assertStatus(422)
            ->assertJsonPath('errors.name.0', 'Локация с таким названием уже существует');
    }

    public function test_show_returns_404_for_foreign_partner_location(): void
    {
        $this->grantPermission('locations.view');

        $foreign = Location::factory()->create([
            'partner_id' => $this->foreignPartner->id,
        ]);

        $this->getJson(route('admin.locations.show', $foreign->id))
            ->assertStatus(404);
    }

    public function test_show_returns_200_for_own_location(): void
    {
        $this->grantPermission('locations.view');

        $loc = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Кабинет A',
        ]);

        $this->getJson(route('admin.locations.show', $loc->id))
            ->assertOk()
            ->assertJsonPath('id', $loc->id)
            ->assertJsonPath('name', 'Кабинет A');
    }

    public function test_update_forbidden_without_manage_permission(): void
    {
        $this->grantPermission('locations.view');

        $loc = Location::factory()->create([
            'partner_id' => $this->partner->id,
        ]);

        $this->putJson(route('admin.locations.update', $loc->id), [
            'name' => 'Новая',
            'is_enabled' => 1,
        ])->assertStatus(403);
    }

    public function test_update_returns_200_and_updates_location(): void
    {
        $this->grantPermission('locations.view');
        $this->grantPermission('locations.manage');

        $loc = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Кабинет B',
            'is_enabled' => true,
        ]);

        $this->putJson(route('admin.locations.update', $loc->id), [
            'name' => 'Кабинет B2',
            'address' => 'Адрес 2',
            'description' => 'Описание',
            'is_enabled' => 0,
        ])->assertOk();

        $this->assertDatabaseHas('locations', [
            'id' => $loc->id,
            'partner_id' => $this->partner->id,
            'name' => 'Кабинет B2',
            'address' => 'Адрес 2',
            'description' => 'Описание',
            'is_enabled' => 0,
        ]);
    }

    public function test_destroy_forbidden_without_manage_permission(): void
    {
        $this->grantPermission('locations.view');

        $loc = Location::factory()->create([
            'partner_id' => $this->partner->id,
        ]);

        $this->deleteJson(route('admin.locations.destroy', $loc->id))
            ->assertStatus(403);
    }

    public function test_destroy_returns_200_and_deletes_location(): void
    {
        $this->grantPermission('locations.view');
        $this->grantPermission('locations.manage');

        $loc = Location::factory()->create([
            'partner_id' => $this->partner->id,
        ]);

        $this->deleteJson(route('admin.locations.destroy', $loc->id))
            ->assertOk();

        $this->assertDatabaseMissing('locations', [
            'id' => $loc->id,
        ]);
    }
}

