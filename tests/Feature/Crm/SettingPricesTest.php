<?php

namespace Tests\Feature\Crm;

use App\Models\MyLog;
use App\Models\Partner;
use App\Models\Team;
use App\Models\TeamPrice;
use App\Models\User;
use App\Models\UserPrice;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Support\Carbon;
use Tests\Feature\Crm\CrmTestCase;

class SettingPricesTest extends CrmTestCase
{
    /** @test */
    public function routes_require_set_prices_permission()
    {
        // Пользователь и партнёр уже созданы в CrmTestCase::setUp

        $team   = Team::factory()->create(['partner_id' => $this->partner->id]);
        $user   = User::factory()->create(['partner_id' => $this->partner->id, 'team_id' => $team->id]);

        // Без явного разрешения setPrices-view ожидаем 403 на все маршруты
        $this->get(route('admin.settingPrices.indexMenu'))->assertStatus(403);

        $this->postJson(route('getTeamPrice'), [
            'teamId'       => $team->id,
            'selectedDate' => 'Сентябрь 2024',
        ])->assertStatus(403);

        $this->postJson(route('setTeamPrice'), [
            'teamId'       => $team->id,
            'teamPrice'    => 1000,
            'selectedDate' => 'Сентябрь 2024',
        ])->assertStatus(403);

        $this->postJson(route('setPriceAllTeams'), [
            'selectedDate' => 'Сентябрь 2024',
            'teamsData'    => [
                ['teamId' => $team->id, 'price' => 1000],
            ],
        ])->assertStatus(403);

        $this->postJson(route('setPriceAllUsers'), [
            'selectedDate' => 'Сентябрь 2024',
            'usersPrice'   => [
                ['user_id' => $user->id, 'price' => 1000, 'user' => ['name' => $user->name]],
            ],
        ])->assertStatus(403);

        $this->get(route('logs.data.settingPrice'))->assertStatus(403);

        $this->post(route('updateDate'), [
            'month' => 'Сентябрь 2024',
        ])->assertStatus(403);
    }

    /** @test */
    public function routes_work_with_set_prices_permission_smoke_for_all_ajax()
    {
        // Отключаем авторизацию can:* для смоук-теста
        $this->withoutMiddleware(Authorize::class);

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'deleted_at' => null,
        ]);

        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id'    => $team->id,
            'is_enabled' => true,
        ]);

        // index
        $this->get(route('admin.settingPrices.indexMenu'))
            ->assertStatus(200)
            ->assertViewIs('admin.settingPrices');

        // updateDate
        $this->post(route('updateDate'), [
            'month' => 'Сентябрь 2024',
        ])->assertStatus(200)
            ->assertJson([
                'success' => true,
                'month'   => 'Сентябрь 2024',
            ]);

        // getTeamPrice
        $this->postJson(route('getTeamPrice'), [
            'teamId'       => $team->id,
            'selectedDate' => 'Сентябрь 2024',
        ])->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'usersTeam',
                'usersPrice',
            ]);

        // setTeamPrice
        $this->postJson(route('setTeamPrice'), [
            'teamId'       => $team->id,
            'teamPrice'    => 1500,
            'selectedDate' => 'Сентябрь 2024',
        ])->assertStatus(200)
            ->assertJson([
                'success' => true,
                'teamId'  => $team->id,
            ]);

        // setPriceAllTeams
        $this->postJson(route('setPriceAllTeams'), [
            'selectedDate' => 'Сентябрь 2024',
            'teamsData'    => [
                ['teamId' => $team->id, 'price' => 2000],
            ],
        ])->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        // setPriceAllUsers
        $this->postJson(route('setPriceAllUsers'), [
            'selectedDate' => 'Сентябрь 2024',
            'usersPrice'   => [
                ['user_id' => $user->id, 'price' => 2100, 'user' => ['name' => $user->name]],
            ],
        ])->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        // getLogsData (DataTables)
        $this->get(route('logs.data.settingPrice'))
            ->assertStatus(200);
    }

    /** @test */
    public function index_shows_only_current_partner_teams_and_initializes_team_prices()
    {
        $this->withoutMiddleware(Authorize::class);

        // Команды текущего партнёра
        $team1 = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'order_by'   => 2,
            'deleted_at' => null,
        ]);
        $team2 = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'order_by'   => 1,
            'deleted_at' => null,
        ]);

        // Команда другого партнёра
        $otherPartner = Partner::factory()->create();
        Team::factory()->create([
            'partner_id' => $otherPartner->id,
            'deleted_at' => null,
        ]);

        // Без записей в team_prices
        $this->assertDatabaseCount('team_prices', 0);

        $response = $this->get(route('admin.settingPrices.indexMenu'))
            ->assertStatus(200)
            ->assertViewIs('admin.settingPrices');

        // Команды в представлении — только текущего партнёра, отсортированы по order_by
        $viewTeams = $response->viewData('allTeams');
        $this->assertCount(2, $viewTeams);
        $this->assertEquals([$team2->id, $team1->id], $viewTeams->pluck('id')->all());

        // team_prices созданы для всех команд текущего партнёра за текущий месяц
        $monthString = $response->viewData('monthString');
        $this->assertNotEmpty($monthString);

        // Превращаем с помощью формата контроллера (проверим ниже отдельно)
        $currentMonthDate = (new Carbon())->startOfMonth()->format('Y-m-d');

        $this->assertDatabaseHas('team_prices', [
            'team_id'   => $team1->id,
            'new_month' => $currentMonthDate,
        ]);
        $this->assertDatabaseHas('team_prices', [
            'team_id'   => $team2->id,
            'new_month' => $currentMonthDate,
        ]);
    }

    /** @test */
    public function update_date_changes_month_and_initializes_team_prices_for_current_partner()
    {
        $this->withoutMiddleware(Authorize::class);

        $team1 = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'deleted_at' => null,
        ]);
        $team2 = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'deleted_at' => null,
        ]);

        $otherPartner = Partner::factory()->create();
        $foreignTeam  = Team::factory()->create([
            'partner_id' => $otherPartner->id,
            'deleted_at' => null,
        ]);

        $this->post(route('updateDate'), ['month' => 'Сентябрь 2024'])
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'month'   => 'Сентябрь 2024',
            ]);

        // В сессии сохранён выбранный месяц
        $this->assertEquals('Сентябрь 2024', session('prices_month'));

        // Даты для текущего партнёра созданы
        $this->assertDatabaseHas('team_prices', [
            'team_id'   => $team1->id,
            'new_month' => '2024-09-01',
        ]);
        $this->assertDatabaseHas('team_prices', [
            'team_id'   => $team2->id,
            'new_month' => '2024-09-01',
        ]);

        // Для чужой команды запись не должна создаваться
        $this->assertDatabaseMissing('team_prices', [
            'team_id'   => $foreignTeam->id,
            'new_month' => '2024-09-01',
        ]);
    }

    /** @test */
    public function update_date_parses_russian_month_name_correctly()
    {
        $this->withoutMiddleware(Authorize::class);

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'deleted_at' => null,
        ]);

        $this->post(route('updateDate'), ['month' => 'Сентябрь 2024'])
            ->assertStatus(200);

        $this->assertDatabaseHas('team_prices', [
            'team_id'   => $team->id,
            'new_month' => '2024-09-01',
        ]);
    }

    /** @test */
    /** @test */
    public function get_team_price_returns_active_users_and_creates_user_prices()
    {
        $this->withoutMiddleware(Authorize::class);

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'deleted_at' => null,
        ]);

        // Активные пользователи команды
        $activeUser1 = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id'    => $team->id,
            'is_enabled' => true,
            'lastname'   => 'Иванов',
            'name'       => 'Пётр',
        ]);

        $activeUser2 = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id'    => $team->id,
            'is_enabled' => true,
            'lastname'   => 'Андреев',
            'name'       => 'Сергей',
        ]);

        // Отключённый пользователь — не должен попасть ни в список, ни в users_prices
        $disabledUser = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id'    => $team->id,
            'is_enabled' => false,
        ]);

        // Перед вызовом контроллера таблица должна быть пустой
        $this->assertDatabaseCount('users_prices', 0);

        $response = $this->postJson(route('getTeamPrice'), [
            'teamId'       => $team->id,
            'selectedDate' => 'Сентябрь 2024',
        ])->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $json = $response->json();

        // usersTeam: только активные, отсортированы по фамилии, затем по имени
        $this->assertCount(2, $json['usersTeam']);
        $this->assertEquals(
            [$activeUser2->id, $activeUser1->id], // Андреев, затем Иванов
            array_column($json['usersTeam'], 'id')
        );

        // Для активных созданы UserPrice за 2024-09-01 с ценой 0
        foreach ([$activeUser1, $activeUser2] as $user) {
            $this->assertDatabaseHas('users_prices', [
                'user_id'   => $user->id,
                'new_month' => '2024-09-01',
                'price'     => 0,
            ]);
        }

        // Для отключённого пользователя записи нет
        $this->assertDatabaseMissing('users_prices', [
            'user_id'   => $disabledUser->id,
            'new_month' => '2024-09-01',
        ]);

        // usersPrice по количеству совпадает с активными
        $this->assertCount(2, $json['usersPrice']);
    }

    /** @test */
    public function get_team_price_cannot_access_foreign_partner_team()
    {
        $this->withoutMiddleware(Authorize::class);

        $otherPartner = Partner::factory()->create();
        $foreignTeam  = Team::factory()->create([
            'partner_id' => $otherPartner->id,
            'deleted_at' => null,
        ]);
        User::factory()->count(2)->create([
            'partner_id' => $otherPartner->id,
            'team_id'    => $foreignTeam->id,
            'is_enabled' => true,
        ]);

        $response = $this->postJson(route('getTeamPrice'), [
            'teamId'       => $foreignTeam->id,
            'selectedDate' => 'Сентябрь 2024',
        ]);

        // Желаемое поведение — запрет доступа или хотя бы отсутствие данных чужого партнёра.
        // Здесь фиксируем как 404 — под эту логику потом надо будет подтянуть контроллер.
        $response->assertStatus(404);
    }

    /** @test */
    /** @test */
    public function set_team_price_updates_team_and_unpaid_active_users_only()
    {
        $this->withoutMiddleware(Authorize::class);

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'deleted_at' => null,
            'title'      => 'Группа А',
        ]);

        // Пользователь с неоплаченной ценой
        $unpaidUser = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id'    => $team->id,
            'is_enabled' => true,
        ]);

        UserPrice::forceCreate([
            'user_id'   => $unpaidUser->id,
            'new_month' => '2024-09-01',
            'price'     => 1000,
            'is_paid'   => 0,
        ]);

        // Пользователь с оплаченной ценой
        $paidUser = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id'    => $team->id,
            'is_enabled' => true,
        ]);

        UserPrice::forceCreate([
            'user_id'   => $paidUser->id,
            'new_month' => '2024-09-01',
            'price'     => 800,
            'is_paid'   => 1,
        ]);

        // Отключённый пользователь
        $disabledUser = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id'    => $team->id,
            'is_enabled' => false,
        ]);

        $this->postJson(route('setTeamPrice'), [
            'teamId'       => $team->id,
            'teamPrice'    => 1500,
            'selectedDate' => 'Сентябрь 2024',
        ])->assertStatus(200)
            ->assertJson([
                'success' => true,
                'teamId'  => $team->id,
            ]);

        // TeamPrice обновлён/создан
        $this->assertDatabaseHas('team_prices', [
            'team_id'   => $team->id,
            'new_month' => '2024-09-01',
            'price'     => 1500,
        ]);

        // Неоплаченный — обновлён
        $this->assertDatabaseHas('users_prices', [
            'user_id'   => $unpaidUser->id,
            'new_month' => '2024-09-01',
            'price'     => 1500,
            'is_paid'   => 0,
        ]);

        // Оплаченный — не изменён
        $this->assertDatabaseHas('users_prices', [
            'user_id'   => $paidUser->id,
            'new_month' => '2024-09-01',
            'price'     => 800,
            'is_paid'   => 1,
        ]);

        // Для отключённого — записи нет
        $this->assertDatabaseMissing('users_prices', [
            'user_id'   => $disabledUser->id,
            'new_month' => '2024-09-01',
        ]);

        // Лог
        $this->assertDatabaseHas('my_logs', [
            'type'        => 1,
            'action'      => 13,
            'target_id'   => $team->id,
            'target_type' => 'App\Models\UserPrice',
            'target_label'=> 'Группа А',
        ]);
    }

    /** @test */
    public function set_team_price_does_not_touch_other_months_and_teams()
    {
        $this->withoutMiddleware(Authorize::class);

        $teamX = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'deleted_at' => null,
        ]);
        $teamY = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'deleted_at' => null,
        ]);

        TeamPrice::forceCreate([
            'team_id'   => $teamX->id,
            'new_month' => '2024-09-01',
            'price'     => 1000,
        ]);
        TeamPrice::forceCreate([
            'team_id'   => $teamX->id,
            'new_month' => '2024-10-01',
            'price'     => 2000,
        ]);
        TeamPrice::forceCreate([
            'team_id'   => $teamY->id,
            'new_month' => '2024-09-01',
            'price'     => 3000,
        ]);

        $this->postJson(route('setTeamPrice'), [
            'teamId'       => $teamX->id,
            'teamPrice'    => 1500,
            'selectedDate' => 'Сентябрь 2024',
        ])->assertStatus(200);

        // Обновился только September для teamX
        $this->assertDatabaseHas('team_prices', [
            'team_id'   => $teamX->id,
            'new_month' => '2024-09-01',
            'price'     => 1500,
        ]);
        // October для teamX не изменился
        $this->assertDatabaseHas('team_prices', [
            'team_id'   => $teamX->id,
            'new_month' => '2024-10-01',
            'price'     => 2000,
        ]);
        // TeamY за September не тронут
        $this->assertDatabaseHas('team_prices', [
            'team_id'   => $teamY->id,
            'new_month' => '2024-09-01',
            'price'     => 3000,
        ]);
    }

    /** @test */
    public function set_team_price_cannot_update_foreign_partner_team()
    {
        $this->withoutMiddleware(Authorize::class);

        $otherPartner = Partner::factory()->create();
        $foreignTeam  = Team::factory()->create([
            'partner_id' => $otherPartner->id,
            'deleted_at' => null,
        ]);

        $this->postJson(route('setTeamPrice'), [
            'teamId'       => $foreignTeam->id,
            'teamPrice'    => 1500,
            'selectedDate' => 'Сентябрь 2024',
        ])->assertStatus(404);
    }

    /** @test */
    public function set_price_all_teams_updates_listed_teams_and_users()
    {
        $this->withoutMiddleware(Authorize::class);

        $teamA = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'deleted_at' => null,
            'title'      => 'Team A',
        ]);
        $teamB = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'deleted_at' => null,
            'title'      => 'Team B',
        ]);

        $userA1 = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id'    => $teamA->id,
            'is_enabled' => true,
        ]);
        $userA2 = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id'    => $teamA->id,
            'is_enabled' => true,
        ]);
        $userB1 = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id'    => $teamB->id,
            'is_enabled' => true,
        ]);

        // уже есть цены
        UserPrice::forceCreate([
            'user_id'   => $userA1->id,
            'new_month' => '2024-09-01',
            'price'     => 1000,
            'is_paid'   => 0,
        ]);
        UserPrice::forceCreate([
            'user_id'   => $userB1->id,
            'new_month' => '2024-09-01',
            'price'     => 2500,
            'is_paid'   => 1,
        ]);

        $this->postJson(route('setPriceAllTeams'), [
            'selectedDate' => 'Сентябрь 2024',
            'teamsData'    => [
                ['teamId' => $teamA->id, 'price' => 2000],
                ['teamId' => $teamB->id, 'price' => 3000],
            ],
        ])->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        // TeamPrice обновлены
        $this->assertDatabaseHas('team_prices', [
            'team_id'   => $teamA->id,
            'new_month' => '2024-09-01',
            'price'     => 2000,
        ]);
        $this->assertDatabaseHas('team_prices', [
            'team_id'   => $teamB->id,
            'new_month' => '2024-09-01',
            'price'     => 3000,
        ]);

        // userA1: был не оплачен — обновился
        $this->assertDatabaseHas('users_prices', [
            'user_id'   => $userA1->id,
            'new_month' => '2024-09-01',
            'price'     => 2000,
            'is_paid'   => 0,
        ]);

        // userA2: записи не было — появилась
        $this->assertDatabaseHas('users_prices', [
            'user_id'   => $userA2->id,
            'new_month' => '2024-09-01',
            'price'     => 2000,
            'is_paid'   => 0,
        ]);

        // userB1: is_paid = 1 — не изменился
        $this->assertDatabaseHas('users_prices', [
            'user_id'   => $userB1->id,
            'new_month' => '2024-09-01',
            'price'     => 2500,
            'is_paid'   => 1,
        ]);

        // Логи по каждой команде
        $this->assertDatabaseHas('my_logs', [
            'type'        => 1,
            'action'      => 11,
            'target_id'   => $teamA->id,
            'target_type' => 'App\Models\UserPrice',
            'target_label'=> 'Team A',
        ]);
        $this->assertDatabaseHas('my_logs', [
            'type'        => 1,
            'action'      => 11,
            'target_id'   => $teamB->id,
            'target_type' => 'App\Models\UserPrice',
            'target_label'=> 'Team B',
        ]);
    }

    /** @test */
    public function set_price_all_teams_ignores_teams_not_in_payload()
    {
        $this->withoutMiddleware(Authorize::class);

        $teamA = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'deleted_at' => null,
        ]);
        $teamB = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'deleted_at' => null,
        ]);
        $teamC = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'deleted_at' => null,
        ]);

        TeamPrice::forceCreate([
            'team_id'   => $teamA->id,
            'new_month' => '2024-09-01',
            'price'     => 1000,
        ]);
        TeamPrice::forceCreate([
            'team_id'   => $teamB->id,
            'new_month' => '2024-09-01',
            'price'     => 2000,
        ]);
        TeamPrice::forceCreate([
            'team_id'   => $teamC->id,
            'new_month' => '2024-09-01',
            'price'     => 3000,
        ]);

        $this->postJson(route('setPriceAllTeams'), [
            'selectedDate' => 'Сентябрь 2024',
            'teamsData'    => [
                ['teamId' => $teamA->id, 'price' => 4000],
                ['teamId' => $teamB->id, 'price' => 5000],
            ],
        ])->assertStatus(200);

        // A, B — обновились
        $this->assertDatabaseHas('team_prices', [
            'team_id'   => $teamA->id,
            'new_month' => '2024-09-01',
            'price'     => 4000,
        ]);
        $this->assertDatabaseHas('team_prices', [
            'team_id'   => $teamB->id,
            'new_month' => '2024-09-01',
            'price'     => 5000,
        ]);

        // C — не тронута
        $this->assertDatabaseHas('team_prices', [
            'team_id'   => $teamC->id,
            'new_month' => '2024-09-01',
            'price'     => 3000,
        ]);
    }

    /** @test */
    public function set_price_all_teams_returns_400_on_invalid_teams_data()
    {
        $this->withoutMiddleware(Authorize::class);

        // teamsData = null
        $this->postJson(route('setPriceAllTeams'), [
            'selectedDate' => 'Сентябрь 2024',
            'teamsData'    => null,
        ])->assertStatus(400)
            ->assertJson([
                'error' => 'Invalid teams data1',
            ]);

        // teamsData не массив
        $this->postJson(route('setPriceAllTeams'), [
            'selectedDate' => 'Сентябрь 2024',
            'teamsData'    => 'not-an-array',
        ])->assertStatus(400)
            ->assertJson([
                'error' => 'Invalid teams data1',
            ]);
    }

    /** @test */
    public function set_price_all_users_updates_only_changed_unpaid_prices()
    {
        $this->withoutMiddleware(Authorize::class);

        $user1 = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);
        $user2 = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);
        $user3 = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);

        UserPrice::forceCreate([
            'user_id'   => $user1->id,
            'new_month' => '2024-09-01',
            'price'     => 1000,
            'is_paid'   => 0,
        ]);
        UserPrice::forceCreate([
            'user_id'   => $user2->id,
            'new_month' => '2024-09-01',
            'price'     => 2000,
            'is_paid'   => 0,
        ]);
        UserPrice::forceCreate([
            'user_id'   => $user3->id,
            'new_month' => '2024-09-01',
            'price'     => 3000,
            'is_paid'   => 1,
        ]);

        $this->postJson(route('setPriceAllUsers'), [
            'selectedDate' => 'Сентябрь 2024',
            'usersPrice'   => [
                [
                    'user_id' => $user1->id,
                    'price'   => 1500,
                    'user'    => ['name' => 'User1'],
                ],
                [
                    'user_id' => $user2->id,
                    'price'   => 2000, // не меняется
                    'user'    => ['name' => 'User2'],
                ],
                [
                    'user_id' => $user3->id,
                    'price'   => 5000, // is_paid=1
                    'user'    => ['name' => 'User3'],
                ],
            ],
        ])->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        // user1 — обновлён
        $this->assertDatabaseHas('users_prices', [
            'user_id'   => $user1->id,
            'new_month' => '2024-09-01',
            'price'     => 1500,
            'is_paid'   => 0,
        ]);

        // user2 — без изменений
        $this->assertDatabaseHas('users_prices', [
            'user_id'   => $user2->id,
            'new_month' => '2024-09-01',
            'price'     => 2000,
            'is_paid'   => 0,
        ]);

        // user3 — не тронут
        $this->assertDatabaseHas('users_prices', [
            'user_id'   => $user3->id,
            'new_month' => '2024-09-01',
            'price'     => 3000,
            'is_paid'   => 1,
        ]);

        // Логи только по user1
        $this->assertDatabaseHas('my_logs', [
            'type'        => 1,
            'action'      => 12,
            'user_id'     => $user1->id,
            'target_id'   => $user1->id,
            'target_type' => 'App\Models\UserPrice',
            'target_label'=> 'User1',
        ]);

        $this->assertDatabaseMissing('my_logs', [
            'type'   => 1,
            'action' => 12,
            'user_id'=> $user2->id,
        ]);
        $this->assertDatabaseMissing('my_logs', [
            'type'   => 1,
            'action' => 12,
            'user_id'=> $user3->id,
        ]);
    }

    /** @test */
    public function set_price_all_users_does_not_create_new_records_or_touch_absent_users()
    {
        $this->withoutMiddleware(Authorize::class);

        $user1 = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);
        $user2 = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);
        $user3 = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);
        $user4 = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);

        UserPrice::forceCreate([
            'user_id'   => $user1->id,
            'new_month' => '2024-09-01',
            'price'     => 1000,
            'is_paid'   => 0,
        ]);
        UserPrice::forceCreate([
            'user_id'   => $user2->id,
            'new_month' => '2024-09-01',
            'price'     => 2000,
            'is_paid'   => 0,
        ]);
        UserPrice::forceCreate([
            'user_id'   => $user3->id,
            'new_month' => '2024-09-01',
            'price'     => 3000,
            'is_paid'   => 0,
        ]);

        // user4 — без UserPrice за этот месяц

        $this->postJson(route('setPriceAllUsers'), [
            'selectedDate' => 'Сентябрь 2024',
            'usersPrice'   => [
                [
                    'user_id' => $user1->id,
                    'price'   => 1500,
                    'user'    => ['name' => 'User1'],
                ],
                [
                    'user_id' => $user2->id,
                    'price'   => 2500,
                    'user'    => ['name' => 'User2'],
                ],
            ],
        ])->assertStatus(200);

        // user1 и user2 — обновлены
        $this->assertDatabaseHas('users_prices', [
            'user_id'   => $user1->id,
            'new_month' => '2024-09-01',
            'price'     => 1500,
        ]);
        $this->assertDatabaseHas('users_prices', [
            'user_id'   => $user2->id,
            'new_month' => '2024-09-01',
            'price'     => 2500,
        ]);

        // user3 — не изменён
        $this->assertDatabaseHas('users_prices', [
            'user_id'   => $user3->id,
            'new_month' => '2024-09-01',
            'price'     => 3000,
        ]);

        // user4 — записи так и нет
        $this->assertDatabaseMissing('users_prices', [
            'user_id'   => $user4->id,
            'new_month' => '2024-09-01',
        ]);
    }

    /** @test */
    public function set_price_all_users_returns_400_on_invalid_payload()
    {
        $this->withoutMiddleware(Authorize::class);

        // usersPrice = null
        $this->postJson(route('setPriceAllUsers'), [
            'selectedDate' => 'Сентябрь 2024',
            'usersPrice'   => null,
        ])->assertStatus(400)
            ->assertJson([
                'error' => 'Некорректные данные',
            ]);

        // usersPrice не массив
        $this->postJson(route('setPriceAllUsers'), [
            'selectedDate' => 'Сентябрь 2024',
            'usersPrice'   => 'not-array',
        ])->assertStatus(400)
            ->assertJson([
                'error' => 'Некорректные данные',
            ]);
    }

    /** @test */
    public function logs_are_written_correctly_for_three_operations()
    {
        $this->withoutMiddleware(Authorize::class);

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'deleted_at' => null,
            'title'      => 'TeamLog',
        ]);

        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id'    => $team->id,
            'is_enabled' => true,
            'name'       => 'UserLog',
        ]);

        // setTeamPrice (action=13)
        $this->postJson(route('setTeamPrice'), [
            'teamId'       => $team->id,
            'teamPrice'    => 1500,
            'selectedDate' => 'Сентябрь 2024',
        ])->assertStatus(200);

        // setPriceAllTeams (action=11)
        $this->postJson(route('setPriceAllTeams'), [
            'selectedDate' => 'Сентябрь 2024',
            'teamsData'    => [
                ['teamId' => $team->id, 'price' => 2000],
            ],
        ])->assertStatus(200);

        // setPriceAllUsers (action=12)
        UserPrice::forceCreate([
            'user_id'   => $user->id,
            'new_month' => '2024-09-01',
            'price'     => 1000,
            'is_paid'   => 0,
        ]);

        $this->postJson(route('setPriceAllUsers'), [
            'selectedDate' => 'Сентябрь 2024',
            'usersPrice'   => [
                [
                    'user_id' => $user->id,
                    'price'   => 2100,
                    'user'    => ['name' => 'UserLog'],
                ],
            ],
        ])->assertStatus(200);

        // Проверяем наличие логов по типам действий
        $this->assertDatabaseHas('my_logs', [
            'type'   => 1,
            'action' => 13,
            'target_id' => $team->id,
        ]);
        $this->assertDatabaseHas('my_logs', [
            'type'   => 1,
            'action' => 11,
            'target_id' => $team->id,
        ]);
        $this->assertDatabaseHas('my_logs', [
            'type'   => 1,
            'action' => 12,
            'user_id'=> $user->id,
        ]);
    }

    /** @test */
    public function get_logs_data_returns_datatables_response_with_type_one_logs()
    {
        $this->withoutMiddleware(Authorize::class);

        // Лог типа 1
        MyLog::forceCreate([
            'type'        => 1,
            'action'      => 11,
            'description' => 'log1',
            'target_type' => 'App\Models\UserPrice',
            'target_id'   => 1,
            'created_at'  => now(),
        ]);

        // Лог другого типа
        MyLog::forceCreate([
            'type'        => 2,
            'action'      => 99,
            'description' => 'log2',
            'target_type' => 'App\Models\UserPrice',
            'target_id'   => 2,
            'created_at'  => now(),
        ]);

        $response = $this->get(route('logs.data.settingPrice'))
            ->assertStatus(200);

        $json = $response->json();

        $this->assertArrayHasKey('data', $json);
        $this->assertIsArray($json['data']);

        // Все строки должны быть с type=1
        foreach ($json['data'] as $row) {
            $this->assertEquals(1, $row['type']);
        }
    }
}