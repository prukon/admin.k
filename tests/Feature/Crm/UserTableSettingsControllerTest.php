<?php

namespace Tests\Feature\Crm;

use App\Models\User;
use App\Models\UserTableSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class UserTableSettingsControllerTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // В этом тестовом классе не хотим упираться в настоящую систему прав,
        // поэтому users-view разрешаем любому АВТОРИЗОВАННОМУ пользователю.
        Gate::before(function ($user, string $ability) {
            if ($ability === 'users-view' && $user) {
                return true;
            }
        });
    }

    /**
     * Хелпер: создаём пользователя текущего партнёра и логиним.
     */
    protected function actingAsPartnerUser(): User
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
        ]);

        $this->actingAs($user);

        return $user;
    }

    // ---------------------------------------------------------------------
    // GET /admin/users/columns-settings
    // ---------------------------------------------------------------------

    /** [P1] Возврат пустого массива, если настроек нет у текущего пользователя */
    public function test_get_columns_returns_empty_array_when_no_settings(): void
    {
        $this->actingAsPartnerUser();

        $response = $this->getJson(route('admin.users.table-settings.get'));

        $response
            ->assertStatus(200)
            ->assertExactJson([]);
    }

    /** [P1] Возврат настроек колонок для текущего пользователя */
    public function test_get_columns_returns_user_settings(): void
    {
        $user = $this->actingAsPartnerUser();

        $setting = new UserTableSetting();
        $setting->user_id   = $user->id;
        $setting->table_key = 'users_index';
        $setting->columns   = [
            'avatar' => true,
            'name'   => false,
        ];
        $setting->save();

        $response = $this->getJson(route('admin.users.table-settings.get'));

        $response
            ->assertStatus(200)
            ->assertExactJson([
                'avatar' => true,
                'name'   => false,
            ]);
    }

    /** [P1] Изоляция по пользователям: не течёт в другой аккаунт */
    public function test_get_columns_is_isolated_per_user(): void
    {
        // user A с настройками
        $userA = User::factory()->create([
            'partner_id' => $this->partner->id,
        ]);

        $settingA = new UserTableSetting();
        $settingA->user_id   = $userA->id;
        $settingA->table_key = 'users_index';
        $settingA->columns   = [
            'avatar' => true,
        ];
        $settingA->save();

        // user B — текущий авторизованный пользователь
        $this->actingAsPartnerUser();

        $response = $this->getJson(route('admin.users.table-settings.get'));

        $response
            ->assertStatus(200)
            ->assertExactJson([]); // настройки userA не подтянулись
    }

    /** [P2] Корректное поведение при «битых» данных: columns не массив */
    public function test_get_columns_returns_empty_array_when_columns_not_array(): void
    {
        $user = $this->actingAsPartnerUser();

        // Кладём в JSON-поле валидный JSON-стринг (строка внутри JSON),
        // чтобы пройти JSON-констрейнт, но при этом контроллер получит НЕ массив.
        DB::table((new UserTableSetting())->getTable())->insert([
            'user_id'   => $user->id,
            'table_key' => 'users_index',
            'columns'   => json_encode('oops-not-array'),
            'created_at'=> now(),
            'updated_at'=> now(),
        ]);

        $response = $this->getJson(route('admin.users.table-settings.get'));

        $response
            ->assertStatus(200)
            ->assertExactJson([]); // контроллер должен вернуть []
    }

    // ---------------------------------------------------------------------
    // POST /admin/users/columns-settings
    // ---------------------------------------------------------------------

    /** [P1] Успешное создание настроек с нормализацией типов в boolean */
    public function test_save_columns_creates_settings_with_boolean_normalization(): void
    {
        $user = $this->actingAsPartnerUser();

        $payload = [
            'columns' => [
                'avatar' => true,
                'name'   => '1',
                'phone'  => 0,
                'email'  => 'false',
                'team'   => 'true',
            ],
        ];

        $response = $this->postJson(route('admin.users.table-settings.save'), $payload);

        $response
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        /** @var \App\Models\UserTableSetting|null $setting */
        $setting = UserTableSetting::where('user_id', $user->id)
            ->where('table_key', 'users_index')
            ->first();

        $this->assertNotNull($setting, 'Настройка должна быть создана');

        $this->assertSame([
            'avatar' => true,
            'name'   => true,
            'phone'  => false,
            'email'  => false,
            'team'   => true,
        ], $setting->columns);
    }

    /** [P1] Обновление настроек вместо создания дублей (updateOrCreate) */
    public function test_save_columns_updates_existing_settings_without_duplicates(): void
    {
        $user = $this->actingAsPartnerUser();

        $existing = new UserTableSetting();
        $existing->user_id   = $user->id;
        $existing->table_key = 'users_index';
        $existing->columns   = [
            'avatar' => false,
        ];
        $existing->save();

        $payload = [
            'columns' => [
                'avatar' => true,
                'name'   => true,
            ],
        ];

        $response = $this->postJson(route('admin.users.table-settings.save'), $payload);

        $response
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertSame(
            1,
            UserTableSetting::where('user_id', $user->id)
                ->where('table_key', 'users_index')
                ->count(),
            'Должна остаться одна запись для user_id + table_key'
        );

        $updated = UserTableSetting::where('user_id', $user->id)
            ->where('table_key', 'users_index')
            ->first();

        $this->assertSame([
            'avatar' => true,
            'name'   => true,
        ], $updated->columns);
    }

    /** [P1] Валидация: поле columns обязательно */
    public function test_save_columns_requires_columns_field(): void
    {
        $this->actingAsPartnerUser();

        $response = $this->postJson(route('admin.users.table-settings.save'), []);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['columns']);

        $this->assertDatabaseCount(
            (new UserTableSetting())->getTable(),
            0
        );
    }

    /** [P1] Валидация: columns должен быть массивом */
    public function test_save_columns_requires_columns_to_be_array(): void
    {
        $this->actingAsPartnerUser();

        $response = $this->postJson(route('admin.users.table-settings.save'), [
            'columns' => 'not-an-array',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['columns']);

        $this->assertDatabaseCount(
            (new UserTableSetting())->getTable(),
            0
        );
    }

    /** [P2] Нормализация «мусорных» значений в false/true по реальной логике filter_var */
    public function test_save_columns_normalizes_garbage_values_to_false(): void
    {
        $user = $this->actingAsPartnerUser();

        $payload = [
            'columns' => [
                'avatar' => 'yes',   // -> true
                'name'   => 'no',    // -> false
                'phone'  => 'abc',   // -> null -> false
                'email'  => '',      // -> null -> false
            ],
        ];

        $response = $this->postJson(route('admin.users.table-settings.save'), $payload);

        $response
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $setting = UserTableSetting::where('user_id', $user->id)
            ->where('table_key', 'users_index')
            ->first();

        $this->assertNotNull($setting);

        $this->assertSame([
            'avatar' => true,   // yes -> true
            'name'   => false,  // no -> false
            'phone'  => false,  // abc -> null -> false
            'email'  => false,  // '' -> null -> false
        ], $setting->columns);
    }

    /** [P2] Привязка настроек именно к текущему пользователю */
    public function test_save_columns_is_bound_to_current_user_only(): void
    {
        // user X с уже существующими настройками
        $userX = User::factory()->create([
            'partner_id' => $this->partner->id,
        ]);

        $settingX = new UserTableSetting();
        $settingX->user_id   = $userX->id;
        $settingX->table_key = 'users_index';
        $settingX->columns   = [
            'avatar' => true,
        ];
        $settingX->save();

        // user Y — тот, под кем сохраняем
        $userY = $this->actingAsPartnerUser();

        $payload = [
            'columns' => [
                'avatar' => false,
                'name'   => true,
            ],
        ];

        $response = $this->postJson(route('admin.users.table-settings.save'), $payload);

        $response
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        // Настройки userX не изменились
        $reloadedX = UserTableSetting::where('user_id', $userX->id)
            ->where('table_key', 'users_index')
            ->first();

        $this->assertSame([
            'avatar' => true,
        ], $reloadedX->columns);

        // Для userY создана/обновлена своя запись
        $settingY = UserTableSetting::where('user_id', $userY->id)
            ->where('table_key', 'users_index')
            ->first();

        $this->assertNotNull($settingY);

        $this->assertSame([
            'avatar' => false,
            'name'   => true,
        ], $settingY->columns);
    }
}