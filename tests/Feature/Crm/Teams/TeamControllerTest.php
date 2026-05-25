<?php

namespace Tests\Feature\Crm\Teams;

use App\Http\Middleware\SetPartner;
use App\Models\MyLog;
use App\Models\Partner;
use App\Models\Role;
use App\Models\Team;
use App\Models\TrainerProfile;
use App\Models\User;
use App\Models\Weekday;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

class TeamControllerTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Приоритет определения партнёра через current_partner
        session(['current_partner' => $this->partner->id]);

        // Реальные права: доступ к groups.view есть у admin
        $this->asAdmin();
    }

    /* ============================================================
     * A. Доступ и базовый index
     * ============================================================
     */

    public function test_index_available_with_groups_view_and_current_partner()
    {
        $response = $this->get('/admin/teams');

        $response->assertStatus(200);
        $response->assertViewIs('admin.team');
        $response->assertViewHas('weekdays');
    }

    public function test_index_forbidden_without_groups_view_permission()
    {
        $actor = $this->createUserWithoutPermission('groups.view', $this->partner);
        $this->actingAs($actor);

        $response = $this->get('/admin/teams');

        $response->assertStatus(403);
    }

    /* ============================================================
     * B. DataTables: /admin/teams/data
     * ============================================================
     */

    public function test_data_returns_only_teams_of_current_partner()
    {
        /** @var Partner $otherPartner */
        $otherPartner = Partner::factory()->create();

        // Команды текущего партнёра
        $team1 = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Группа A',
        ]);
        $team2 = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Группа B',
        ]);

        // Команда другого партнёра
        Team::factory()->create([
            'partner_id' => $otherPartner->id,
            'title'      => 'Чужая группа',
        ]);

        $response = $this->get('/admin/teams/data');

        $response->assertStatus(200);
        $json = $response->json();

        // В ответе должны быть только команды текущего партнёра
        $this->assertEquals(2, $json['recordsTotal']);
        $this->assertEquals(2, $json['recordsFiltered']);

        $ids = collect($json['data'])->pluck('id')->all();
        $this->assertContains($team1->id, $ids);
        $this->assertContains($team2->id, $ids);
        $this->assertNotContains('Чужая группа', collect($json['data'])->pluck('title')->all());
    }

    public function test_data_datatables_search_finds_team_by_title(): void
    {
        $target = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'DtTeamsSearchUnique',
        ]);

        Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Другая группа поиск',
        ]);

        $json = $this->getJson('/admin/teams/data?search[value]=DtTeamsSearchUnique')
            ->assertOk()
            ->json();

        $this->assertSame(1, $json['recordsFiltered']);
        $this->assertSame($target->id, $json['data'][0]['id']);
    }

    public function test_data_panel_title_takes_precedence_over_datatables_search(): void
    {
        $byPanel = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'ПриоритетПанелиГруппа',
        ]);

        Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'ПриоритетПоискаГруппа',
        ]);

        $json = $this->getJson(
            '/admin/teams/data?title=ПриоритетПанелиГруппа&search[value]=ПриоритетПоискаГруппа'
        )
            ->assertOk()
            ->json();

        $titles = collect($json['data'])->pluck('title')->all();
        $this->assertContains('ПриоритетПанелиГруппа', $titles);
        $this->assertNotContains('ПриоритетПоискаГруппа', $titles);
        $this->assertSame(1, $json['recordsFiltered']);
    }

    public function test_data_datatables_search_finds_team_by_trainer_name(): void
    {
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Группа для поиска тренера',
        ]);

        $profile = $this->makeTrainerProfileForFilter('УникальныйПоиск', 'Тренерович');

        DB::table('team_trainer')->insert([
            'partner_id'         => $this->partner->id,
            'team_id'            => $team->id,
            'trainer_profile_id' => $profile->id,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Без нужного тренера',
        ]);

        $json = $this->getJson('/admin/teams/data?search[value]=УникальныйПоиск')
            ->assertOk()
            ->json();

        $titles = collect($json['data'])->pluck('title')->all();
        $this->assertContains('Группа для поиска тренера', $titles);
        $this->assertNotContains('Без нужного тренера', $titles);
    }

    public function test_data_filters_by_title_within_current_partner()
    {
        Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Младшая группа',
        ]);

        Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Старшая группа',
        ]);

        // У другого партнёра тоже "Младшая группа" — не должна попасть
        $otherPartner = Partner::factory()->create();
        Team::factory()->create([
            'partner_id' => $otherPartner->id,
            'title'      => 'Младшая группа',
        ]);

        $response = $this->get('/admin/teams/data?title=Младшая');

        $response->assertStatus(200);
        $json = $response->json();

        $this->assertEquals(1, $json['recordsFiltered']);
        $titles = collect($json['data'])->pluck('title')->all();
        $this->assertEquals(['Младшая группа'], $titles);
    }

    public function test_data_filters_by_status_active_and_inactive()
    {
        $activeTeam = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Активная',
            'is_enabled' => 1,
        ]);

        $inactiveTeam = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Неактивная',
            'is_enabled' => 0,
        ]);

        // active
        $responseActive = $this->get('/admin/teams/data?status=active');
        $jsonActive = $responseActive->json();

        $titlesActive = collect($jsonActive['data'])->pluck('title')->all();
        $this->assertContains('Активная', $titlesActive);
        $this->assertNotContains('Неактивная', $titlesActive);

        // inactive
        $responseInactive = $this->get('/admin/teams/data?status=inactive');
        $jsonInactive = $responseInactive->json();

        $titlesInactive = collect($jsonInactive['data'])->pluck('title')->all();
        $this->assertContains('Неактивная', $titlesInactive);
        $this->assertNotContains('Активная', $titlesInactive);
    }

    public function test_data_filters_by_trainer_profile_id(): void
    {
        $withTrainer = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'С тренером фильтр',
        ]);
        $withoutTrainer = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Без тренера фильтр',
        ]);

        $profile = $this->makeTrainerProfileForFilter('Иван', 'Тренеров');

        DB::table('team_trainer')->insert([
            'partner_id'         => $this->partner->id,
            'team_id'            => $withTrainer->id,
            'trainer_profile_id' => $profile->id,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        $json = $this->get('/admin/teams/data?trainer_profile_id=' . $profile->id)
            ->assertOk()
            ->json();

        $titles = collect($json['data'])->pluck('title')->all();
        $this->assertContains('С тренером фильтр', $titles);
        $this->assertNotContains('Без тренера фильтр', $titles);
    }

    public function test_data_filters_by_trainer_none(): void
    {
        $withTrainer = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Есть тренер none',
        ]);
        $withoutTrainer = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Нет тренера none',
        ]);

        $profile = $this->makeTrainerProfileForFilter('Пётр', 'Безгруппов');

        DB::table('team_trainer')->insert([
            'partner_id'         => $this->partner->id,
            'team_id'            => $withTrainer->id,
            'trainer_profile_id' => $profile->id,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        $json = $this->get('/admin/teams/data?trainer_profile_id=none')
            ->assertOk()
            ->json();

        $titles = collect($json['data'])->pluck('title')->all();
        $this->assertContains('Нет тренера none', $titles);
        $this->assertNotContains('Есть тренер none', $titles);
    }

    public function test_data_paginates_results()
    {
        // Создаем 5 команд текущего партнёра
        $teams = Team::factory()->count(5)->create([
            'partner_id' => $this->partner->id,
        ])->sortBy('order_by')->values();

        // Первая страница
        $responsePage1 = $this->get('/admin/teams/data?start=0&length=2');
        $jsonPage1 = $responsePage1->json();
        $this->assertCount(2, $jsonPage1['data']);

        // Вторая страница
        $responsePage2 = $this->get('/admin/teams/data?start=2&length=2');
        $jsonPage2 = $responsePage2->json();
        $this->assertCount(2, $jsonPage2['data']);

        // Не должно быть пересечения по id между первой и второй страницами
        $ids1 = collect($jsonPage1['data'])->pluck('id')->all();
        $ids2 = collect($jsonPage2['data'])->pluck('id')->all();

        $this->assertEmpty(array_intersect($ids1, $ids2));
    }

    public function test_data_contains_weekdays_label_from_related_weekdays()
    {
        // Берём несколько дней недели
        $weekdays = Weekday::take(3)->pluck('id', 'title');

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Группа с расписанием',
        ]);

        $team->weekdays()->sync($weekdays->values()->all());

        $response = $this->get('/admin/teams/data');
        $json = $response->json();

        $row = collect($json['data'])->firstWhere('id', $team->id);
        $this->assertNotNull($row);

        // Заголовки дней в лейбле
        $expectedParts = $weekdays->keys()->all(); // titles
        foreach ($expectedParts as $part) {
            $this->assertStringContainsString($part, $row['weekdays_label']);
        }
    }

    /* ============================================================
     * C. Store (создание группы)
     * ============================================================
     */

    public function test_store_ajax_creates_team_for_current_partner_and_logs()
    {
        $payload = [
            'title'      => 'Новая группа',
            'type'       => 'group',
            'default_duration_minutes' => 60,
            'order_by'   => 15,
            'is_enabled' => 1,
            'weekdays'   => Weekday::take(2)->pluck('id')->all(),
        ];

        $response = $this->postJson('/admin/teams', $payload, [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertStatus(200);
        $response->assertJsonFragment(['message' => 'Группа создана успешно']);

        /** @var Team $team */
        $team = Team::where('title', 'Новая группа')->first();
        $this->assertNotNull($team);
        $this->assertEquals($this->partner->id, $team->partner_id);
        $this->assertEquals('group', $team->type);
        $this->assertEquals(60, $team->default_duration_minutes);
        $this->assertEquals(15, $team->order_by);

        // Связанные дни недели
        $this->assertEqualsCanonicalizing(
            $payload['weekdays'],
            $team->weekdays()->pluck('id')->all()
        );

        // Лог
        $log = MyLog::where('target_type', Team::class)
            ->where('target_id', $team->id)
            ->where('action', 31)
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals(3, $log->type);
        $this->assertEquals($this->user->id, $log->author_id);
        $this->assertEquals($this->partner->id, $log->partner_id);
        $this->assertEquals($team->title, $log->target_label);
        $this->assertStringContainsString('Название:', $log->description);
        $this->assertStringContainsString('Тип:', $log->description);
        $this->assertStringContainsString('Длительность по умолчанию (мин):', $log->description);
        $this->assertStringContainsString('Дни недели:', $log->description);
        $this->assertStringContainsString('Сортировка:', $log->description);
        $this->assertStringContainsString('Активность:', $log->description);
    }

    public function test_store_non_ajax_redirects_and_creates_team()
    {
        $payload = [
            'title'      => 'Группа без ajax',
            'type'       => 'individual',
            'default_duration_minutes' => 45,
            'order_by'   => 20,
            'is_enabled' => 0,
            'weekdays'   => Weekday::take(1)->pluck('id')->all(),
        ];

        $response = $this->post('/admin/teams', $payload);

        // Достаточно убедиться, что есть редирект и команда создана
        $response->assertStatus(302);

        $team = Team::where('title', 'Группа без ajax')->first();
        $this->assertNotNull($team);
        $this->assertEquals($this->partner->id, $team->partner_id);
        $this->assertEquals('individual', $team->type);
        $this->assertEquals(45, $team->default_duration_minutes);
    }

    /* ============================================================
     * D. Edit / Update
     * ============================================================
     */

    public function test_edit_returns_team_data_for_current_partner()
    {
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Редактируемая группа',
            'type'       => 'individual',
            'default_duration_minutes' => 50,
        ]);

        $weekdays = Weekday::take(2)->pluck('id')->all();
        $team->weekdays()->sync($weekdays);

        $response = $this->get("/admin/team/{$team->id}/edit");

        $response->assertStatus(200);
        $json = $response->json();

        $this->assertEquals($team->id, $json['id']);
        $this->assertEquals($team->title, $json['title']);
        $this->assertEquals($team->type, $json['type']);
        $this->assertEquals($team->default_duration_minutes, $json['default_duration_minutes']);
        $this->assertEquals($team->order_by, $json['order_by']);
        $this->assertEquals($team->is_enabled, $json['is_enabled']);

        // team_weekdays содержит привязанные дни
        $this->assertEqualsCanonicalizing(
            $weekdays,
            collect($json['team_weekdays'])->pluck('id')->all()
        );

        // weekdays — полный список всех дней
        $this->assertGreaterThanOrEqual(
            count($weekdays),
            count($json['weekdays'])
        );
    }

    public function test_edit_returns_404_for_team_of_another_partner()
    {
        $otherPartner = Partner::factory()->create();

        $foreignTeam = Team::factory()->create([
            'partner_id' => $otherPartner->id,
        ]);

        $response = $this->get("/admin/team/{$foreignTeam->id}/edit");

        $response->assertStatus(404);
    }

    public function test_update_successfully_updates_team_of_current_partner_and_logs_changes()
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $this->user->role_id,
            'permission_id' => $this->permissionId('schedule.view'),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Старая группа',
            'type'       => 'group',
            'default_duration_minutes' => 60,
            'order_by'   => 10,
            'is_enabled' => 1,
        ]);

        $oldWeekdays = Weekday::take(2)->pluck('id')->all();
        $team->weekdays()->sync($oldWeekdays);

        $newWeekdays = Weekday::skip(2)->take(2)->pluck('id')->all();

        $payload = [
            'title'      => 'Новая группа',
            'type'       => 'individual',
            'default_duration_minutes' => 55,
            'order_by'   => 25,
            'is_enabled' => 0,
            'weekdays'   => $newWeekdays,
        ];

        $response = $this->patchJson("/admin/team/{$team->id}", $payload);

        $response->assertStatus(200);
        $response->assertJsonFragment(['message' => 'Группа успешно обновлена']);

        $team->refresh();
        $this->assertEquals('Новая группа', $team->title);
        $this->assertEquals('individual', $team->type);
        $this->assertEquals(55, $team->default_duration_minutes);
        $this->assertEquals(25, $team->order_by);
        $this->assertEquals(0, $team->is_enabled);
        $this->assertEqualsCanonicalizing(
            $newWeekdays,
            $team->weekdays()->pluck('id')->all()
        );

        $log = MyLog::where('target_type', Team::class)
            ->where('target_id', $team->id)
            ->where('action', 32)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertStringContainsString('Название:', $log->description);
        $this->assertStringContainsString('Тип:', $log->description);
        $this->assertStringContainsString('Длительность по умолчанию (мин):', $log->description);
        $this->assertStringContainsString('Дни недели:', $log->description);
        $this->assertStringContainsString('Сортировка:', $log->description);
        $this->assertStringContainsString('Активность:', $log->description);
    }

    public function test_store_returns_422_when_type_missing(): void
    {
        $payload = [
            'title' => 'Без типа',
            'order_by' => 10,
            'is_enabled' => 1,
            'weekdays' => [],
        ];

        $this->postJson('/admin/teams', $payload, [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    public function test_update_returns_422_when_type_invalid(): void
    {
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'type' => 'group',
            'default_duration_minutes' => 60,
        ]);

        $payload = [
            'title' => $team->title,
            'type' => 'wrong',
            'default_duration_minutes' => 60,
            'order_by' => $team->order_by,
            'is_enabled' => (int) $team->is_enabled,
            'weekdays' => [],
        ];

        $this->patchJson("/admin/team/{$team->id}", $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    public function test_update_returns_404_for_team_of_another_partner()
    {
        $otherPartner = Partner::factory()->create();
        $foreignTeam  = Team::factory()->create([
            'partner_id' => $otherPartner->id,
            'title'      => 'Чужая группа',
        ]);

        $payload = [
            'title'      => 'Попытка взлома',
            'type'       => 'group',
            'default_duration_minutes' => 60,
            'order_by'   => 99,
            'is_enabled' => 0,
            'weekdays'   => [],
        ];

        $response = $this->patchJson("/admin/team/{$foreignTeam->id}", $payload);

        $response->assertStatus(404);
        $response->assertJsonFragment([
            'error' => 'Команда не найдена или принадлежит другому партнёру',
        ]);

        $foreignTeam->refresh();
        $this->assertEquals('Чужая группа', $foreignTeam->title);
    }

    public function test_update_does_not_create_log_if_nothing_changed()
    {
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Без изменений',
            'type'       => 'group',
            'default_duration_minutes' => 60,
            'order_by'   => 5,
            'is_enabled' => 1,
        ]);

        $weekdays = Weekday::take(2)->pluck('id')->all();
        $team->weekdays()->sync($weekdays);

        $logsBefore = MyLog::where('target_type', Team::class)
            ->where('target_id', $team->id)
            ->where('action', 32)
            ->count();

        $payload = [
            'title'      => $team->title,
            'type'       => $team->type,
            'default_duration_minutes' => $team->default_duration_minutes,
            'order_by'   => $team->order_by,
            'is_enabled' => $team->is_enabled,
            'weekdays'   => $weekdays,
        ];

        $response = $this->patchJson("/admin/team/{$team->id}", $payload);

        $response->assertStatus(200);

        $logsAfter = MyLog::where('target_type', Team::class)
            ->where('target_id', $team->id)
            ->where('action', 32)
            ->count();

        $this->assertEquals($logsBefore, $logsAfter);
    }

    /* ============================================================
     * E. Delete
     * ============================================================
     */

    public function test_delete_soft_deletes_team_and_nulls_users_and_logs()
    {
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Удаляемая группа',
        ]);

        $userWithTeam = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id'    => $team->id,
        ]);

        $response = $this->deleteJson("/admin/team/{$team->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'message' => 'Группа и её связь с пользователями успешно помечены как удалённые',
        ]);

        // Пользователь больше не привязан к команде
        $this->assertDatabaseHas('users', [
            'id'      => $userWithTeam->id,
            'team_id' => null,
        ]);

        // Soft delete
        $this->assertSoftDeleted('teams', ['id' => $team->id]);

        // Лог
        $log = MyLog::where('target_type', Team::class)
            ->where('target_id', $team->id)
            ->where('action', 33)
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals(3, $log->type);
        $this->assertStringContainsString('Группа удалена:', $log->description);
    }

    public function test_delete_forbidden_for_team_of_another_partner()
    {
        $otherPartner = Partner::factory()->create();
        $foreignTeam  = Team::factory()->create([
            'partner_id' => $otherPartner->id,
        ]);

        $response = $this->deleteJson("/admin/team/{$foreignTeam->id}");

        $response->assertStatus(403);
        $response->assertJsonFragment([
            'message' => 'Доступ запрещён.',
        ]);


        // Группа не должна быть помечена удалённой
        $this->assertDatabaseHas('teams', [
            'id'         => $foreignTeam->id,
            'deleted_at' => null,
        ]);
    }

    /* ============================================================
     * F. Страховочный кейс: партнёр не выбран → отваливаемся на middleware SetPartner
     * ============================================================
     */

    public function test_partner_missing_is_blocked_by_set_partner_middleware(): void
    {
        // Убираем current_partner и partner_id у пользователя
        session()->forget('current_partner');
        $this->user->partner_id = null;
        $this->user->save();

        // Права/роль тут не важны: SetPartner отработает раньше.
        $this->asSuperadmin();

        $res = $this->from('/admin')->get('/admin/teams');

        // По SetPartner: redirect()->back() + session errors
        $res->assertStatus(302);
        $res->assertSessionHasErrors(['partner']);
    }

    private function makeTrainerProfileForFilter(string $name, string $lastname): TrainerProfile
    {
        $trainerRoleId = (int) Role::query()->where('name', 'trainer')->value('id');

        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $trainerRoleId,
            'name'       => $name,
            'lastname'   => $lastname,
            'email'      => strtolower($name) . '-' . uniqid() . '@example.test',
        ]);

        return TrainerProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'user_id'    => $user->id,
        ]);
    }
}