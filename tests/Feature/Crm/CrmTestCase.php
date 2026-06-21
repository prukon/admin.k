<?php

namespace Tests\Feature\Crm;

use App\Models\Role;
use App\Models\User;
use App\Models\Partner;
use App\Models\SchoolLeadStatus;
use App\Models\Team;
use App\Services\TeamUserSyncService;
use Database\Seeders\PermissionGroupsSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolesSeeder;
use Database\Seeders\WeekdaysSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

abstract class CrmTestCase extends TestCase
{
    use RefreshDatabase;

    /**
     * Разрешённая БД для тестов (жёсткий предохранитель).
     * Поставь сюда ТОЛЬКО тестовую БД, которую ты готов без сожаления снести migrate:fresh.
     */
    private const ALLOWED_TEST_DATABASE = 'prukon_test.kidcrm.testing';

    protected Partner $partner;
    protected User $user;

    /**
     * Второй партнёр/юзер — для тестов изоляции (anti-leak).
     */
    protected Partner $foreignPartner;
    protected User $foreignUser;

    protected function setUp(): void
    {
        parent::setUp();

        /**
         * SAFETY GUARD
         * Запрещаем запуск тестов, если окружение не testing
         * или если подключение смотрит не в строго разрешённую тестовую БД.
         */
        $this->assertSafeTestingEnvironment();

        // В тестах не должны зависеть от writable storage/ (file cache).
        // Переключаем cache на in-memory store.
        config(['cache.default' => 'array']);

        // В некоторых окружениях storage/logs может быть недоступен для записи.
        // Переключаем логирование на errorlog, чтобы тесты не падали из-за прав на файл laravel.log.
        config(['logging.default' => 'errorlog']);
        // TinkoffApiClient пишет в канал tinkoff (файл под storage/logs/tbank/) — в CI без прав на каталог тесты падают.
        config(['logging.channels.tinkoff.driver' => 'errorlog']);

        // В некоторых окружениях storage/ может быть недоступен для записи тестовым процессом.
        // Переносим compiled Blade views в системный tmp (writable).
        // Важно: используем уникальную папку на каждый прогон.
        $compiled = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'kidscrm_compiled_views_'
            . (string) Str::uuid();

        if (!is_dir($compiled)) {
            @mkdir($compiled, 0777, true);
        }
        @chmod($compiled, 0777);
        config(['view.compiled' => $compiled]);

        // 1) Референсы
        $this->seed(WeekdaysSeeder::class);
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionGroupsSeeder::class);
        $this->seed(PermissionSeeder::class);

        // Создаём партнёра
        $this->partner = Partner::factory()->create();

        // Создаем роль
        $userRoleId = Role::where('name', 'user')->value('id');

        // Создаем юзера
        $this->user = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $userRoleId,
        ]);

        // Создаём чужого партнёра
        $this->foreignPartner = Partner::factory()->create();

        // Создаём чужого юзера
        $this->foreignUser = User::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'role_id' => $userRoleId,
        ]);

        // 4) Базовые права admin/user — через Partner::created (assignBasePermissionsForPartner)

        // 5) Авторизация
        $this->actingAs($this->user);

        // 6) current_partner в session
        $this->withSession(['current_partner' => $this->partner->id]);
    }

    /**
     * Предохранитель от случайного запуска тестов в production/staging
     * и/или на боевой БД.
     */
    protected function assertSafeTestingEnvironment(): void
    {
        // 1) Окружение обязано быть testing
        if (!app()->environment('testing')) {
            throw new RuntimeException(
                "SAFETY GUARD: Тесты можно запускать только в окружении 'testing'. " .
                "Сейчас: '" . app()->environment() . "'. " .
                "Запускай: php artisan test --env=testing"
            );
        }

        // 2) Имя базы обязано быть строго тем, что мы разрешили
        $dbName = DB::connection()->getDatabaseName();

        if (!is_string($dbName) || $dbName === '') {
            throw new RuntimeException(
                "SAFETY GUARD: Не удалось определить имя базы данных для текущего подключения."
            );
        }

        if ($dbName !== self::ALLOWED_TEST_DATABASE) {
            throw new RuntimeException(
                "SAFETY GUARD: Подключение указывает на НЕразрешённую БД: '{$dbName}'. " .
                "Разрешена ТОЛЬКО: '" . self::ALLOWED_TEST_DATABASE . "'. " .
                "Проверь, что ты запускаешь тесты так: php artisan test --env=testing " .
                "и что в .env.testing указана правильная DB_DATABASE."
            );
        }
    }

    // Авторизация admin
    protected function asAdmin(): self
    {
        $adminRoleId = Role::where('name', 'admin')->value('id');

        $this->user->role_id = $adminRoleId;
        $this->user->save();

        $this->actingAs($this->user);

        return $this;
    }

    // Авторизация superadmin
    protected function asSuperadmin(): self
    {
        $adminRoleId = Role::where('name', 'superadmin')->value('id');

        $this->user->role_id = $adminRoleId;
        $this->user->save();

        $this->actingAs($this->user);

        return $this;
    }

    /**
     * Быстро переключиться на “чужого” пользователя.
     */
    protected function asForeignUser(): self
    {
        $this->actingAs($this->foreignUser);
        $this->withSession(['current_partner' => $this->foreignPartner->id]);

        return $this;
    }

    protected function roleId(string $name): int
    {
        return (int) Role::query()->where('name', $name)->firstOrFail()->id;
    }

    protected function permissionId(string $name): int
    {
        $id = DB::table('permissions')->where('name', $name)->value('id');
        $this->assertNotNull($id, "Permission '{$name}' не найден в таблице permissions");
        return (int) $id;
    }

    protected function createUserWithRole(string $roleName, ?Partner $partner = null, array $attributes = []): User
    {
        $partner ??= $this->partner;

        return User::factory()->create(array_merge([
            'partner_id' => $partner->id,
            'role_id'    => $this->roleId($roleName),
        ], $attributes));
    }

    /**
     * Создаёт пользователя текущего партнёра с ролью, у которой НЕТ указанного permission (по pivot permission_role).
     *
     * Важно: учитывает partner_id в permission_role.
     */
    protected function createUserWithoutPermission(string $permissionName, ?Partner $partner = null, array $attributes = []): User
    {
        $partner ??= $this->partner;

        $permId = $this->permissionId($permissionName);

        $role = Role::query()
            ->where('name', '!=', 'superadmin')
            ->whereNotExists(function ($q) use ($permId, $partner) {
                $q->select(DB::raw(1))
                    ->from('permission_role')
                    ->whereColumn('permission_role.role_id', 'roles.id')
                    ->where('permission_role.permission_id', $permId)
                    ->where('permission_role.partner_id', $partner->id);
            })
            ->first();

        // Если подходящей роли нет — создаём тестовую роль без прав
        if (!$role) {
            $now = now();
            $roleId = DB::table('roles')->insertGetId([
                'name'       => 'test_no_' . str_replace('.', '_', $permissionName) . '_' . Str::lower(Str::random(6)),
                'label'      => 'Test No ' . $permissionName,
                'is_sistem'  => 0,
                'order_by'   => 0,
                // is_visible появилось миграцией 2025_04_10..., поэтому на старых схемах игнорируется
                'is_visible' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $role = Role::query()->findOrFail($roleId);
        }

        return User::factory()->create(array_merge([
            'partner_id' => $partner->id,
            'role_id'    => $role->id,
        ], $attributes));
    }

    protected function schoolLeadSystemStatusId(): int
    {
        return SchoolLeadStatus::systemNewId();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createPartnerSchoolLeadStatus(array $attributes = [], ?int $partnerId = null): SchoolLeadStatus
    {
        return SchoolLeadStatus::query()->create([
            'partner_id'           => $partnerId ?? (int) $this->partner->id,
            'name'                 => $attributes['name'] ?? 'Кастомный',
            'color'                => $attributes['color'] ?? '#0d6efd',
            'sort_order'           => (int) ($attributes['sort_order'] ?? 10),
            'is_default_in_filter' => (bool) ($attributes['is_default_in_filter'] ?? false),
            'is_system'            => false,
        ]);
    }

    protected ?int $cachedSchoolLeadProcessingStatusId = null;

    protected ?int $cachedSchoolLeadSaleStatusId = null;

    protected ?int $cachedSchoolLeadSpamStatusId = null;

    protected function schoolLeadProcessingStatusId(): int
    {
        if ($this->cachedSchoolLeadProcessingStatusId === null) {
            $this->cachedSchoolLeadProcessingStatusId = (int) $this->createPartnerSchoolLeadStatus([
                'name'                 => 'Обработка',
                'is_default_in_filter' => true,
            ])->id;
        }

        return $this->cachedSchoolLeadProcessingStatusId;
    }

    protected function schoolLeadSaleStatusId(): int
    {
        if ($this->cachedSchoolLeadSaleStatusId === null) {
            $this->cachedSchoolLeadSaleStatusId = (int) $this->createPartnerSchoolLeadStatus([
                'name' => 'Продажа',
            ])->id;
        }

        return $this->cachedSchoolLeadSaleStatusId;
    }

    protected function schoolLeadSpamStatusId(): int
    {
        if ($this->cachedSchoolLeadSpamStatusId === null) {
            $this->cachedSchoolLeadSpamStatusId = (int) $this->createPartnerSchoolLeadStatus([
                'name' => 'Спам',
            ])->id;
        }

        return $this->cachedSchoolLeadSpamStatusId;
    }

    /**
     * Маршруты CRUD статусов заявок для smoke-тестов доступа (все должны отдавать 200).
     *
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}>
     */
    protected function schoolLeadStatusManagementRoutesPayload(): array
    {
        $updateStatus = $this->createPartnerSchoolLeadStatus(['name' => 'Update smoke']);
        $deleteStatus = $this->createPartnerSchoolLeadStatus(['name' => 'Delete smoke']);

        return [
            [
                'method' => 'GET',
                'url'    => route('admin.school-leads.statuses.index'),
            ],
            [
                'method' => 'POST',
                'url'    => route('admin.school-leads.statuses.store'),
                'data'   => [
                    'name'                 => 'Создан access',
                    'color'                => '#198754',
                    'is_default_in_filter' => false,
                ],
            ],
            [
                'method' => 'PUT',
                'url'    => route('admin.school-leads.statuses.update', ['schoolLeadStatus' => $updateStatus->id]),
                'data'   => [
                    'name'                 => 'Обновлён access',
                    'color'                => '#ffc107',
                    'sort_order'           => 30,
                    'is_default_in_filter' => true,
                ],
            ],
            [
                'method' => 'DELETE',
                'url'    => route('admin.school-leads.statuses.destroy', ['schoolLeadStatus' => $deleteStatus->id]),
            ],
        ];
    }

    /**
     * Вставка строки users_prices с обязательным team_id (схема после pivot).
     *
     * @param  Team|int|null  $team  Явная группа; иначе team_id пользователя или новая группа партнёра.
     */
    protected function insertUserPrice(User $user, array $fields, Team|int|null $team = null): void
    {
        if ($team instanceof Team) {
            $teamId = (int) $team->id;
        } elseif (is_int($team)) {
            $teamId = $team;
        } else {
            $teamId = (int) ($user->team_id ?: 0);
        }

        if ($teamId <= 0) {
            $teamId = (int) Team::factory()->create(['partner_id' => $user->partner_id])->id;
        }

        app(TeamUserSyncService::class)->attachTeamForStudent($user, $teamId);

        DB::table('users_prices')->insert(array_merge([
            'user_id'    => $user->id,
            'team_id'    => $teamId,
            'created_at' => now(),
            'updated_at' => now(),
        ], $fields));
    }
}