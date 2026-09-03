<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Teams;

use App\Models\Team;
use Illuminate\Support\Facades\Auth;

/**
 * Доступ к create/edit/delete группы, чей успех UI показывает toast без reload:
 * гость, без groups.view, со правом — не 500 и не пустой 200.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class TeamsCreateEditToastAccessFeatureTest extends TeamsCreateEditToastTestCase
{
    public function test_guest_cannot_open_teams_or_create_or_update_or_delete_group(): void
    {
        Auth::logout();
        $team = $this->makeTeam();

        $webPage = $this->get(route('admin.team.index'));
        $this->assertContains($webPage->getStatusCode(), [302, 401, 403]);
        $this->assertNotSame(500, $webPage->getStatusCode());
        $this->assertNotSame(200, $webPage->getStatusCode(), 'Гость не должен видеть /admin/teams');

        $jsonPage = $this->getJson(route('admin.team.index'));
        $this->assertContains($jsonPage->getStatusCode(), [302, 401, 403]);
        $this->assertNotSame(500, $jsonPage->getStatusCode());

        $guestTitle = 'Гость тост '.uniqid('', true);
        $store = $this->postJson(route('admin.team.store'), $this->teamPayload([
            'title' => $guestTitle,
        ]), $this->ajaxHeaders());
        $this->assertContains($store->getStatusCode(), [302, 401, 403]);
        $this->assertNotSame(200, $store->getStatusCode(), 'Гость не должен создать группу');
        $this->assertNotSame(500, $store->getStatusCode());
        $this->assertDatabaseMissing('teams', ['title' => $guestTitle]);

        $update = $this->patchJson(route('admin.team.update', $team->id), $this->teamPayload([
            'title' => 'Взлом тост',
        ]), $this->ajaxHeaders());
        $this->assertContains($update->getStatusCode(), [302, 401, 403]);
        $this->assertNotSame(200, $update->getStatusCode(), 'Гость не должен обновить группу');
        $this->assertNotSame(500, $update->getStatusCode());
        $this->assertSame('Группа для тоста', $team->fresh()->title);

        $delete = $this->deleteJson(route('admin.team.delete', $team), [], $this->ajaxHeaders());
        $this->assertContains($delete->getStatusCode(), [302, 401, 403]);
        $this->assertNotSame(200, $delete->getStatusCode(), 'Гость не должен удалить группу');
        $this->assertNotSame(500, $delete->getStatusCode());
        $this->assertDatabaseHas('teams', [
            'id'         => $team->id,
            'deleted_at' => null,
        ]);
    }

    public function test_manager_without_groups_view_gets_403_on_create_update_and_delete(): void
    {
        $actor = $this->createUserWithoutPermission('groups.view', $this->partner);
        $this->actingAs($actor);
        $team = $this->makeTeam();

        $this->get(route('admin.team.index'))->assertForbidden();
        $this->getJson(route('admin.team.index'))->assertForbidden();

        $deniedTitle = 'Запрет тост '.uniqid('', true);
        $this->postJson(route('admin.team.store'), $this->teamPayload([
            'title' => $deniedTitle,
        ]), $this->ajaxHeaders())->assertForbidden();

        $this->patchJson(route('admin.team.update', $team->id), $this->teamPayload([
            'title' => 'Запрет патч',
        ]), $this->ajaxHeaders())->assertForbidden();

        $this->deleteJson(route('admin.team.delete', $team), [], $this->ajaxHeaders())->assertForbidden();

        $this->assertSame('Группа для тоста', $team->fresh()->title);
        $this->assertDatabaseMissing('teams', [
            'partner_id' => $this->partner->id,
            'title'      => $deniedTitle,
        ]);
        $this->assertDatabaseHas('teams', [
            'id'         => $team->id,
            'deleted_at' => null,
        ]);
    }

    public function test_authorized_user_opens_teams_page_with_hidden_toast_and_can_mutate_group(): void
    {
        $this->actingAsGroupsViewer();

        $html = $this->get(route('admin.team.index'))
            ->assertOk()
            ->getContent();
        $this->assertNotSame('', trim($html));
        $this->assertStringContainsString('id="kidsMainToast"', $html);
        $this->assertStringContainsString('window.showToast', $html);
        $this->assertDoesNotMatchRegularExpression(
            '/id="kidsMainToast"[^>]*\bshow\b/',
            $html,
            'При первом открытии всплывайка не должна быть уже показана'
        );

        $title = 'Тост доступ '.uniqid('', true);
        $store = $this->postJson(route('admin.team.store'), $this->teamPayload([
            'title' => $title,
        ]), $this->ajaxHeaders());
        $this->assertNotSame(500, $store->getStatusCode());
        $store->assertOk();
        $this->assertNotSame('', trim((string) $store->getContent()));

        $id = (int) $store->json('team.id');
        $this->assertGreaterThan(0, $id);

        $update = $this->patchJson(route('admin.team.update', $id), $this->teamPayload([
            'title' => $title.' после',
        ]), $this->ajaxHeaders());
        $this->assertNotSame(500, $update->getStatusCode());
        $update->assertOk();
        $this->assertNotSame('', trim((string) $update->getContent()));

        $deleteTarget = $this->makeTeam(['title' => 'Удалить из доступа '.uniqid('', true)]);
        $delete = $this->deleteJson(route('admin.team.delete', $deleteTarget), [], $this->ajaxHeaders());
        $this->assertNotSame(500, $delete->getStatusCode());
        $delete->assertOk();
        $this->assertNotSame('', trim((string) $delete->getContent()));
        $this->assertTrue(Team::withTrashed()->findOrFail($deleteTarget->id)->trashed());
    }
}
