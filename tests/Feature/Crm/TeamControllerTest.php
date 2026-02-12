<?php

namespace Tests\Feature\Crm;

use App\Http\Middleware\SetPartner;
use App\Models\MyLog;
use App\Models\Partner;
use App\Models\Team;
use App\Models\User;
use App\Models\Weekday;
use Illuminate\Support\Facades\Gate;

class TeamControllerTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Разрешаем доступ к роутам с middleware('can:groups-view') по умолчанию
        Gate::define('groups-view', fn ($user = null) => true);

        // Приоритет определения партнёра через current_partner
        session(['current_partner' => $this->partner->id]);
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
        // Перепределяем ability на запрет
        Gate::define('groups-view', fn ($user = null) => false);

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
        $this->assertStringContainsString('Дни недели:', $log->description);
        $this->assertStringContainsString('Сортировка:', $log->description);
        $this->assertStringContainsString('Активность:', $log->description);
    }

    public function test_store_non_ajax_redirects_and_creates_team()
    {
        $payload = [
            'title'      => 'Группа без ajax',
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
        ]);

        $weekdays = Weekday::take(2)->pluck('id')->all();
        $team->weekdays()->sync($weekdays);

        $response = $this->get("/admin/team/{$team->id}/edit");

        $response->assertStatus(200);
        $json = $response->json();

        $this->assertEquals($team->id, $json['id']);
        $this->assertEquals($team->title, $json['title']);
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
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Старая группа',
            'order_by'   => 10,
            'is_enabled' => 1,
        ]);

        $oldWeekdays = Weekday::take(2)->pluck('id')->all();
        $team->weekdays()->sync($oldWeekdays);

        $newWeekdays = Weekday::skip(2)->take(2)->pluck('id')->all();

        $payload = [
            'title'      => 'Новая группа',
            'order_by'   => 25,
            'is_enabled' => 0,
            'weekdays'   => $newWeekdays,
        ];

        $response = $this->patchJson("/admin/team/{$team->id}", $payload);

        $response->assertStatus(200);
        $response->assertJsonFragment(['message' => 'Группа успешно обновлена']);

        $team->refresh();
        $this->assertEquals('Новая группа', $team->title);
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
        $this->assertStringContainsString('Дни недели:', $log->description);
        $this->assertStringContainsString('Сортировка:', $log->description);
        $this->assertStringContainsString('Активность:', $log->description);
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
     * F. Страховочный кейс: requirePartnerId без current_partner
     * ============================================================
     */

    public function test_require_partner_id_aborts_with_400_when_partner_is_missing()
    {
        // Middleware SetPartner нам не нужен в этом кейсе
        $this->withoutMiddleware(SetPartner::class);

        // Убираем current_partner и partner_id у пользователя
        session()->forget('current_partner');

        $this->user->partner_id = null;
        $this->user->save();

        $response = $this->get('/admin/teams');

        $response->assertStatus(400);
        $this->assertStringContainsString(
            'Текущий партнёр не определён',
            (string) $response->getContent()
        );
    }
}