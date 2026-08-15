<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Users;

use App\Models\User;
use App\Models\UserTableSetting;
use Illuminate\Support\Facades\Auth;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Персональное «Показать N» на /admin/users: page_length в user_table_settings.
 */
final class UsersPageLengthFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->asAdmin();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);
    }

    public function test_index_uses_default_page_length_when_nothing_saved(): void
    {
        UserTableSetting::where('user_id', $this->user->id)
            ->where('table_key', 'users_index')
            ->delete();

        $html = $this->get(route('admin.user1'))
            ->assertOk()
            ->assertViewHas('usersPageLength', 10)
            ->getContent();

        $this->assertStringContainsString('persistPageLength: true', $html);
        $this->assertMatchesRegularExpression('/pageLength:\s*10\b/', $html);
    }

    public function test_index_uses_saved_page_length_for_current_admin(): void
    {
        UserTableSetting::updateOrCreate(
            ['user_id' => $this->user->id, 'table_key' => 'users_index'],
            ['page_length' => 50]
        );

        $html = $this->get(route('admin.user1'))
            ->assertOk()
            ->assertViewHas('usersPageLength', 50)
            ->getContent();

        $this->assertMatchesRegularExpression('/pageLength:\s*50\b/', $html);
        $this->assertDoesNotMatchRegularExpression('/pageLength:\s*10\b/', $html);
    }

    public function test_index_falls_back_to_default_when_stored_page_length_is_invalid(): void
    {
        UserTableSetting::updateOrCreate(
            ['user_id' => $this->user->id, 'table_key' => 'users_index'],
            ['page_length' => 99]
        );

        $this->get(route('admin.user1'))
            ->assertOk()
            ->assertViewHas('usersPageLength', 10);
    }

    public function test_index_does_not_use_another_admins_page_length(): void
    {
        $other = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->user->role_id,
        ]);

        UserTableSetting::updateOrCreate(
            ['user_id' => $other->id, 'table_key' => 'users_index'],
            ['page_length' => 100]
        );

        $this->get(route('admin.user1'))
            ->assertOk()
            ->assertViewHas('usersPageLength', 10);
    }

    public function test_ajax_save_page_length_persists_without_columns(): void
    {
        UserTableSetting::where('user_id', $this->user->id)
            ->where('table_key', 'users_index')
            ->delete();

        $this->postJson(route('admin.users.table-settings.save'), [
            'page_length' => 20,
        ])
            ->assertOk()
            ->assertExactJson(['success' => true]);

        $setting = UserTableSetting::where('user_id', $this->user->id)
            ->where('table_key', 'users_index')
            ->firstOrFail();

        $this->assertSame(20, $setting->page_length);
        $this->assertNull($setting->columns);

        $payload = $this->getJson(route('admin.users.table-settings.get'))
            ->assertOk()
            ->assertExactJson([])
            ->json();

        $this->assertArrayNotHasKey('page_length', $payload);
    }

    public function test_ajax_save_page_length_does_not_wipe_columns(): void
    {
        UserTableSetting::updateOrCreate(
            ['user_id' => $this->user->id, 'table_key' => 'users_index'],
            ['columns' => ['phone' => false, 'name' => true]]
        );

        $this->postJson(route('admin.users.table-settings.save'), [
            'page_length' => 100,
        ])->assertOk();

        $setting = UserTableSetting::where('user_id', $this->user->id)
            ->where('table_key', 'users_index')
            ->firstOrFail();

        $this->assertSame(100, $setting->page_length);
        $this->assertSame([
            'phone' => false,
            'name'  => true,
        ], $setting->columns);
    }

    public function test_ajax_save_columns_does_not_wipe_page_length(): void
    {
        UserTableSetting::updateOrCreate(
            ['user_id' => $this->user->id, 'table_key' => 'users_index'],
            [
                'columns'     => ['phone' => true],
                'page_length' => 50,
            ]
        );

        $this->postJson(route('admin.users.table-settings.save'), [
            'columns' => ['phone' => false, 'name' => true],
        ])->assertOk();

        $setting = UserTableSetting::where('user_id', $this->user->id)
            ->where('table_key', 'users_index')
            ->firstOrFail();

        $this->assertSame(50, $setting->page_length);
        $this->assertSame([
            'phone' => false,
            'name'  => true,
        ], $setting->columns);
    }

    public function test_ajax_save_invalid_page_length_returns_422_with_field_error(): void
    {
        $this->postJson(route('admin.users.table-settings.save'), [
            'page_length' => 15,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['page_length'])
            ->assertJsonPath('errors.page_length.0', 'Можно показать 10, 20, 50 или 100 записей.');

        $this->assertSame(
            0,
            UserTableSetting::where('user_id', $this->user->id)
                ->where('table_key', 'users_index')
                ->count()
        );
    }

    public function test_ajax_save_accepts_each_allowed_page_length(): void
    {
        foreach (UserTableSetting::PAGE_LENGTHS as $length) {
            $this->postJson(route('admin.users.table-settings.save'), [
                'page_length' => $length,
            ])
                ->assertOk()
                ->assertJson(['success' => true]);

            $this->assertSame(
                $length,
                UserTableSetting::where('user_id', $this->user->id)
                    ->where('table_key', 'users_index')
                    ->value('page_length')
            );
        }
    }

    public function test_get_does_not_return_page_length_key_when_it_is_saved(): void
    {
        UserTableSetting::updateOrCreate(
            ['user_id' => $this->user->id, 'table_key' => 'users_index'],
            [
                'columns'     => ['phone' => false],
                'page_length' => 20,
            ]
        );

        $payload = $this->getJson(route('admin.users.table-settings.get'))
            ->assertOk()
            ->assertExactJson(['phone' => false])
            ->json();

        $this->assertArrayNotHasKey('page_length', $payload);
    }

    public function test_non_ajax_save_page_length_returns_json_and_persists(): void
    {
        $response = $this->from(route('admin.user1'))
            ->post(route('admin.users.table-settings.save'), [
                'page_length' => '50',
            ]);

        $this->assertSame(200, $response->getStatusCode());
        $response->assertJson(['success' => true]);

        $this->assertSame(
            50,
            UserTableSetting::where('user_id', $this->user->id)
                ->where('table_key', 'users_index')
                ->value('page_length')
        );
    }

    public function test_non_ajax_invalid_page_length_redirects_back_with_field_error(): void
    {
        $this->from(route('admin.user1'))
            ->post(route('admin.users.table-settings.save'), [
                'page_length' => 7,
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['page_length']);
    }

    public function test_guest_cannot_save_page_length(): void
    {
        Auth::logout();

        $response = $this->postJson(route('admin.users.table-settings.save'), [
            'page_length' => 20,
        ]);

        $this->assertContains($response->getStatusCode(), [302, 401, 403, 419]);
        $this->assertSame(
            0,
            UserTableSetting::where('table_key', 'users_index')->count()
        );
    }

    public function test_user_without_permission_cannot_save_page_length(): void
    {
        $denied = $this->createUserWithoutPermission('users.view', $this->partner);
        $this->actingAs($denied);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->postJson(route('admin.users.table-settings.save'), [
            'page_length' => 20,
        ])->assertForbidden();
    }

    public function test_ajax_save_empty_payload_still_requires_columns(): void
    {
        $this->postJson(route('admin.users.table-settings.save'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['columns']);
    }

    public function test_ajax_save_can_persist_columns_and_page_length_together(): void
    {
        $this->postJson(route('admin.users.table-settings.save'), [
            'columns'     => ['phone' => false, 'name' => true],
            'page_length' => 20,
        ])->assertOk();

        $setting = UserTableSetting::where('user_id', $this->user->id)
            ->where('table_key', 'users_index')
            ->firstOrFail();

        $this->assertSame(20, $setting->page_length);
        $this->assertSame([
            'phone' => false,
            'name'  => true,
        ], $setting->columns);
    }

    public function test_after_changing_show_by_reopening_page_keeps_saved_length(): void
    {
        $this->postJson(route('admin.users.table-settings.save'), [
            'page_length' => 50,
        ])->assertOk();

        $html = $this->get(route('admin.user1'))
            ->assertOk()
            ->assertViewHas('usersPageLength', 50)
            ->getContent();

        $chunk = $this->usersTableCreateChunk($html);
        $this->assertMatchesRegularExpression('/pageLength:\s*50\b/', $chunk);
        $this->assertDoesNotMatchRegularExpression('/pageLength:\s*10\b/', $chunk);
        $this->assertStringContainsString('persistPageLength: true', $chunk);
        $this->assertSame(1, substr_count($html, 'persistPageLength: true'));
    }

    public function test_hiding_columns_then_reopening_page_does_not_reset_show_by(): void
    {
        $this->postJson(route('admin.users.table-settings.save'), [
            'page_length' => 100,
        ])->assertOk();

        $this->postJson(route('admin.users.table-settings.save'), [
            'columns' => [
                'phone' => false,
                'name'  => true,
            ],
        ])->assertOk();

        $html = $this->get(route('admin.user1'))
            ->assertOk()
            ->assertViewHas('usersPageLength', 100)
            ->getContent();

        $this->assertMatchesRegularExpression('/pageLength:\s*100\b/', $this->usersTableCreateChunk($html));

        $payload = $this->getJson(route('admin.users.table-settings.get'))
            ->assertOk()
            ->assertJsonPath('phone', false)
            ->json();
        $this->assertArrayNotHasKey('page_length', $payload);
    }

    public function test_changing_show_by_does_not_persist_page_number(): void
    {
        $this->postJson(route('admin.users.table-settings.save'), [
            'page_length' => 20,
            'start'       => 40,
            'draw'        => 3,
        ])->assertOk();

        $setting = UserTableSetting::where('user_id', $this->user->id)
            ->where('table_key', 'users_index')
            ->firstOrFail();

        $this->assertSame(20, $setting->page_length);
        $this->assertArrayNotHasKey('start', $setting->getAttributes());

        $html = $this->get(route('admin.user1'))->assertOk()->getContent();
        $chunk = $this->usersTableCreateChunk($html);
        $this->assertStringNotContainsString('start:', $chunk);
        $this->assertMatchesRegularExpression('/pageLength:\s*20\b/', $chunk);
    }

    public function test_empty_columns_together_with_page_length_does_not_wipe_hidden_columns(): void
    {
        UserTableSetting::updateOrCreate(
            ['user_id' => $this->user->id, 'table_key' => 'users_index'],
            [
                'columns'     => ['phone' => false, 'name' => true],
                'page_length' => 10,
            ]
        );

        $this->postJson(route('admin.users.table-settings.save'), [
            'columns'     => [],
            'page_length' => 50,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['columns']);

        $setting = UserTableSetting::where('user_id', $this->user->id)
            ->where('table_key', 'users_index')
            ->firstOrFail();

        $this->assertSame(10, $setting->page_length);
        $this->assertSame([
            'phone' => false,
            'name'  => true,
        ], $setting->columns);
    }

    public function test_zero_negative_and_non_numeric_page_length_return_422_with_field_error(): void
    {
        foreach ([0, -1, 25, 'abc', 10.5] as $invalid) {
            $this->postJson(route('admin.users.table-settings.save'), [
                'page_length' => $invalid,
            ])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['page_length']);
        }

        $this->assertSame(
            0,
            UserTableSetting::where('user_id', $this->user->id)
                ->where('table_key', 'users_index')
                ->count()
        );
    }

    public function test_foreign_partner_page_length_does_not_leak_into_current_admin_page(): void
    {
        UserTableSetting::updateOrCreate(
            ['user_id' => $this->foreignUser->id, 'table_key' => 'users_index'],
            ['page_length' => 100]
        );

        $html = $this->get(route('admin.user1'))
            ->assertOk()
            ->assertViewHas('usersPageLength', 10)
            ->getContent();

        $this->assertMatchesRegularExpression('/pageLength:\s*10\b/', $this->usersTableCreateChunk($html));
    }

    public function test_guest_is_denied_on_index_and_page_length_endpoints(): void
    {
        Auth::logout();

        $index = $this->get(route('admin.user1'));
        $this->assertContains($index->getStatusCode(), [302, 401, 403]);
        $this->assertNotSame(500, $index->getStatusCode());
        $this->assertNotSame(200, $index->getStatusCode());

        $get = $this->getJson(route('admin.users.table-settings.get'));
        $this->assertContains($get->getStatusCode(), [302, 401, 403]);
        $this->assertNotSame(500, $get->getStatusCode());

        $post = $this->from(route('admin.user1'))
            ->post(route('admin.users.table-settings.save'), [
                'page_length' => 20,
            ]);
        $this->assertContains($post->getStatusCode(), [302, 401, 403, 419]);
        $this->assertNotSame(500, $post->getStatusCode());
        $this->assertNotSame(200, $post->getStatusCode());
        $this->assertSame(
            0,
            UserTableSetting::where('table_key', 'users_index')->count()
        );
    }

    public function test_user_without_permission_gets_403_on_index_and_page_length_endpoints(): void
    {
        $denied = $this->createUserWithoutPermission('users.view', $this->partner);
        $this->actingAs($denied);
        $this->withSession(['current_partner' => $this->partner->id, '2fa:passed' => true]);

        $this->get(route('admin.user1'))->assertForbidden();
        $this->getJson(route('admin.users.table-settings.get'))->assertForbidden();
        $this->postJson(route('admin.users.table-settings.save'), [
            'page_length' => 20,
        ])->assertForbidden();
    }

    private function usersTableCreateChunk(string $html): string
    {
        $pos = strpos($html, "KidsCrmDataTable.create('#users-table'");
        $this->assertNotFalse($pos, 'KidsCrmDataTable.create(#users-table) не найден');

        return substr($html, $pos, 1800);
    }
}
