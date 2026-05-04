<?php

namespace Tests\Feature\Crm\Users;

use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Models\UserField;
use App\Models\UserFieldValue;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Доп. поля пользователя при создании (POST /admin/users): валидация, партнёрская изоляция, editable.
 */
class AdminUsersCustomFieldsFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->asAdmin();
    }

    protected function baseStorePayload(Team $team, Role $role): array
    {
        return [
            'name' => 'Иван',
            'lastname' => 'Тестов',
            'email' => 'cf-' . uniqid('', true) . '@example.com',
            'role_id' => $role->id,
            'team_id' => $team->id,
            'birthday' => '2015-01-01',
            'is_enabled' => 1,
        ];
    }

    public function test_index_returns_ok_and_exposes_fields_and_user_fields_payload(): void
    {
        $field = UserField::factory()
            ->forPartner($this->partner->id)
            ->withSlug('payload_check')
            ->create(['name' => 'Проверка']);

        DB::table('user_field_role')->insert([
            'user_field_id' => $field->id,
            'role_id'       => $this->roleId('admin'),
        ]);

        $response = $this->get(route('admin.user1'));

        $response->assertOk();
        $response->assertViewHas('fields');
        $response->assertViewHas('userFieldsPayload');

        $payload = $response->viewData('userFieldsPayload');
        $this->assertIsArray($payload);
        $slugs = collect($payload)->pluck('slug')->all();
        $this->assertContains('payload_check', $slugs);
    }

    public function test_store_persists_custom_field_values_in_same_request(): void
    {
        $role = Role::where('name', 'user')->firstOrFail();
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);

        $adminRoleId = $this->roleId('admin');

        $field = UserField::factory()
            ->forPartner($this->partner->id)
            ->withSlug('nickname')
            ->create(['name' => 'Никнейм']);

        DB::table('user_field_role')->insert([
            'user_field_id' => $field->id,
            'role_id' => $adminRoleId,
        ]);

        $payload = array_merge($this->baseStorePayload($team, $role), [
            'custom' => ['nickname' => 'Vanya123'],
        ]);

        $response = $this->postJson('/admin/users', $payload, [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertOk();

        $createdId = $response->json('user.id');
        $this->assertNotNull($createdId);

        $row = UserFieldValue::where('user_id', $createdId)
            ->where('field_id', $field->id)
            ->first();

        $this->assertNotNull($row);
        $this->assertSame('Vanya123', $row->value);
    }

    public function test_store_validation_rejects_unknown_custom_slug(): void
    {
        $role = Role::where('name', 'user')->firstOrFail();
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);

        $payload = array_merge($this->baseStorePayload($team, $role), [
            'custom' => ['totally_unknown_slug' => 'x'],
        ]);

        $response = $this->postJson('/admin/users', $payload, [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['custom.totally_unknown_slug']);
    }

    public function test_store_validation_rejects_slug_from_other_partner(): void
    {
        $role = Role::where('name', 'user')->firstOrFail();
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);

        $foreignField = UserField::factory()
            ->forPartner($this->foreignPartner->id)
            ->withSlug('foreign_only')
            ->create(['name' => 'Чужое']);

        $payload = array_merge($this->baseStorePayload($team, $role), [
            'custom' => ['foreign_only' => 'steal'],
        ]);

        $response = $this->postJson('/admin/users', $payload, [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['custom.foreign_only']);

        $this->assertDatabaseMissing('user_field_values', [
            'field_id' => $foreignField->id,
        ]);
    }

    public function test_store_does_not_write_non_editable_custom_even_when_slug_exists_for_partner(): void
    {
        $role = Role::where('name', 'user')->firstOrFail();
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);

        $userRoleId = $this->roleId('user');

        // Поле доступно только роли «user», не «admin» — не входит в editable для текущего актора
        $field = UserField::factory()
            ->forPartner($this->partner->id)
            ->withSlug('only_for_user_role')
            ->create(['name' => 'Секрет']);

        DB::table('user_field_role')->insert([
            'user_field_id' => $field->id,
            'role_id' => $userRoleId,
        ]);

        $payload = array_merge($this->baseStorePayload($team, $role), [
            'custom' => ['only_for_user_role' => 'hacker'],
        ]);

        $response = $this->postJson('/admin/users', $payload, [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertOk();

        $createdId = $response->json('user.id');
        $this->assertNotNull($createdId);

        $this->assertNull(
            UserFieldValue::where('user_id', $createdId)
                ->where('field_id', $field->id)
                ->first()
        );
    }

    public function test_store_accepts_empty_custom_string_and_stores_empty_value(): void
    {
        $role = Role::where('name', 'user')->firstOrFail();
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);

        $adminRoleId = $this->roleId('admin');

        $field = UserField::factory()
            ->forPartner($this->partner->id)
            ->withSlug('optional_note')
            ->create(['name' => 'Заметка']);

        DB::table('user_field_role')->insert([
            'user_field_id' => $field->id,
            'role_id' => $adminRoleId,
        ]);

        $payload = array_merge($this->baseStorePayload($team, $role), [
            'custom' => ['optional_note' => ''],
        ]);

        $response = $this->postJson('/admin/users', $payload, [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertOk();

        $createdId = $response->json('user.id');

        $row = UserFieldValue::where('user_id', $createdId)
            ->where('field_id', $field->id)
            ->first();

        $this->assertNotNull($row);
        $this->assertSame('', $row->value);
    }

    public function test_patch_after_create_updates_custom_field_returns_ok(): void
    {
        $role = Role::where('name', 'user')->firstOrFail();
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);

        $adminRoleId = $this->roleId('admin');

        $field = UserField::factory()
            ->forPartner($this->partner->id)
            ->withSlug('note')
            ->create(['name' => 'Заметка']);

        DB::table('user_field_role')->insert([
            'user_field_id' => $field->id,
            'role_id' => $adminRoleId,
        ]);

        $email = 'patch-cf-' . uniqid('', true) . '@example.com';

        $createResp = $this->postJson('/admin/users', [
            'name' => 'П',
            'lastname' => 'А',
            'email' => $email,
            'role_id' => $role->id,
            'team_id' => $team->id,
            'is_enabled' => 1,
            'custom' => ['note' => 'first'],
        ], ['X-Requested-With' => 'XMLHttpRequest']);

        $createResp->assertOk();
        $userId = $createResp->json('user.id');

        $user = User::findOrFail($userId);

        $patch = $this->patchJson('/admin/users/' . $userId, [
            'name' => $user->name,
            'lastname' => $user->lastname,
            'custom' => ['note' => 'second'],
        ]);

        $patch->assertOk();

        $this->assertSame(
            'second',
            UserFieldValue::where('user_id', $userId)->where('field_id', $field->id)->value('value')
        );
    }
}
