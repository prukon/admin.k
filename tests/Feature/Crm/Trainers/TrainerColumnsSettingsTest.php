<?php

namespace Tests\Feature\Crm\Trainers;

use App\Models\UserTableSetting;
use Illuminate\Support\Facades\Auth;
use Tests\Feature\Crm\CrmTestCase;

final class TrainerColumnsSettingsTest extends CrmTestCase
{
    private string $url = '/admin/trainers/columns-settings';

    protected function setUp(): void
    {
        parent::setUp();
        session(['current_partner' => $this->partner->id, '2fa:passed' => true]);
    }

    private function grantTrainersView(): void
    {
        \Illuminate\Support\Facades\DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
            'permission_id' => $this->permissionId('trainers.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_get_forbidden_without_trainers_view_permission(): void
    {
        $actor = $this->createUserWithoutPermission('trainers.view');
        $this->actingAs($actor);

        $this->getJson($this->url)->assertStatus(403);
    }

    public function test_post_forbidden_without_trainers_view_permission(): void
    {
        $actor = $this->createUserWithoutPermission('trainers.view');
        $this->actingAs($actor);

        $this->postJson($this->url, ['columns' => ['full_name' => true]])
            ->assertStatus(403);
    }

    public function test_get_returns_empty_array_when_no_settings(): void
    {
        $this->grantTrainersView();

        $this->getJson($this->url)
            ->assertOk()
            ->assertExactJson([]);
    }

    public function test_post_saves_columns_for_trainers_index(): void
    {
        $this->grantTrainersView();

        $payload = [
            'avatar' => true,
            'full_name' => false,
            'teams_label' => true,
            'email' => true,
            'default_base_salary' => true,
            'default_rate_per_training' => true,
            'sort_order' => true,
            'is_enabled' => true,
            'actions' => true,
        ];

        $this->postJson($this->url, ['columns' => $payload])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('user_table_settings', [
            'user_id' => $this->user->id,
            'table_key' => 'trainers_index',
        ]);

        $setting = UserTableSetting::query()
            ->where('user_id', $this->user->id)
            ->where('table_key', 'trainers_index')
            ->first();

        $this->assertNotNull($setting);
        $this->assertFalse($setting->columns['full_name']);
        $this->assertTrue($setting->columns['avatar']);
    }

    public function test_guest_cannot_access_columns_settings(): void
    {
        Auth::logout();

        $get = $this->get($this->url);
        $this->assertTrue(in_array($get->getStatusCode(), [302, 401], true));

        $post = $this->post($this->url, ['columns' => ['full_name' => true]]);
        $this->assertTrue(in_array($post->getStatusCode(), [302, 401], true));
    }
}
