<?php

namespace Tests\Feature\Crm\Partners;

use App\Models\User;
use App\Models\UserTableSetting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

class PartnerColumnsSettingsTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->asAdmin();
        $this->grantPartnerView();
    }

    public function test_get_forbidden_without_partner_view_permission(): void
    {
        $actor = $this->createUserWithoutPermission('partner.view', $this->partner);
        $this->actingAs($actor);

        $this->getJson(route('admin.partner.columns-settings.get'))->assertStatus(403);
    }

    public function test_post_forbidden_without_partner_view_permission(): void
    {
        $actor = $this->createUserWithoutPermission('partner.view', $this->partner);
        $this->actingAs($actor);

        $this->postJson(route('admin.partner.columns-settings.save'), [
            'columns' => ['title' => true],
        ])->assertStatus(403);
    }

    public function test_guest_cannot_access_get_and_post(): void
    {
        Auth::logout();

        $get = $this->get(route('admin.partner.columns-settings.get'));
        $this->assertContains($get->getStatusCode(), [302, 401], true);

        $post = $this->post(route('admin.partner.columns-settings.save'), [
            'columns' => ['title' => true],
        ]);
        $this->assertContains($post->getStatusCode(), [302, 401], true);
    }

    public function test_get_returns_empty_array_when_settings_missing(): void
    {
        UserTableSetting::where('user_id', $this->user->id)
            ->where('table_key', 'partners_index')
            ->delete();

        $this->getJson(route('admin.partner.columns-settings.get'))
            ->assertOk()
            ->assertExactJson([]);
    }

    public function test_post_validates_columns_required_array(): void
    {
        $this->postJson(route('admin.partner.columns-settings.save'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['columns']);

        $this->postJson(route('admin.partner.columns-settings.save'), [
            'columns' => 'not-array',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['columns']);
    }

    public function test_post_creates_settings_and_normalizes_booleans(): void
    {
        UserTableSetting::where('user_id', $this->user->id)
            ->where('table_key', 'partners_index')
            ->delete();

        $this->postJson(route('admin.partner.columns-settings.save'), [
            'columns' => [
                'title'  => 'true',
                'email'  => 1,
                'phone'  => 'false',
                'actions' => 0,
                'weird'  => 'abc',
            ],
        ])
            ->assertOk()
            ->assertExactJson(['success' => true]);

        $setting = UserTableSetting::where('user_id', $this->user->id)
            ->where('table_key', 'partners_index')
            ->firstOrFail();

        $this->assertSame([
            'title'   => true,
            'email'   => true,
            'phone'   => false,
            'actions' => false,
            'weird'   => false,
        ], $setting->columns);
    }

    public function test_post_updates_existing_settings_without_duplicates(): void
    {
        UserTableSetting::updateOrCreate(
            ['user_id' => $this->user->id, 'table_key' => 'partners_index'],
            ['columns' => ['title' => true]]
        );

        $this->postJson(route('admin.partner.columns-settings.save'), [
            'columns' => ['title' => 'false', 'tax_id' => 'true'],
        ])->assertOk();

        $this->assertSame(
            1,
            UserTableSetting::where('user_id', $this->user->id)
                ->where('table_key', 'partners_index')
                ->count()
        );

        $setting = UserTableSetting::where('user_id', $this->user->id)
            ->where('table_key', 'partners_index')
            ->firstOrFail();

        $this->assertSame([
            'title'  => false,
            'tax_id' => true,
        ], $setting->columns);
    }

    public function test_get_returns_only_current_user_settings(): void
    {
        $other = User::factory()->create(['partner_id' => $this->partner->id]);

        UserTableSetting::updateOrCreate(
            ['user_id' => $other->id, 'table_key' => 'partners_index'],
            ['columns' => ['title' => false, 'email' => true]]
        );

        UserTableSetting::updateOrCreate(
            ['user_id' => $this->user->id, 'table_key' => 'partners_index'],
            ['columns' => ['title' => true]]
        );

        $this->getJson(route('admin.partner.columns-settings.get'))
            ->assertOk()
            ->assertExactJson(['title' => true]);
    }

    public function test_get_returns_empty_array_when_columns_in_db_is_not_array(): void
    {
        DB::table('user_table_settings')->updateOrInsert(
            ['user_id' => $this->user->id, 'table_key' => 'partners_index'],
            ['columns' => json_encode('not-an-array', JSON_UNESCAPED_UNICODE)]
        );

        $this->getJson(route('admin.partner.columns-settings.get'))
            ->assertOk()
            ->assertExactJson([]);
    }

    public function test_post_rejects_empty_columns_array(): void
    {
        $this->postJson(route('admin.partner.columns-settings.save'), [
            'columns' => [],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['columns']);
    }

    private function grantPartnerView(): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $this->user->role_id,
            'permission_id' => $this->permissionId('partner.view'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }
}
