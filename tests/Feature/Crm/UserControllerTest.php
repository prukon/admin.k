<?php

namespace Tests\Feature\Crm;

use App\Models\User;
use App\Models\Partner;
use App\Models\Role;
use App\Models\Team;
use App\Models\UserField;
use App\Models\UserFieldValue;
use App\Models\MyLog;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class UserControllerTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Контекст текущего партнёра
        session(['current_partner' => $this->partner->id]);

        // Разрешаем ВСЕ права, кроме users-phone-update
        Gate::before(function ($user, string $ability = null) {
            if ($ability === 'users-phone-update') {
                // для этого права отдельно задаём поведение ниже
                return null;
            }

            // все остальные права считаем разрешёнными
            return true;
        });

        // По умолчанию телефон менять нельзя (в отдельных тестах переопределяем)
        Gate::define('users-phone-update', fn() => false);
    }


    /**
     * [P2] Отображение страницы пользователей для обычного админа партнёра
     */
    public function test_index_shows_users_page_for_partner_admin(): void
    {
        // Роль обычного админа (НЕ системная)
        $adminRole = new Role();
        $adminRole->name = 'test-partner-admin';
        $adminRole->label = 'Test Partner Admin';
        $adminRole->is_sistem = 0;
        $adminRole->is_visible = 1;
        $adminRole->save();

        $this->user->role()->associate($adminRole);
        $this->user->save();

        // Системная видимая роль — пробуем взять из сидера, если нет — создаём
        $systemVisibleRole = Role::where('is_sistem', 1)
            ->where('is_visible', 1)
            ->first();

        if (!$systemVisibleRole) {
            $systemVisibleRole = new Role();
            $systemVisibleRole->name = 'test-system-visible';
            $systemVisibleRole->label = 'Test System Visible';
            $systemVisibleRole->is_sistem = 1;
            $systemVisibleRole->is_visible = 1;
            $systemVisibleRole->save();
        }

        // Системная скрытая роль — пробуем взять из сидера, если нет — создаём
        $systemHiddenRole = Role::where('is_sistem', 1)
            ->where('is_visible', 0)
            ->first();

        if (!$systemHiddenRole) {
            $systemHiddenRole = new Role();
            $systemHiddenRole->name = 'test-system-hidden';
            $systemHiddenRole->label = 'Test System Hidden';
            $systemHiddenRole->is_sistem = 1;
            $systemHiddenRole->is_visible = 0;
            $systemHiddenRole->save();
        }

        // партнёрские роли
        $partnerRoleA = new Role();
        $partnerRoleA->name = 'test-partner-role-a';
        $partnerRoleA->label = 'Test Partner Role A';
        $partnerRoleA->is_sistem = 0;
        $partnerRoleA->is_visible = 1;
        $partnerRoleA->save();

        $partnerRoleB = new Role();
        $partnerRoleB->name = 'test-partner-role-b';
        $partnerRoleB->label = 'Test Partner Role B';
        $partnerRoleB->is_sistem = 0;
        $partnerRoleB->is_visible = 1;
        $partnerRoleB->save();

        // привязки ролей к партнёрам через pivot partner_role
        DB::table('partner_role')->insert([
            'partner_id' => $this->partner->id,
            'role_id' => $partnerRoleA->id,
        ]);

        $otherPartner = Partner::factory()->create();
        DB::table('partner_role')->insert([
            'partner_id' => $otherPartner->id,
            'role_id' => $partnerRoleB->id,
        ]);

        // произвольные поля
        $fieldA = UserField::factory()->create([
            'partner_id' => $this->partner->id,
        ]);
        $fieldB = UserField::factory()->create([
            'partner_id' => $otherPartner->id,
        ]);

        // команды
        $teamA1 = Team::factory()->create([
            'partner_id' => $this->partner->id,
        ]);
        $teamA2 = Team::factory()->create([
            'partner_id' => $this->partner->id,
        ]);
        $teamB = Team::factory()->create([
            'partner_id' => $otherPartner->id,
        ]);

        $response = $this->get('/admin/users');

        $response->assertStatus(200);
        $response->assertViewIs('admin.user');

        $response->assertViewHas('currentUser', function (User $currentUser) use ($adminRole) {
            return $currentUser->id === $this->user->id
                && $currentUser->role_id === $adminRole->id;
        });

        $response->assertViewHas('fields', function ($fields) use ($fieldA, $fieldB) {
            $ids = $fields->pluck('id')->all();
            return in_array($fieldA->id, $ids, true)
                && !in_array($fieldB->id, $ids, true);
        });

        $response->assertViewHas('allTeams', function ($teams) use ($teamA1, $teamA2, $teamB) {
            $ids = $teams->pluck('id')->all();
            return in_array($teamA1->id, $ids, true)
                && in_array($teamA2->id, $ids, true)
                && !in_array($teamB->id, $ids, true);
        });

        // Обычный админ:
        // - видит системные роли только с is_visible=1
        // - видит партнёрские роли только своего партнёра
        $response->assertViewHas('roles', function ($roles) use ($systemVisibleRole, $systemHiddenRole, $partnerRoleA, $partnerRoleB) {
            $ids = $roles->pluck('id')->all();

            // системная видимая есть
            $okVisible = in_array($systemVisibleRole->id, $ids, true);

            // системная скрытая отсутствует (если вообще существует)
            $okHidden = $systemHiddenRole
                ? !in_array($systemHiddenRole->id, $ids, true)
                : true;

            // своя партнёрская есть, чужая — нет
            $okPartner = in_array($partnerRoleA->id, $ids, true)
                && !in_array($partnerRoleB->id, $ids, true);

            return $okVisible && $okHidden && $okPartner;
        });
    }

    /**
     * [P2] Отображение страницы пользователей для супер-админа
     */
    public function test_index_shows_users_page_for_superadmin(): void
    {
        // 1) Берём реальную роль superadmin из БД
        $superRole = Role::where('name', 'superadmin')->firstOrFail();

        // 2) Назначаем эту роль текущему пользователю
        $this->user->role()->associate($superRole);
        $this->user->save();

        // 3) Создаём скрытую партнёрскую роль и вешаем её на текущего партнёра
        $hiddenPartnerRole = new Role();
        $hiddenPartnerRole->name = 'test-partner-role-hidden-for-super';
        $hiddenPartnerRole->label = 'Hidden Partner Role For Superadmin';
        $hiddenPartnerRole->is_sistem = 0;
        $hiddenPartnerRole->is_visible = 0; // СКРЫТАЯ
        $hiddenPartnerRole->save();

        DB::table('partner_role')->insert([
            'partner_id' => $this->partner->id,
            'role_id' => $hiddenPartnerRole->id,
        ]);

        // 4) Делаем запрос к странице пользователей
        $response = $this->get('/admin/users');

        // 5) Проверяем, что доступ есть
        $response->assertStatus(200);
        $response->assertViewIs('admin.user');

        // 6) Проверяем, что:
        //    - в roles есть супер-админ (скрытая системная роль)
        //    - в roles есть скрытая партнёрская роль
        $response->assertViewHas('roles', function ($roles) use ($superRole, $hiddenPartnerRole) {
            $ids = $roles->pluck('id')->all();

            // superadmin должен быть виден, несмотря на is_visible=0
            if (!in_array($superRole->id, $ids, true)) {
                return false;
            }

            // скрытая партнёрская роль тоже должна быть видна супер-админу
            if (!in_array($hiddenPartnerRole->id, $ids, true)) {
                return false;
            }

            return true;
        });
    }

    /**
     * [P1] Базовая структура DataTables-ответа и учёт партнёра
     */
    public function test_data_returns_basic_datatables_structure_for_current_partner(): void
    {
        $otherPartner = Partner::factory()->create();

        // пользователи текущего партнёра
        $usersA = User::factory()->count(3)->create([
            'partner_id' => $this->partner->id,
        ]);

        // пользователи другого партнёра
        $usersB = User::factory()->count(2)->create([
            'partner_id' => $otherPartner->id,
        ]);

        $response = $this->getJson('/admin/users/data?draw=1&start=0&length=10');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'draw',
                'recordsTotal',
                'recordsFiltered',
                'data',
            ]);

        $json = $response->json();

        $this->assertEquals(1, $json['draw']);

// реальное количество пользователей текущего партнёра
        $partnerUserCount = \App\Models\User::where('partner_id', $this->partner->id)->count();

        $this->assertEquals($partnerUserCount, $json['recordsTotal']);
        $this->assertEquals($partnerUserCount, $json['recordsFiltered']);

        $returnedIds = collect($json['data'])->pluck('id')->all();
        foreach ($usersA as $userA) {
            $this->assertContains($userA->id, $returnedIds);
        }
        foreach ($usersB as $userB) {
            $this->assertNotContains($userB->id, $returnedIds);
        }
    }

    /**
     * [P1] Фильтрация по id
     */
    public function test_data_filters_by_id(): void
    {
        $user1 = User::factory()->create(['partner_id' => $this->partner->id]);
        $user2 = User::factory()->create(['partner_id' => $this->partner->id]);

        $response = $this->getJson('/admin/users/data?id=' . $user1->id . '&draw=2');

        $response->assertStatus(200);
        $json = $response->json();

        $this->assertEquals(2, $json['draw']);
        $this->assertEquals(1, $json['recordsFiltered']);
        $this->assertCount(1, $json['data']);
        $this->assertEquals($user1->id, $json['data'][0]['id']);
    }

    /**
     * [P1] Фильтрация по name (ФИО / email / телефон / дата рождения)
     */
    public function test_data_filters_by_name_email_phone_birthday(): void
    {
        $target = User::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Иван',
            'lastname' => 'Петров',
            'email' => 'ivan.petrov@example.com',
            'phone' => '+70001112233',
            'birthday' => '2010-05-15',
        ]);

        $other = User::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Другой',
            'lastname' => 'Юзер',
            'email' => 'other@example.com',
            'phone' => '+79999999999',
            'birthday' => '2000-01-01',
        ]);

        // по фамилии
        $response = $this->getJson('/admin/users/data?name=Петр');
        $json = $response->json();
        $this->assertEquals(1, $json['recordsFiltered']);
        $this->assertEquals($target->id, $json['data'][0]['id']);

        // по email
        $response = $this->getJson('/admin/users/data?name=ivan.petrov');
        $json = $response->json();
        $this->assertEquals(1, $json['recordsFiltered']);
        $this->assertEquals($target->id, $json['data'][0]['id']);

        // по телефону
        $response = $this->getJson('/admin/users/data?name=1112');
        $json = $response->json();
        $this->assertEquals(1, $json['recordsFiltered']);
        $this->assertEquals($target->id, $json['data'][0]['id']);

        // по дате рождения
        $response = $this->getJson('/admin/users/data?name=2010-05-15');
        $json = $response->json();
        $this->assertEquals(1, $json['recordsFiltered']);
        $this->assertEquals($target->id, $json['data'][0]['id']);
    }

    /**
     * [P1] Фильтрация по team_id: конкретная команда, none и пусто
     */
    public function test_data_filters_by_team_id_and_none(): void
    {
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
        ]);

        $userInTeam = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
        ]);

        $userWithoutTeam = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => null,
        ]);

        // конкретная команда
        $json = $this->getJson('/admin/users/data?team_id=' . $team->id)->json();
        $ids = collect($json['data'])->pluck('id')->all();
        $this->assertContains($userInTeam->id, $ids);
        $this->assertNotContains($userWithoutTeam->id, $ids);

        // none
        $json = $this->getJson('/admin/users/data?team_id=none')->json();
        $ids = collect($json['data'])->pluck('id')->all();
        $this->assertContains($userWithoutTeam->id, $ids);
        $this->assertNotContains($userInTeam->id, $ids);

        // пустое — оба
        $json = $this->getJson('/admin/users/data')->json();
        $ids = collect($json['data'])->pluck('id')->all();
        $this->assertContains($userInTeam->id, $ids);
        $this->assertContains($userWithoutTeam->id, $ids);
    }

    /**
     * [P1] Фильтрация по status (active / inactive)
     */
    public function test_data_filters_by_status_active_inactive(): void
    {
        $activeUser = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);
        $inactiveUser = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 0,
        ]);

        $json = $this->getJson('/admin/users/data?status=active')->json();
        $ids = collect($json['data'])->pluck('id')->all();

        $this->assertContains($activeUser->id, $ids);
        $this->assertNotContains($inactiveUser->id, $ids);

        $json = $this->getJson('/admin/users/data?status=inactive')->json();
        $ids = collect($json['data'])->pluck('id')->all();

        $this->assertContains($inactiveUser->id, $ids);
        $this->assertNotContains($activeUser->id, $ids);
    }

    /**
     * [P2] Сортировка по основным колонкам DataTables
     */
    public function test_data_sorts_by_columns(): void
    {
        // Две команды
        $teamAlpha = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Alpha',
        ]);
        $teamBeta = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Beta',
        ]);

        // Два пользователя с разными данными
        $u1 = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $teamBeta->id,
            'lastname' => 'Бета',
            'name' => 'Сергей',
            'email' => 'b@example.com',
            'phone' => '+70000000001',
            'is_enabled' => 0,
        ]);

        $u2 = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $teamAlpha->id,
            'lastname' => 'Альфа',
            'name' => 'Иван',
            'email' => 'a@example.com',
            'phone' => '+70000000002',
            'is_enabled' => 1,
        ]);

        // 1) Сортировка по имени (col=2) asc — по фамилии, затем имени
        $json = $this->getJson('/admin/users/data?order[0][column]=2&order[0][dir]=asc')->json();
        $ids = collect($json['data'])->pluck('id')->all();

        $posU1 = array_search($u1->id, $ids, true);
        $posU2 = array_search($u2->id, $ids, true);

        $this->assertNotFalse($posU1, 'u1 не найден в результатах сортировки по имени asc');
        $this->assertNotFalse($posU2, 'u2 не найден в результатах сортировки по имени asc');

        // Альфа (u2) должна быть раньше Беты (u1)
        $this->assertTrue(
            $posU2 < $posU1,
            'Ожидали, что Альфа (u2) будет раньше Беты (u1) при сортировке по имени asc'
        );

        // 2) Сортировка по команде (col=3) asc — по teams.title
        $json = $this->getJson('/admin/users/data?order[0][column]=3&order[0][dir]=asc')->json();
        $ids = collect($json['data'])->pluck('id')->all();

        $posU1 = array_search($u1->id, $ids, true);
        $posU2 = array_search($u2->id, $ids, true);

        $this->assertNotFalse($posU1, 'u1 не найден в результатах сортировки по команде asc');
        $this->assertNotFalse($posU2, 'u2 не найден в результатах сортировки по команде asc');

        // Alpha (u2) должна быть раньше Beta (u1)
        $this->assertTrue(
            $posU2 < $posU1,
            'Ожидали, что команда Alpha (u2) будет раньше Beta (u1) при сортировке по команде asc'
        );

        // 3) Сортировка по email (col=5) asc
        $json = $this->getJson('/admin/users/data?order[0][column]=5&order[0][dir]=asc')->json();
        $ids = collect($json['data'])->pluck('id')->all();

        $posU1 = array_search($u1->id, $ids, true);
        $posU2 = array_search($u2->id, $ids, true);

        $this->assertNotFalse($posU1, 'u1 не найден в результатах сортировки по email asc');
        $this->assertNotFalse($posU2, 'u2 не найден в результатах сортировки по email asc');

        // a@example.com (u2) перед b@example.com (u1)
        $this->assertTrue(
            $posU2 < $posU1,
            "Ожидали, что u2 (id={$u2->id}, email={$u2->email}) будет раньше u1 (id={$u1->id}, email={$u1->email}) при сортировке по email asc"
        );

        // 4) Сортировка по статусу (col=7) desc — активные первыми
        $json = $this->getJson('/admin/users/data?order[0][column]=7&order[0][dir]=desc')->json();
        $ids = collect($json['data'])->pluck('id')->all();

        $posU1 = array_search($u1->id, $ids, true); // is_enabled=0
        $posU2 = array_search($u2->id, $ids, true); // is_enabled=1

        $this->assertNotFalse($posU1, 'u1 не найден в результатах сортировки по статусу desc');
        $this->assertNotFalse($posU2, 'u2 не найден в результатах сортировки по статусу desc');

        // u2 (is_enabled=1) должен быть раньше u1 (is_enabled=0)
        $this->assertTrue(
            $posU2 < $posU1,
            "Ожидали, что u2 (id={$u2->id}, is_enabled=1) будет раньше u1 (id={$u1->id}, is_enabled=0) при сортировке по статусу desc"
        );
    }

    /**
     * [P2] Пагинация и корректность recordsFiltered
     */
    public function test_data_paginates_and_sets_records_filtered_correctly(): void
    {
        User::factory()->count(30)->create([
            'partner_id' => $this->partner->id,
        ]);

        // страница 1
        $json = $this->getJson('/admin/users/data?start=0&length=10')->json();
        $this->assertCount(10, $json['data']);

        // страница 2
        $json2 = $this->getJson('/admin/users/data?start=10&length=10')->json();
        $this->assertCount(10, $json2['data']);

        $ids1 = collect($json['data'])->pluck('id')->all();
        $ids2 = collect($json2['data'])->pluck('id')->all();

        $this->assertEmpty(array_intersect($ids1, $ids2));

        // фильтр по имени одного пользователя
        $target = User::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'UNIQUE-NAME',
        ]);

        $jsonFiltered = $this->getJson('/admin/users/data?name=UNIQUE-NAME')->json();
        $this->assertEquals(1, $jsonFiltered['recordsFiltered']);
        $this->assertCount(1, $jsonFiltered['data']);
        $this->assertEquals($target->id, $jsonFiltered['data'][0]['id']);
    }

    /**
     * [P2] Форматирование данных в data (аватар, дата рождения, статус)
     */
    public function test_data_formats_avatar_birthday_and_status_fields(): void
    {
        $withAvatar = User::factory()->create([
            'partner_id' => $this->partner->id,
            'image_crop' => 'avatar1.png',
            'birthday' => '2010-02-03',
            'is_enabled' => 1,
        ]);

        $withoutAvatar = User::factory()->create([
            'partner_id' => $this->partner->id,
            'image_crop' => null,
            'birthday' => null,
            'is_enabled' => 0,
        ]);

        $json = $this->getJson('/admin/users/data?start=0&length=10')->json();

        $items = collect($json['data'])->keyBy('id');

        $this->assertStringContainsString(
            'storage/avatars/' . $withAvatar->image_crop,
            $items[$withAvatar->id]['avatar']
        );
        $this->assertEquals('03.02.2010', $items[$withAvatar->id]['birthday']);
        $this->assertEquals('Активен', $items[$withAvatar->id]['status_label']);
        $this->assertEquals(1, $items[$withAvatar->id]['is_enabled']);

        $this->assertStringContainsString(
            'img/default-avatar.png',
            $items[$withoutAvatar->id]['avatar']
        );
        $this->assertEquals('', $items[$withoutAvatar->id]['birthday']);
        $this->assertEquals('Неактивен', $items[$withoutAvatar->id]['status_label']);
        $this->assertEquals(0, $items[$withoutAvatar->id]['is_enabled']);
    }

    /**
     * [P1] Успешное создание пользователя через AJAX для текущего партнёра
     */
    public function test_store_creates_user_via_ajax_for_current_partner(): void
    {
        // роль "user" (системная, видимая)
        $role = Role::firstOrCreate(
            ['name' => 'user'],
            [
                'label' => 'user',
                'is_sistem' => 1,
                'is_visible' => 1,
                'order_by' => 0,
            ]
        );

        // команда текущего партнёра
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
        ]);

        $payload = [
            'name' => 'Новый',
            'lastname' => 'Пользователь',
            'email' => 'newuser@example.com',
            'phone' => '+79990000001',
            'role_id' => $role->id,
            'team_id' => $team->id,
            'birthday' => '2015-01-01',
            'start_date' => '2024-09-01',
            'is_enabled' => 1,                 // boolean для валидации
            'password' => 'TestPass123!',    // обязателен в StoreRequest
            'password_confirmation' => 'TestPass123!',
        ];

        $response = $this->postJson('/admin/users', $payload, [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'user' => [
                    'id',
                    'name',
                    'birthday',
                    'start_date',
                    'team',
                    'email',
                    'is_enabled',
                ],
            ]);

        $json = $response->json();

        // Берём id созданного пользователя из ответа
        $createdId = $json['user']['id'] ?? null;
        $this->assertNotNull($createdId, 'В ответе не вернулся ID созданного пользователя');

        $created = User::find($createdId);
        $this->assertNotNull($created, "Пользователь с id={$createdId} не найден в БД");

        $this->assertEquals($this->partner->id, $created->partner_id);
        $this->assertEquals($team->id, $created->team_id);
        $this->assertEquals($payload['name'], $created->name);
        $this->assertEquals($payload['email'], $created->email);
        $this->assertEquals(1, $created->is_enabled);

        $this->assertEquals('Пользователь создан успешно', $json['message']);
    }

    /**
     * [P1] Транзакционность и логирование при создании (MyLog)
     */
    public function test_store_is_transactional_and_writes_creation_log(): void
    {
        $role = Role::firstOrCreate(
            ['name' => 'user'],
            [
                'label' => 'user',
                'is_sistem' => 1,
                'is_visible' => 1,
                'order_by' => 0,
            ]
        );

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Alpha',
        ]);

        $payload = [
            'name' => 'Мария',
            'lastname' => 'Иванова',
            'email' => 'maria@example.com',
            'phone' => '+79990000002',
            'role_id' => $role->id,
            'team_id' => $team->id,
            'birthday' => '2014-02-02',
            'start_date' => '2024-09-01',
            'is_enabled' => 1,
            'password' => 'TestPass123!',
            'password_confirmation' => 'TestPass123!',
        ];

        $this->postJson('/admin/users', $payload, [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertStatus(200);

        $user = User::where('email', 'maria@example.com')->firstOrFail();

        $logs = MyLog::where('target_type', User::class)
            ->where('target_id', $user->id)
            ->where('action', 21) // создание учётки
            ->get();

        $this->assertCount(1, $logs);

        $log = $logs->first();
        $this->assertEquals($user->id, $log->user_id);
        $this->assertStringContainsString('Имя:', $log->description);
        $this->assertStringContainsString('Группа: ' . $team->title, $log->description);
        $this->assertStringContainsString('Роль:', $log->description);
    }

    /**
     * [P2] Обязательность контекста партнёра (ошибка, если partnerId не определён)
     */
    public function test_store_fails_when_partner_context_is_missing(): void
    {
        // Создаём пользователя БЕЗ партнёра — это «беда», которую должен поймать middleware
        $userWithoutPartner = User::factory()->create([
            'partner_id' => null,
            // на всякий случай дадим ему какую-то системную роль,
            // чтобы он прошёл auth и имел доступ к /admin/users
            'role_id' => Role::where('name', 'admin')->value('id') ?? 2,
        ]);

        // Авторизуемся этим пользователем
        $this->actingAs($userWithoutPartner);

        // Явно очищаем current_partner в сессии, чтобы SetPartner не мог его подхватить
        session()->forget('current_partner');

        // Пэйлоад может быть любым — до контроллера мы всё равно не дойдём,
        // но оставим валидный для наглядности
        $roleUserId = Role::where('name', 'user')->value('id') ?? 3;

        $payload = [
            'name'                  => 'Тест',
            'lastname'              => 'БезПартнёра',
            'email'                 => 'nopartner@example.com',
            'phone'                 => '+79990000003',
            'role_id'               => $roleUserId,
            'team_id'               => null,
            'birthday'              => '2015-03-03',
            'start_date'            => '2024-09-01',
            'is_enabled'            => 1,
            'password'              => 'TestPass123!',
            'password_confirmation' => 'TestPass123!',
        ];

        // Здесь важно проверять поведение именно middleware,
        // поэтому обычный POST (как браузер), а не обязательно postJson
        $response = $this->post('/admin/users', $payload);

        // 1) Middleware должен нас завернуть с редиректом
        $response->assertStatus(302);

        // 2) В сессии должны быть ошибки по ключу 'partner' с текстом "Партнёр не выбран."
        $response->assertSessionHasErrors([
            'partner' => 'Партнёр не выбран.',
        ]);

        // 3) Пользователь с таким email не должен быть создан
        $this->assertDatabaseMissing('users', [
            'email' => 'nopartner@example.com',
        ]);
    }

    /**
     * [P1] AJAX-ответ edit() для обычного админа: пользователь, поля, роли
     */
    public function test_edit_returns_user_fields_and_roles_for_partner_admin(): void
    {
        $adminRole = new Role();
        $adminRole->name = 'test-edit-admin';
        $adminRole->label = 'Test Edit Admin';
        $adminRole->is_sistem = 0;
        $adminRole->is_visible = 1;
        $adminRole->save();

        $this->user->role()->associate($adminRole);
        $this->user->save();

        $partnerFieldRole = new Role();
        $partnerFieldRole->name = 'test-field-role';
        $partnerFieldRole->label = 'Test Field Role';
        $partnerFieldRole->is_sistem = 0;
        $partnerFieldRole->is_visible = 1;
        $partnerFieldRole->save();

        DB::table('partner_role')->insert([
            'partner_id' => $this->partner->id,
            'role_id' => $partnerFieldRole->id,
        ]);

        $fieldAllowed = UserField::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Поле 1',
            'slug' => 'field_1',
        ]);
        $fieldAllowed->roles()->attach($adminRole->id);

        $fieldNotAllowed = UserField::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Поле 2',
            'slug' => 'field_2',
        ]);
        $fieldNotAllowed->roles()->attach($partnerFieldRole->id);

        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
            'birthday' => '2010-10-11',
        ]);

        $response = $this->getJson(
            '/admin/users/' . $user->id . '/edit',
            ['X-Requested-With' => 'XMLHttpRequest']
        );

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user',
                'currentUser' => ['role_id', 'isSuperadmin'],
                'fields',
                'roles',
            ]);

        $json = $response->json();

        $this->assertEquals($this->user->role_id, $json['currentUser']['role_id']);
        $this->assertFalse($json['currentUser']['isSuperadmin']);

        $this->assertEquals('2010-10-11', $json['user']['birthday']);

        $fieldIds = collect($json['fields'])->pluck('id')->all();
        $this->assertContains($fieldAllowed->id, $fieldIds);
        $this->assertNotContains($fieldNotAllowed->id, $fieldIds);

        $fieldPayload = collect($json['fields'])->firstWhere('id', $fieldAllowed->id);
        $this->assertTrue($fieldPayload['editable']);
    }

    /**
     * [P2] AJAX-ответ edit() для супер-админа
     */
    public function test_edit_returns_user_fields_and_roles_for_superadmin(): void
    {
        // супер-админ — любая системная роль
        $superRole = Role::where('is_sistem', 1)->first();
        if (!$superRole) {
            $superRole = new Role();
            $superRole->name = 'test-edit-super';
            $superRole->label = 'Test Edit Super';
            $superRole->is_sistem = 1;
            $superRole->is_visible = 1;
            $superRole->save();
        }

        $this->user->role()->associate($superRole);
        $this->user->save();

        $field1 = UserField::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Поле 1',
        ]);
        $field2 = UserField::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Поле 2',
        ]);

        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
        ]);

        $response = $this->getJson(
            '/admin/users/' . $user->id . '/edit',
            ['X-Requested-With' => 'XMLHttpRequest']
        );

        $response->assertStatus(200);
        $json = $response->json();

        $this->assertTrue($json['currentUser']['isSuperadmin']);

        $fieldIds = collect($json['fields'])->pluck('id')->all();
        $this->assertContains($field1->id, $fieldIds);
        $this->assertContains($field2->id, $fieldIds);

        foreach ($json['fields'] as $field) {
            $this->assertTrue($field['editable']);
        }
    }

    /**
     * [P1] Базовое обновление основных полей + лог только по изменённым полям
     */
    /**
     * [P1] Базовое обновление основных полей + лог только по изменённым полям
     */
    public function test_update_changes_basic_fields_and_logs_only_changed_values(): void
    {
        $oldTeam = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Старая группа',
        ]);
        $newTeam = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Новая группа',
        ]);

        $oldRole = new Role();
        $oldRole->name = 'test-old-role';
        $oldRole->label = 'Старая роль';
        $oldRole->is_sistem = 0;
        $oldRole->is_visible = 1;
        $oldRole->save();

        $newRole = new Role();
        $newRole->name = 'test-new-role';
        $newRole->label = 'Новая роль';
        $newRole->is_sistem = 0;
        $newRole->is_visible = 1;
        $newRole->save();

        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Иван',
            'lastname' => 'Старый',
            'email' => 'old@example.com',
            'is_enabled' => 1,
            'birthday' => '2010-01-01',
            'team_id' => $oldTeam->id,
            'role_id' => $oldRole->id,
            'phone' => '+70000000001',
        ]);

        // актор
        $this->actingAs($this->user);

        $payload = [
            'name' => 'Пётр',                // меняем
            'lastname' => 'Новый',              // меняем
            'email' => 'new@example.com',    // меняем
            'is_enabled' => 0,                    // меняем
            'birthday' => '2012-02-02',         // меняем
            'team_id' => $newTeam->id,         // меняем
            'role_id' => $newRole->id,         // меняем
            // телефон не трогаем
        ];

        // ⚠️ тут было putJson('/admin/users/...'), меняем на PATCH
        $response = $this->patchJson('/admin/users/' . $user->id, $payload);
        $response->assertStatus(200);

        $user->refresh();

        $this->assertEquals('Пётр', $user->name);
        $this->assertEquals('Новый', $user->lastname);
        $this->assertEquals('new@example.com', $user->email);
        $this->assertEquals(0, $user->is_enabled);
        $this->assertEquals('2012-02-02', $user->birthday->format('Y-m-d'));
        $this->assertEquals($newTeam->id, $user->team_id);
        $this->assertEquals($newRole->id, $user->role_id);

        $logs = MyLog::where('target_type', User::class)
            ->where('target_id', $user->id)
            ->where('action', 22)
            ->get();

        $this->assertCount(1, $logs);
        $log = $logs->first();

        $desc = $log->description;

        $this->assertStringContainsString('Имя: Иван → Пётр', $desc);
        $this->assertStringContainsString('Фамилия: Старый → Новый', $desc);
        $this->assertStringContainsString('Email: old@example.com → new@example.com', $desc);
        $this->assertStringContainsString('Активен: Да → Нет', $desc);
        $this->assertStringContainsString('Д.р: 01.01.2010 → 02.02.2012', $desc);
        $this->assertStringContainsString('Группа: Старая группа → Новая группа', $desc);
        $this->assertStringContainsString('Роль: Старая роль → Новая роль', $desc);

        $this->assertStringNotContainsString('Телефон:', $desc);
    }

    /**
     * [P1] Обновление телефона при наличии права users-phone-update
     */
    /**
     * [P1] Обновление телефона при наличии права users-phone-update
     */
    public function test_update_changes_phone_and_resets_verification_if_actor_has_permission(): void
    {
        // актор — админ партнёра
        $actor = $this->user;

        // включаем право users-phone-update
        \Illuminate\Support\Facades\Gate::define('users-phone-update', fn () => true);

        // пользователь, которому меняем телефон
        $user = User::factory()->create([
            'partner_id'        => $this->partner->id,
            'phone'             => '+70000000001',
            'phone_verified_at' => now(),
            // на всякий случай, чтобы точно были заполнены
            'name'              => 'Иван',
            'lastname'          => 'Иванов',
        ]);

        $this->actingAs($actor);

        $payload = [
            // обязательные для UpdateRequest поля — передаём их без изменений
            'name'     => $user->name,
            'lastname' => $user->lastname,

            // то, что реально хотим изменить
            'phone' => '+70000000002',
        ];

        // PATCH или PUT — оба сработают, если маршрут объявлен как resource
        $response = $this->patchJson('/admin/users/' . $user->id, $payload);
        $response->assertStatus(200);

        $user->refresh();

        $this->assertEquals('+70000000002', $user->phone);
        $this->assertNull($user->phone_verified_at);

        // Проверяем, что в логах есть запись об изменении телефона
        $log = MyLog::where('target_type', User::class)
            ->where('target_id', $user->id)
            ->where('action', 22)
            ->latest()
            ->first();

        $this->assertNotNull($log);
        $this->assertStringContainsString('Телефон:', $log->description);
        $this->assertStringContainsString('+70000000001', $log->description);
        $this->assertStringContainsString('+70000000002', $log->description);
    }

    /**
     * [P1] Попытка смены телефона без права users-phone-update
     */
    /**
     * [P1] Попытка смены телефона без права users-phone-update
     */
    /**
     * [P1] Попытка смены телефона без права users-phone-update
     */
    public function test_update_does_not_change_phone_without_permission(): void
    {
        Gate::define('users-phone-update', fn () => false);

        $user = User::factory()->create([
            'partner_id'        => $this->partner->id,
            'phone'             => '+70000000001',
            'phone_verified_at' => now(),
        ]);

        // ⚠️ опять же докидываем name/lastname
        $payload = [
            'phone'    => '+70000000002',
            'name'     => $user->name,
            'lastname' => $user->lastname,
        ];

        $response = $this->patchJson('/admin/users/' . $user->id, $payload);
        $response->assertStatus(200);

        $user->refresh();
        $this->assertEquals('+70000000001', $user->phone);
        $this->assertNotNull($user->phone_verified_at);

        $log = MyLog::where('target_type', User::class)
            ->where('target_id', $user->id)
            ->where('action', 22)
            ->first();

        if ($log) {
            $this->assertStringNotContainsString('Телефон:', $log->description);
        }
    }

    /**
     * [P1] Обновление кастом-полей: создание/обновление и лог только по изменённым
     */
    /**
     * [P1] Обновление кастом-полей: создание/обновление и лог только по изменённым
     */
    public function test_update_changes_custom_fields_and_logs_only_changed_ones(): void
    {
        // пользователь для редактирования
        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
            'name'       => 'Иван',
            'lastname'   => 'Иванов',
        ]);

        // два кастомных поля партнёра
        $fieldOne = UserField::factory()->create([
            'partner_id'  => $this->partner->id,
            'name'        => 'Поле 1',
            'slug'        => 'field_one',
            'field_type'  => 'string',
        ]);

        $fieldTwo = UserField::factory()->create([
            'partner_id'  => $this->partner->id,
            'name'        => 'Поле 2',
            'slug'        => 'field_two',
            'field_type'  => 'string',
        ]);

        // исходные значения
        UserFieldValue::factory()->create([
            'user_id'  => $user->id,
            'field_id' => $fieldOne->id,
            'value'    => 'old-1',
        ]);

        UserFieldValue::factory()->create([
            'user_id'  => $user->id,
            'field_id' => $fieldTwo->id,
            'value'    => 'old-2',
        ]);

        $this->actingAs($this->user);

        $payload = [
            // обязательные поля, чтобы пройти UpdateRequest
            'name'     => $user->name,
            'lastname' => $user->lastname,

            // меняем только одно кастомное поле
            'custom' => [
                'field_one' => 'new-1', // изменится
                'field_two' => 'old-2', // останется как было
            ],
        ];

        $response = $this->patchJson('/admin/users/' . $user->id, $payload);
        $response->assertStatus(200);

        // Проверяем, что значения в БД обновились корректно
        $val1 = UserFieldValue::where('user_id', $user->id)
            ->where('field_id', $fieldOne->id)
            ->first();
        $val2 = UserFieldValue::where('user_id', $user->id)
            ->where('field_id', $fieldTwo->id)
            ->first();

        $this->assertEquals('new-1', $val1->value);
        $this->assertEquals('old-2', $val2->value);

        // Ищем лог
        $log = MyLog::where('target_type', User::class)
            ->where('target_id', $user->id)
            ->where('action', 22)
            ->latest()
            ->first();

        $this->assertNotNull($log);

        // В лог должна попасть только строка по изменённому полю
        $this->assertStringContainsString('Поле 1: old-1 → new-1', $log->description);
        $this->assertStringNotContainsString('Поле 2', $log->description);
    }

    /**
     * [P2] Отсутствие логирования при отсутствии изменений
     */
    /**
     * [P2] Отсутствие логирования при отсутствии изменений
     */
    public function test_update_does_not_write_log_if_there_are_no_changes(): void
    {
        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Иван',
            'lastname' => 'Петров',
            'email' => 'ivan@example.com',
            'is_enabled' => 1,
            'birthday' => '2010-01-01',
        ]);

        $payload = [
            'name' => 'Иван',
            'lastname' => 'Петров',
            'email' => 'ivan@example.com',
            'is_enabled' => 1,
            'birthday' => '2010-01-01',
        ];

        // ⚠️ было putJson
        $response = $this->patchJson('/admin/users/' . $user->id, $payload);
        $response->assertStatus(200);

        $logs = MyLog::where('target_type', User::class)
            ->where('target_id', $user->id)
            ->where('action', 22)
            ->get();

        $this->assertCount(0, $logs);
    }


    /**
     * [P1] Успешное удаление пользователя своего партнёра + лог
     */
    /**
     * [P1] Успешное удаление пользователя своего партнёра + лог
     */
    public function test_delete_removes_user_of_current_partner_and_writes_log(): void
    {
        $userToDelete = User::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Удаляемый',
            'lastname' => 'Пользователь',
        ]);

        // ⚠️ было /admin/users/{id}
        $response = $this->deleteJson('/admin/user/' . $userToDelete->id);
        $response->assertStatus(200)
            ->assertJson([
                'success' => 'Пользователь успешно удалён',
            ]);

        $this->assertSoftDeleted('users', [
            'id' => $userToDelete->id,
        ]);

        $log = MyLog::where('target_type', User::class)
            ->where('target_id', $userToDelete->id)
            ->where('action', 24)
            ->first();

        $this->assertNotNull($log);
        $this->assertStringContainsString('Удален пользователь', $log->description);
        $this->assertStringContainsString((string)$userToDelete->id, $log->description);
    }

    /**
     * [P1] Запрет удаления пользователя чужого партнёра для обычного админа
     */
    /**
     * [P1] Запрет удаления пользователя чужого партнёра для обычного админа
     */
    public function test_delete_forbidden_for_user_of_another_partner_for_non_superadmin(): void
    {
        $otherPartner = Partner::factory()->create();

        $foreignUser = User::factory()->create([
            'partner_id' => $otherPartner->id,
        ]);

        // ⚠️ было /admin/users/{id}
        $response = $this->deleteJson('/admin/user/' . $foreignUser->id);
        $response->assertStatus(403);
        $this->assertNotSoftDeleted('users', ['id' => $foreignUser->id]);

        $log = MyLog::where('target_type', User::class)
            ->where('target_id', $foreignUser->id)
            ->where('action', 24)
            ->first();

        $this->assertNull($log);
    }
    /**
     * [P1] Успешная смена пароля своего пользователя (или пользователя того же партнёра)
     */
    /**
     * [P1] Успешная смена пароля своего пользователя (или пользователя того же партнёра)
     */
    public function test_update_password_changes_password_for_same_partner_user_and_logs_it(): void
    {
        $userToChange = User::factory()->create([
            'partner_id' => $this->partner->id,
            'password' => Hash::make('old-password'),
            'name' => 'Обучающийся',
        ]);

        $payload = [
            'password' => 'new-secure-password',
        ];

        // ⚠️ было putJson('/admin/users/{id}/password')
        $response = $this->postJson('/admin/user/' . $userToChange->id . '/update-password', $payload);
        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $userToChange->refresh();
        $this->assertTrue(Hash::check('new-secure-password', $userToChange->password));

        $log = MyLog::where('target_type', User::class)
            ->where('target_id', $userToChange->id)
            ->where('action', 26)
            ->first();

        $this->assertNotNull($log);
        $this->assertStringContainsString('Пароль пользователя', $log->description);
        $this->assertStringContainsString($userToChange->name, $log->description);
        $this->assertStringContainsString($this->user->name, $log->description);
    }

    /**
     * [P1] Запрет смены пароля пользователя чужого партнёра
     */
    /**
     * [P1] Запрет смены пароля пользователя чужого партнёра
     */
    public function test_update_password_forbidden_for_user_of_another_partner_for_non_superadmin(): void
    {
        $otherPartner = Partner::factory()->create();

        $foreignUser = User::factory()->create([
            'partner_id' => $otherPartner->id,
            'password' => Hash::make('old-password'),
        ]);

        $payload = [
            'password' => 'new-pass',
        ];

        // ⚠️ было putJson('/admin/users/{id}/password')
        $response = $this->postJson('/admin/user/' . $foreignUser->id . '/update-password', $payload);
        $response->assertStatus(403);

        $foreignUser->refresh();
        $this->assertTrue(Hash::check('old-password', $foreignUser->password));

        $log = MyLog::where('target_type', User::class)
            ->where('target_id', $foreignUser->id)
            ->where('action', 26)
            ->first();

        $this->assertNull($log);
    }

    /**
     * [P1] Попытка установить тот же самый пароль
     */
    /**
     * [P1] Попытка установить тот же самый пароль
     */
    public function test_update_password_rejects_same_password_with_422(): void
    {
        $userToChange = User::factory()->create([
            'partner_id' => $this->partner->id,
            'password' => Hash::make('same-password'),
        ]);

        $payload = [
            'password' => 'same-password',
        ];

        // ⚠️ было putJson('/admin/users/{id}/password')
        $response = $this->postJson('/admin/user/' . $userToChange->id . '/update-password', $payload);
        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'Новый пароль совпадает с текущим.',
        ]);

        $userToChange->refresh();
        $this->assertTrue(Hash::check('same-password', $userToChange->password));

        $log = MyLog::where('target_type', User::class)
            ->where('target_id', $userToChange->id)
            ->where('action', 26)
            ->first();

        $this->assertNull($log);
    }
}