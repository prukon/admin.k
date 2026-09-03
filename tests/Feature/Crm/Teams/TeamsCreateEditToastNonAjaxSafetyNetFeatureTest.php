<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Teams;

/**
 * Backend safety-net: native POST группы → 302 на /admin/teams + запись в БД.
 * PATCH/DELETE без X-Requested-With исторически отдают JSON 200 с message (не пустой 200).
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class TeamsCreateEditToastNonAjaxSafetyNetFeatureTest extends TeamsCreateEditToastTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsGroupsViewer();
    }

    public function test_store_non_ajax_redirects_and_creates_group(): void
    {
        $title = 'Non-AJAX store '.uniqid('', true);

        $response = $this->from(route('admin.team.index'))
            ->post(route('admin.team.store'), $this->teamPayload([
                'title' => $title,
            ]));

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode(), 'Create без AJAX не должен быть пустым 200');
        $response->assertRedirect(route('admin.team.index'));

        $this->assertDatabaseHas('teams', [
            'partner_id' => $this->partner->id,
            'title'      => $title,
        ]);
    }

    public function test_store_non_ajax_validation_redirects_back_with_title_error(): void
    {
        $this->from(route('admin.team.index'))
            ->post(route('admin.team.store'), $this->teamPayload([
                'title' => '',
            ]))
            ->assertStatus(302)
            ->assertSessionHasErrors(['title']);

        $this->assertDatabaseMissing('teams', [
            'partner_id' => $this->partner->id,
            'title'      => '',
        ]);
    }

    public function test_update_non_ajax_returns_json_message_and_updates_group(): void
    {
        $team = $this->makeTeam(['title' => 'До native PATCH']);

        $response = $this->from(route('admin.team.index'))
            ->patch(route('admin.team.update', $team->id), $this->teamPayload([
                'title' => 'После native PATCH',
            ]));

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame('', trim((string) $response->getContent()), 'PATCH без AJAX не должен быть пустым 200');
        $response->assertOk()->assertJsonPath('message', 'Группа успешно обновлена');
        $this->assertSame('После native PATCH', $team->fresh()->title);
    }

    public function test_update_non_ajax_validation_redirects_back_with_title_error(): void
    {
        $team = $this->makeTeam(['title' => 'До ошибки PATCH']);

        $this->from(route('admin.team.index'))
            ->patch(route('admin.team.update', $team->id), $this->teamPayload([
                'title' => '',
            ]))
            ->assertStatus(302)
            ->assertSessionHasErrors(['title']);

        $this->assertSame('До ошибки PATCH', $team->fresh()->title);
    }

    public function test_delete_non_ajax_returns_json_message_and_soft_deletes_group(): void
    {
        $team = $this->makeTeam(['title' => 'Native DELETE '.uniqid('', true)]);

        $response = $this->from(route('admin.team.index'))
            ->delete(route('admin.team.delete', $team));

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame('', trim((string) $response->getContent()), 'DELETE без AJAX не должен быть пустым 200');
        $response->assertOk()->assertJsonPath(
            'message',
            'Группа и её связь с пользователями успешно помечены как удалённые'
        );
        $this->assertSoftDeleted('teams', ['id' => $team->id]);
    }
}
