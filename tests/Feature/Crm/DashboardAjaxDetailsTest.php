<?php

namespace Tests\Feature\Crm;

use App\Models\Partner;
use App\Models\ScheduleUser;
use App\Models\Team;
use App\Models\User;
use App\Models\UserField;
use App\Models\UserPrice;
use App\Models\Weekday;

class DashboardAjaxDetailsTest extends CrmTestCase
{
    /**
     * P0.4 — Доступ к данным только в рамках текущего партнёра — getUserDetails
     *
     * Пользователь другого партнёра не должен "просвечивать" через /get-user-details.
     */
    public function test_user_details_endpoint_does_not_leak_foreign_partner_user(): void
    {
        /** @var Partner $foreignPartner */
        $foreignPartner = Partner::factory()->create();
        /** @var User $foreignUser */
        $foreignUser = User::factory()->create([
            'partner_id' => $foreignPartner->id,
        ]);

        $response = $this->getJson(route('getUserDetails', ['userId' => $foreignUser->id]));

        $response
            ->assertOk()
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * P0.5 — Доступ к данным только в рамках текущего партнёра — getTeamDetails
     *
     * Команда другого партнёра не должна быть доступна.
     */
    public function test_team_details_endpoint_does_not_leak_foreign_partner_team(): void
    {
        /** @var Partner $foreignPartner */
        $foreignPartner = Partner::factory()->create();
        /** @var Team $foreignTeam */
        $foreignTeam = Team::factory()->create([
            'partner_id' => $foreignPartner->id,
        ]);

        $response = $this->getJson(route('getTeamDetails', [
            'teamId'   => $foreignTeam->id,
            'teamName' => $foreignTeam->name,
        ]));

        $response
            ->assertOk()
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * P1.1 — Успешное получение данных пользователя текущего партнёра
     */
    public function test_get_user_details_returns_expected_data_for_current_partner_user(): void
    {
        // Команда текущего партнёра
        /** @var Team $team */
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
        ]);

        /** @var User $user */
        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id'    => $team->id,
            'birthday'   => '2000-01-15',
        ]);

        // Никаких UserPrice::factory() / ScheduleUser::factory() — контроллер и без них живёт

        $response = $this->getJson(route('getUserDetails', ['userId' => $user->id]));

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
                'user'    => [
                    'id'      => $user->id,
                    'team_id' => $team->id,
                ],
                'userTeam' => [
                    'id' => $team->id,
                ],
            ])
            ->assertJsonPath('formattedBirthday', '15.01.2000')
            ->assertJsonStructure([
                'success',
                'user',
                'userTeam',
                'userPrice',        // могут быть пустые массивы, это ок
                'scheduleUser',
                'formattedBirthday',
                'userFields',
                'userFieldValues',
                'allFields',
            ]);
    }
    /**
     * P1.4 — Конкретная команда — пользователи этой команды
     */
    /**
     * P1.4 — Конкретная команда — пользователи этой команды текущего партнёра.
     *
     * Проверяем:
     * 1) Ответ успешный, вернулась нужная команда.
     * 2) В usersTeam точно есть наши созданные юзеры.
     * 3) У всех usersTeam правильный partner_id и team_id.
     * 4) Наш userWithoutTeam присутствует в userWithoutTeam.
     */
    public function test_get_team_details_returns_only_users_of_specific_team_of_current_partner(): void
    {
        /** @var Team $team */
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
        ]);

        // Юзеры в команде текущего партнёра
        $usersInTeam = User::factory()->count(3)->create([
            'partner_id' => $this->partner->id,
            'team_id'    => $team->id,
            'is_enabled' => 1,
        ]);

        // Юзер этого же партнёра, но без команды
        $userWithoutTeam = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id'    => null,
            'is_enabled' => 1,
        ]);

        // Юзер другого партнёра (не должен попасть никуда)
        User::factory()->create(); // partner_id будет отличаться

        // Weekdays для команды
        /** @var Weekday $weekday */
        $weekday = Weekday::factory()->create();
        $team->weekdays()->attach($weekday->id);

        $response = $this->getJson(route('getTeamDetails', [
            'teamId'   => $team->id,
            'teamName' => $team->name,
        ]));

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
                'team'    => [
                    'id' => $team->id,
                ],
            ])
            ->assertJsonStructure([
                'success',
                'team',
                'teamWeekDayId',
                'usersTeam',
                'userWithoutTeam',
            ]);

        $json = $response->json();

        $returnedUsers = collect($json['usersTeam']);

        // 1) Наши юзеры должны быть среди usersTeam
        $returnedIds = $returnedUsers->pluck('id')->all();
        $expectedIds = $usersInTeam->pluck('id')->all();

        foreach ($expectedIds as $expectedId) {
            $this->assertContains(
                $expectedId,
                $returnedIds,
                "usersTeam должен содержать созданного нами юзера с id={$expectedId}"
            );
        }

        // 2) Все usersTeam должны принадлежать текущему партнёру и этой команде
        $returnedUsers->each(function (array $user) use ($team) {
            $this->assertEquals(
                $team->id,
                $user['team_id'],
                "Все usersTeam должны принадлежать команде {$team->id}"
            );
            $this->assertEquals(
                $this->partner->id,
                $user['partner_id'],
                "Все usersTeam должны принадлежать текущему партнёру {$this->partner->id}"
            );
        });

        // 3) userWithoutTeam — среди userWithoutTeam
        $withoutTeamReturnedIds = collect($json['userWithoutTeam'])->pluck('id')->all();

        $this->assertContains(
            $userWithoutTeam->id,
            $withoutTeamReturnedIds,
            'userWithoutTeam должен содержать созданного нами юзера без команды'
        );
    }    /**
     * P1.5 — teamName = all — все юзеры текущего партнёра
     */
    /**
     * P1.5 — teamName = all — все включённые юзеры текущего партнёра.
     *
     * Проверяем:
     * 1) Наши созданные юзеры партнёра присутствуют в usersTeam.
     * 2) Все usersTeam принадлежат текущему партнёру и имеют is_enabled = 1.
     */
    public function test_get_team_details_with_all_returns_all_enabled_users_of_current_partner(): void
    {
        // Юзеры текущего партнёра
        $usersPartner = User::factory()->count(4)->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 1,
        ]);

        // Отключенный юзер текущего партнёра (не должен попасть)
        User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => 0,
        ]);

        // Юзеры другого партнёра (не должны попасть)
        User::factory()->count(2)->create();

        $response = $this->getJson(route('getTeamDetails', [
            'teamName' => 'all',
        ]));

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'team',
                'teamWeekDayId',
                'usersTeam',
                'userWithoutTeam',
            ]);

        $json = $response->json();

        $returnedUsers = collect($json['usersTeam']);
        $returnedIds   = $returnedUsers->pluck('id')->all();

        // 1) Наши пользователи текущего партнёра должны быть среди usersTeam
        $expectedIds = $usersPartner->pluck('id')->all();

        foreach ($expectedIds as $expectedId) {
            $this->assertContains(
                $expectedId,
                $returnedIds,
                "При teamName=all в usersTeam должен быть юзер с id={$expectedId} (текущий партнёр)"
            );
        }

        // 2) Все usersTeam должны принадлежать текущему партнёру и быть включёнными
        $returnedUsers->each(function (array $user) {
            $this->assertEquals(
                $this->partner->id,
                $user['partner_id'],
                "Все usersTeam при teamName=all должны принадлежать текущему партнёру {$this->partner->id}"
            );
            $this->assertEquals(
                1,
                $user['is_enabled'],
                'Все usersTeam при teamName=all должны быть включёнными (is_enabled = 1)'
            );
        });
    }
    /**
     * P1.6 — teamName = withoutTeam — только юзеры без команды
     */
    /**
     * P1.6 — teamName = withoutTeam — только юзеры без команды текущего партнёра.
     *
     * Проверяем:
     * 1) Наши созданные юзеры без команды текущего партнёра присутствуют в usersTeam.
     * 2) Все usersTeam принадлежат текущему партнёру, не имеют команды и включены.
     */
    public function test_get_team_details_with_without_team_returns_only_users_without_team_of_current_partner(): void
    {
        // Юзеры без команды текущего партнёра
        $usersWithoutTeam = User::factory()->count(3)->create([
            'partner_id' => $this->partner->id,
            'team_id'    => null,
            'is_enabled' => 1,
        ]);

        // Юзеры с командой текущего партнёра (не должны попасть)
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
        ]);

        User::factory()->count(2)->create([
            'partner_id' => $this->partner->id,
            'team_id'    => $team->id,
            'is_enabled' => 1,
        ]);

        // Юзер другого партнёра без команды (не должен попасть)
        User::factory()->create([
            'partner_id' => Partner::factory()->create()->id,
            'team_id'    => null,
            'is_enabled' => 1,
        ]);

        $response = $this->getJson(route('getTeamDetails', [
            'teamName' => 'withoutTeam',
        ]));

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $json = $response->json();

        $returnedUsers = collect($json['usersTeam']);
        $returnedIds   = $returnedUsers->pluck('id')->all();

        // 1) Наши юзеры без команды текущего партнёра должны быть среди usersTeam
        $expectedIds = $usersWithoutTeam->pluck('id')->all();

        foreach ($expectedIds as $expectedId) {
            $this->assertContains(
                $expectedId,
                $returnedIds,
                "При teamName=withoutTeam в usersTeam должен быть юзер с id={$expectedId} (без команды, текущий партнёр)"
            );
        }

        // 2) Все usersTeam должны принадлежать текущему партнёру, быть без команды и включёнными
        $returnedUsers->each(function (array $user) {
            $this->assertEquals(
                $this->partner->id,
                $user['partner_id'],
                "Все usersTeam при teamName=withoutTeam должны принадлежать текущему партнёру {$this->partner->id}"
            );
            $this->assertNull(
                $user['team_id'],
                'Все usersTeam при teamName=withoutTeam должны быть без команды (team_id = null)'
            );
            $this->assertEquals(
                1,
                $user['is_enabled'],
                'Все usersTeam при teamName=withoutTeam должны быть включёнными (is_enabled = 1)'
            );
        });
    }
    /**
     * P2.1 — getUserDetails — пользователь не найден
     */
    public function test_get_user_details_returns_success_false_when_user_not_found(): void
    {
        $response = $this->getJson(route('getUserDetails', ['userId' => 999999]));

        $response
            ->assertOk()
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * P2.4 — getTeamDetails — команда не передана / не найдена
     *
     * 1) Без teamId и без спец-значений teamName → ожидаем success=false.
     * 2) С несуществующим teamId → ожидаем success=false.
     */
    public function test_get_team_details_returns_success_false_when_team_id_missing(): void
    {
        $response = $this->getJson(route('getTeamDetails', [
            'teamName' => 'some-team',
        ]));

        $response
            ->assertOk()
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_get_team_details_returns_success_false_when_team_not_found(): void
    {
        $response = $this->getJson(route('getTeamDetails', [
            'teamId'   => 999999,
            'teamName' => 'some-team',
        ]));

        $response
            ->assertOk()
            ->assertJson([
                'success' => false,
            ]);
    }
}