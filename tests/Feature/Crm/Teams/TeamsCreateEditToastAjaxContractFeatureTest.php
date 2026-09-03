<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Teams;

use App\Models\Team;

/**
 * AJAX-контракт create/edit/delete группы: JSON 200 с message для toast, 422 errors по полям.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class TeamsCreateEditToastAjaxContractFeatureTest extends TeamsCreateEditToastTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsGroupsViewer();
    }

    public function test_ajax_store_returns_message_that_toast_shows_and_team_payload(): void
    {
        $title = 'AJAX тост store '.uniqid('', true);

        $this->postJson(route('admin.team.store'), $this->teamPayload([
            'title' => $title,
        ]), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('message', 'Группа создана успешно')
            ->assertJsonStructure([
                'message',
                'team' => ['id', 'title'],
            ])
            ->assertJsonPath('team.title', $title);

        $this->assertDatabaseHas('teams', [
            'partner_id' => $this->partner->id,
            'title'      => $title,
        ]);
    }

    public function test_ajax_store_without_title_returns_422_field_error_not_toast_payload(): void
    {
        $this->postJson(route('admin.team.store'), $this->teamPayload([
            'title' => '',
        ]), $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title'])
            ->assertJsonPath('errors.title.0', 'Введите название');

        $this->assertDatabaseMissing('teams', [
            'partner_id' => $this->partner->id,
            'title'      => '',
        ]);
    }

    public function test_ajax_store_duplicate_title_returns_422_on_title_field(): void
    {
        $title = 'Дубль тост '.uniqid('', true);
        $this->makeTeam(['title' => $title]);

        $this->postJson(route('admin.team.store'), $this->teamPayload([
            'title' => $title,
        ]), $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title'])
            ->assertJsonPath(
                'errors.title.0',
                'Группа с таким названием уже существует у текущего партнёра'
            );

        $this->assertSame(1, Team::query()->where('partner_id', $this->partner->id)->where('title', $title)->count());
    }

    public function test_ajax_store_invalid_duration_returns_422_on_duration_field(): void
    {
        $title = 'Длительность тост '.uniqid('', true);

        $this->postJson(route('admin.team.store'), $this->teamPayload([
            'title'                    => $title,
            'default_duration_minutes' => 0,
        ]), $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['default_duration_minutes'])
            ->assertJsonPath('errors.default_duration_minutes.0', 'Длительность должна быть больше 0 минут');

        $this->assertDatabaseMissing('teams', [
            'partner_id' => $this->partner->id,
            'title'      => $title,
        ]);
    }

    public function test_ajax_update_returns_message_that_toast_shows(): void
    {
        $team = $this->makeTeam(['title' => 'До патча']);

        $this->patchJson(route('admin.team.update', $team->id), $this->teamPayload([
            'title' => 'После патча',
        ]), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('message', 'Группа успешно обновлена')
            ->assertJsonStructure(['message']);

        $this->assertSame('После патча', $team->fresh()->title);
    }

    public function test_ajax_update_without_title_returns_422_on_title_field(): void
    {
        $team = $this->makeTeam(['title' => 'Имя останется']);

        $this->patchJson(route('admin.team.update', $team->id), $this->teamPayload([
            'title' => '',
        ]), $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title'])
            ->assertJsonPath('errors.title.0', 'Введите название');

        $this->assertSame('Имя останется', $team->fresh()->title);
    }

    public function test_ajax_edit_returns_team_json_for_modal_not_empty_200(): void
    {
        $team = $this->makeTeam(['title' => 'Для модалки']);

        $this->getJson(route('admin.team.edit', $team->id), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('id', $team->id)
            ->assertJsonPath('title', 'Для модалки')
            ->assertJsonStructure(['id', 'title', 'is_enabled', 'order_by']);
    }

    public function test_ajax_delete_returns_server_message_while_ui_toast_uses_shorter_copy(): void
    {
        $team = $this->makeTeam(['title' => 'Удалить AJAX '.uniqid('', true)]);

        $this->deleteJson(route('admin.team.delete', $team), [], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Группа и её связь с пользователями успешно помечены как удалённые'
            );

        $this->assertSoftDeleted('teams', ['id' => $team->id]);
    }

    public function test_ajax_update_foreign_partner_team_is_not_found(): void
    {
        $foreign = Team::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'title'      => 'Чужая группа',
        ]);

        $this->patchJson(route('admin.team.update', $foreign->id), $this->teamPayload([
            'title' => 'Взлом',
        ]), $this->ajaxHeaders())->assertNotFound();

        $this->assertSame('Чужая группа', $foreign->fresh()->title);
    }

    public function test_ajax_delete_foreign_partner_team_is_forbidden(): void
    {
        $foreign = Team::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'title'      => 'Чужая на удаление',
        ]);

        $this->deleteJson(route('admin.team.delete', $foreign), [], $this->ajaxHeaders())
            ->assertForbidden();

        $this->assertDatabaseHas('teams', [
            'id'         => $foreign->id,
            'deleted_at' => null,
        ]);
    }
}
