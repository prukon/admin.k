<?php

namespace Tests\Feature\Crm\Dashboard;

use App\Models\Partner;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Models\Weekday;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\Crm\CrmTestCase;

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

        // 2) Все usersTeam должны принадлежать текущему партнёру
        $returnedUsers->each(function (array $user) {
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
     * P1.5 — teamName = all — все включённые ученики текущего партнёра (роль user).
     *
     * Проверяем:
     * 1) Наши созданные ученики партнёра присутствуют в usersTeam.
     * 2) Все usersTeam принадлежат текущему партнёру, is_enabled = 1, role_id роли user.
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

        $studentRoleId = $this->roleId('user');

        // 2) Все usersTeam должны принадлежать текущему партнёру, быть включёнными учениками
        $returnedUsers->each(function (array $user) use ($studentRoleId) {
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
            $this->assertEquals(
                $studentRoleId,
                $user['role_id'],
                'Все usersTeam при teamName=all должны иметь системную роль user'
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
            $this->assertDatabaseMissing('team_user', [
                'user_id' => $user['id'],
                'partner_id' => $this->partner->id,
            ]);
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

    public function test_cabinet_fio_select_lists_only_enabled_system_role_users(): void
    {
        $this->withoutVite();
        config(['broadcasting.default' => 'null']);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->roleId('user'),
            'is_enabled' => 1,
            'lastname'   => 'Кабинетселектов',
            'name'       => 'Иван',
        ]);
        $disabled = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->roleId('user'),
            'is_enabled' => 0,
            'lastname'   => 'Отключеннов',
            'name'       => 'Пётр',
        ]);
        $admin = $this->createUserWithRole('admin', $this->partner, [
            'is_enabled' => 1,
            'lastname'   => 'Админов',
            'name'       => 'Сергей',
        ]);
        $trainer = $this->createUserWithRole('trainer', $this->partner, [
            'is_enabled' => 1,
            'lastname'   => 'Тренеров',
            'name'       => 'Олег',
        ]);
        $custom = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->makeCustomRole()->id,
            'is_enabled' => 1,
            'lastname'   => 'Кастомов',
            'name'       => 'Игорь',
        ]);

        $this->asAdmin();

        $html = (string) $this->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('id="single-select-user"', $html);
        $this->assertStringContainsString('data-user-id="'.$student->id.'"', $html);
        $this->assertStringNotContainsString('data-user-id="'.$disabled->id.'"', $html);
        $this->assertStringNotContainsString('data-user-id="'.$admin->id.'"', $html);
        $this->assertStringNotContainsString('data-user-id="'.$trainer->id.'"', $html);
        $this->assertStringNotContainsString('data-user-id="'.$custom->id.'"', $html);
        $this->assertStringNotContainsString('data-user-id="'.$this->user->id.'"', $html);
    }

    public function test_get_user_details_returns_success_false_for_non_students_and_disabled(): void
    {
        $admin = $this->createUserWithRole('admin', $this->partner, ['is_enabled' => 1]);
        $trainer = $this->createUserWithRole('trainer', $this->partner, ['is_enabled' => 1]);
        $disabled = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->roleId('user'),
            'is_enabled' => 0,
        ]);
        $custom = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->makeCustomRole()->id,
            'is_enabled' => 1,
        ]);

        foreach ([$admin, $trainer, $disabled, $custom] as $target) {
            $this->getJson(route('getUserDetails', ['userId' => $target->id]))
                ->assertOk()
                ->assertJson(['success' => false]);
        }
    }

    public function test_get_team_details_all_excludes_staff_disabled_and_custom_roles(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->roleId('user'),
            'is_enabled' => 1,
        ]);
        $disabled = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->roleId('user'),
            'is_enabled' => 0,
        ]);
        $admin = $this->createUserWithRole('admin', $this->partner, ['is_enabled' => 1]);
        $trainer = $this->createUserWithRole('trainer', $this->partner, ['is_enabled' => 1]);
        $custom = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->makeCustomRole()->id,
            'is_enabled' => 1,
        ]);

        $ids = collect($this->getJson(route('getTeamDetails', ['teamName' => 'all']))
            ->assertOk()
            ->assertJson(['success' => true])
            ->json('usersTeam'))->pluck('id')->all();

        $this->assertContains($student->id, $ids);
        $this->assertNotContains($disabled->id, $ids);
        $this->assertNotContains($admin->id, $ids);
        $this->assertNotContains($trainer->id, $ids);
        $this->assertNotContains($custom->id, $ids);
    }

    public function test_get_team_details_without_team_excludes_staff_without_groups(): void
    {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id'    => null,
            'role_id'    => $this->roleId('user'),
            'is_enabled' => 1,
        ]);
        $admin = $this->createUserWithRole('admin', $this->partner, [
            'team_id'    => null,
            'is_enabled' => 1,
        ]);
        $trainer = $this->createUserWithRole('trainer', $this->partner, [
            'team_id'    => null,
            'is_enabled' => 1,
        ]);

        $ids = collect($this->getJson(route('getTeamDetails', ['teamName' => 'withoutTeam']))
            ->assertOk()
            ->assertJson(['success' => true])
            ->json('usersTeam'))->pluck('id')->all();

        $this->assertContains($student->id, $ids);
        $this->assertNotContains($admin->id, $ids);
        $this->assertNotContains($trainer->id, $ids);
    }

    public function test_get_team_details_specific_team_excludes_non_student_in_pivot(): void
    {
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
        ]);
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id'    => $team->id,
            'role_id'    => $this->roleId('user'),
            'is_enabled' => 1,
        ]);
        $trainer = $this->createUserWithRole('trainer', $this->partner, [
            'is_enabled' => 1,
        ]);
        DB::table('team_user')->insert([
            'partner_id' => $this->partner->id,
            'team_id'    => $team->id,
            'user_id'    => $trainer->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ids = collect($this->getJson(route('getTeamDetails', [
            'teamId'   => $team->id,
            'teamName' => $team->title,
        ]))
            ->assertOk()
            ->assertJson(['success' => true])
            ->json('usersTeam'))->pluck('id')->all();

        $this->assertContains($student->id, $ids);
        $this->assertNotContains($trainer->id, $ids);
    }

    private function makeCustomRole(): Role
    {
        $role = Role::query()->create([
            'name'       => 'cabinet_staff_'.Str::lower(Str::random(8)),
            'label'      => 'Сотрудник кабинета',
            'is_sistem'  => 0,
            'is_visible' => 1,
            'order_by'   => 80,
        ]);

        return $role;
    }
}