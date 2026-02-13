<?php

namespace Tests\Feature\Crm;

use App\Models\User;
use App\Models\UserTableSetting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class TeamColumnsSettingsTest extends CrmTestCase
{
    private string $url = '/admin/teams/columns-settings';

    protected function setUp(): void
    {
        parent::setUp();

        // По умолчанию в этих тестах считаем, что доступ есть.
        Gate::define('groups-view', fn () => true);
    }

    /** @test */
    public function get_forbidden_without_groups_view_permission(): void
    {
        Gate::define('groups-view', fn () => false);

        $this->getJson($this->url)->assertStatus(403);
    }

    /** @test */
    public function post_forbidden_without_groups_view_permission(): void
    {
        Gate::define('groups-view', fn () => false);

        $this->postJson($this->url, ['columns' => ['title' => true]])
            ->assertStatus(403);
    }

    /** @test */
    public function guest_cannot_access_get_and_post(): void
    {
        Auth::logout();

        $get = $this->get($this->url);
        $this->assertTrue(in_array($get->getStatusCode(), [302, 401], true));

        $post = $this->post($this->url, ['columns' => ['title' => true]]);
        $this->assertTrue(in_array($post->getStatusCode(), [302, 401], true));
    }

    /** @test */
    public function get_returns_empty_array_when_settings_missing(): void
    {
        UserTableSetting::where('user_id', $this->user->id)
            ->where('table_key', 'teams_index')
            ->delete();

        $this->getJson($this->url)
            ->assertStatus(200)
            ->assertExactJson([]);
    }

    /** @test */
    public function post_validates_columns_required_array(): void
    {
        $this->postJson($this->url, [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['columns']);

        $this->postJson($this->url, ['columns' => 'not-array'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['columns']);
    }

    /** @test */
    public function post_creates_settings_and_normalizes_booleans(): void
    {
        UserTableSetting::where('user_id', $this->user->id)
            ->where('table_key', 'teams_index')
            ->delete();

        $payload = [
            'columns' => [
                'title'  => 'true',
                'price'  => 1,
                'email'  => 'false',
                'active' => 0,
                'any'    => 'on',
                'weird'  => 'abc', // должно стать false
            ],
        ];

        $this->postJson($this->url, $payload)
            ->assertStatus(200)
            ->assertExactJson(['success' => true]);

        $this->assertDatabaseHas('user_table_settings', [
            'user_id'   => $this->user->id,
            'table_key' => 'teams_index',
        ]);

        $setting = UserTableSetting::where('user_id', $this->user->id)
            ->where('table_key', 'teams_index')
            ->firstOrFail();

        $this->assertSame([
            'title'  => true,
            'price'  => true,
            'email'  => false,
            'active' => false,
            'any'    => true,
            'weird'  => false,
        ], $setting->columns);
    }

    /** @test */
    public function post_updates_existing_settings_without_duplicates(): void
    {
        UserTableSetting::updateOrCreate(
            ['user_id' => $this->user->id, 'table_key' => 'teams_index'],
            ['columns' => ['title' => true]]
        );

        $this->assertSame(
            1,
            UserTableSetting::where('user_id', $this->user->id)->where('table_key', 'teams_index')->count()
        );

        $this->postJson($this->url, [
            'columns' => ['title' => 'false', 'phone' => 'true'],
        ])->assertStatus(200);

        $this->assertSame(
            1,
            UserTableSetting::where('user_id', $this->user->id)->where('table_key', 'teams_index')->count()
        );

        $setting = UserTableSetting::where('user_id', $this->user->id)
            ->where('table_key', 'teams_index')
            ->firstOrFail();

        $this->assertSame([
            'title' => false,
            'phone' => true,
        ], $setting->columns);
    }

    /** @test */
    public function get_returns_only_current_user_settings(): void
    {
        $other = User::factory()->create(['partner_id' => $this->partner->id]);

        UserTableSetting::updateOrCreate(
            ['user_id' => $other->id, 'table_key' => 'teams_index'],
            ['columns' => ['title' => false, 'x' => true]]
        );

        UserTableSetting::updateOrCreate(
            ['user_id' => $this->user->id, 'table_key' => 'teams_index'],
            ['columns' => ['title' => true]]
        );

        $this->getJson($this->url)
            ->assertStatus(200)
            ->assertExactJson(['title' => true]);
    }

    /** @test */
    /** @test */
    public function get_returns_empty_array_when_columns_in_db_is_not_array(): void
    {
        // Важно: кладём ВАЛИДНЫЙ JSON, который не является массивом (например JSON-строка)
        DB::table('user_table_settings')->updateOrInsert(
            ['user_id' => $this->user->id, 'table_key' => 'teams_index'],
            ['columns' => json_encode('not-an-array', JSON_UNESCAPED_UNICODE)]
        );

        $this->getJson($this->url)
            ->assertStatus(200)
            ->assertExactJson([]);
    }

    /** @test */
    public function post_rejects_empty_columns_array(): void
    {
        $this->postJson($this->url, ['columns' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['columns']);
    }


}