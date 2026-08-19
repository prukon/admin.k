<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Chat;

use App\Models\ChatThread;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * P1: гость / без groups.view / со правом — создание группы и чат API учебного треда.
 * Ни один вызов не 500 и не пустой бессмысленный 200.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ChatTeamGroupThreadFullAccessFeatureTest extends ChatTestCase
{
    use InteractsWithTeamGroupChats;

    protected function setUp(): void
    {
        parent::setUp();

        $this->asAdmin();
        $this->grantPermission($this->user, 'groups.view');
        $this->grantPermission($this->user, 'trainers.view');
        $this->grantPermission($this->user, 'users.view');
        $this->grantPermission($this->user, 'messages.view');
    }

    public function test_guest_is_redirected_from_team_store_and_no_chat_is_created(): void
    {
        Auth::logout();

        $html = $this->from(route('login'))->post(route('admin.team.store'), $this->teamStorePayload('Гость '.uniqid('', true)));
        $this->assertNotSame(500, $html->getStatusCode());
        $this->assertNotSame(200, $html->getStatusCode(), 'Гость не должен получить пустой 200');
        $this->assertTrue($html->isRedirect());
        $this->assertGuest();
        $this->assertNoTeamChatCreated();
    }

    public function test_guest_json_team_store_is_unauthorized_and_creates_no_chat(): void
    {
        Auth::logout();

        $json = $this->postJson(
            route('admin.team.store'),
            $this->teamStorePayload('ГостьJson '.uniqid('', true)),
            $this->teamChatAjaxHeaders()
        );
        $this->assertNotSame(500, $json->getStatusCode());
        $json->assertUnauthorized();
        $this->assertNoTeamChatCreated();
    }

    public function test_guest_cannot_open_team_chat_api(): void
    {
        $team = $this->storeTeamViaAjax('ГостьТред '.uniqid('', true));
        $thread = $this->teamThread($team);

        Auth::logout();

        $html = $this->get(route('chat.api.threads.show', $thread));
        $this->assertNotSame(500, $html->getStatusCode());
        $this->assertTrue($html->isRedirect());

        $json = $this->getJson(route('chat.api.threads.show', $thread));
        $this->assertNotSame(500, $json->getStatusCode());
        $json->assertUnauthorized();

        $index = $this->getJson(route('chat.api.threads.index'));
        $this->assertNotSame(500, $index->getStatusCode());
        $index->assertUnauthorized();
    }

    public function test_manager_without_groups_view_gets_403_and_no_chat_is_created(): void
    {
        $denied = $this->createUserWithoutPermission('groups.view', $this->partner);
        $this->actingInPartner($denied);

        $title = 'Запрет '.uniqid('', true);
        $response = $this->postJson(
            route('admin.team.store'),
            $this->teamStorePayload($title),
            $this->teamChatAjaxHeaders()
        );
        $this->assertNotSame(500, $response->getStatusCode());
        $response->assertForbidden();
        $this->assertNull(Team::query()->where('title', $title)->first());
        $this->assertNoTeamChatCreated();
    }

    public function test_manager_without_groups_view_cannot_rename_or_delete_team_chat_stays(): void
    {
        $team = $this->storeTeamViaAjax('ЧужойПатч '.uniqid('', true));
        $thread = $this->teamThread($team);
        $subject = $thread->subject;

        $denied = $this->createUserWithoutPermission('groups.view', $this->partner);
        $this->actingInPartner($denied);

        $patch = $this->patchJson(route('admin.team.update', $team->id), [
            'title' => 'Взлом '.uniqid('', true),
            'is_enabled' => 1,
        ]);
        $this->assertNotSame(500, $patch->getStatusCode());
        $patch->assertForbidden();

        $delete = $this->deleteJson(route('admin.team.delete', $team));
        $this->assertNotSame(500, $delete->getStatusCode());
        $delete->assertForbidden();

        $this->assertSame($subject, $thread->fresh()->subject);
        $this->assertNull($team->fresh()->deleted_at);
        $this->assertSame(1, ChatThread::query()->where('team_id', $team->id)->count());
    }

    public function test_admin_with_groups_view_can_create_team_and_open_its_chat(): void
    {
        $title = 'Доступ '.uniqid('', true);
        $store = $this->postJson(
            route('admin.team.store'),
            $this->teamStorePayload($title),
            $this->teamChatAjaxHeaders()
        );
        $this->assertNotSame(500, $store->getStatusCode());
        $store->assertOk();
        $this->assertNotSame('', trim((string) $store->getContent()));

        $team = Team::query()->where('title', $title)->firstOrFail();
        $thread = $this->teamThread($team);
        $this->assertUserInThread($this->user, $thread);

        $this->get(route('admin.team.index'))->assertOk();
        $this->getJson(route('chat.api.threads.show', $thread))
            ->assertOk()
            ->assertJsonPath('thread.is_group', true)
            ->assertJsonPath('thread.peer_id', null)
            ->assertJsonPath('thread.title', $title);
    }

    public function test_wrong_methods_on_teams_collection_are_not_server_errors(): void
    {
        foreach (['PUT', 'PATCH', 'DELETE'] as $method) {
            $response = $this->call($method, '/admin/teams', $this->teamStorePayload('Метод '.uniqid('', true)));
            $this->assertNotSame(500, $response->getStatusCode(), $method.' /admin/teams не 500');
            $this->assertSame(405, $response->getStatusCode(), $method.' /admin/teams должен быть 405');
        }
        $this->assertNoTeamChatCreated();
    }

    public function test_put_and_post_on_team_item_are_not_server_errors(): void
    {
        $team = $this->storeTeamViaAjax('МетодЭлемент '.uniqid('', true));

        $put = $this->put('/admin/team/'.$team->id, [
            'title' => 'Put '.uniqid('', true),
            'is_enabled' => 1,
        ]);
        $this->assertNotSame(500, $put->getStatusCode());
        $this->assertContains($put->getStatusCode(), [404, 405]);

        $post = $this->post('/admin/team/'.$team->id, [
            'title' => 'Post '.uniqid('', true),
            'is_enabled' => 1,
        ]);
        $this->assertNotSame(500, $post->getStatusCode());
        $this->assertContains($post->getStatusCode(), [404, 405]);

        $this->assertSame(1, ChatThread::query()->where('team_id', $team->id)->count());
    }

    public function test_student_without_messages_view_is_in_chat_but_gets_403_on_inbox(): void
    {
        $team = $this->storeTeamViaAjax('БезЧатаUI '.uniqid('', true));
        $denied = $this->makePeer('NoMsg_');
        \Illuminate\Support\Facades\DB::table('permission_role')
            ->where('partner_id', $this->partner->id)
            ->where('role_id', $denied->role_id)
            ->where('permission_id', $this->permissionId('messages.view'))
            ->delete();

        app(\App\Services\TeamUserSyncService::class)->attachTeamForStudent($denied, (int) $team->id);
        $this->assertUserInThread($denied, $this->teamThread($team));

        $this->actingInPartner($denied);
        $inbox = $this->getJson(route('chat.api.threads.index'));
        $this->assertNotSame(500, $inbox->getStatusCode());
        $inbox->assertForbidden();

        $show = $this->getJson(route('chat.api.threads.show', $this->teamThread($team)));
        $this->assertNotSame(500, $show->getStatusCode());
        $show->assertForbidden();
    }

    public function test_student_with_messages_view_sees_team_chat_classmate_does_not(): void
    {
        $team = $this->storeTeamViaAjax('Видит '.uniqid('', true));
        $student = $this->makePeer('SeeStu_');
        $outsider = $this->makePeer('OutStu_');
        app(\App\Services\TeamUserSyncService::class)->attachTeamForStudent($student, (int) $team->id);

        $thread = $this->teamThread($team);
        $row = $this->inboxRowFor($student, (int) $thread->id);
        $this->assertNotNull($row);
        $this->assertTrue((bool) $row['is_group']);
        $this->assertNull($row['peer_id']);

        $this->actingInPartner($outsider);
        $this->grantPermission($outsider, 'messages.view');
        $list = collect($this->getJson(route('chat.api.threads.index'))->assertOk()->json('threads'));
        $this->assertNull($list->firstWhere('id', $thread->id));

        $show = $this->getJson(route('chat.api.threads.show', $thread));
        $this->assertNotSame(500, $show->getStatusCode());
        $show->assertForbidden();
    }

    public function test_foreign_partner_does_not_see_our_team_chat(): void
    {
        $team = $this->storeTeamViaAjax('Изоляция '.uniqid('', true));
        $thread = $this->teamThread($team);

        $this->actingInPartner($this->foreignUser, $this->foreignPartner);
        $this->grantPermission($this->foreignUser, 'messages.view', (int) $this->foreignPartner->id);

        $list = collect($this->getJson(route('chat.api.threads.index'))->assertOk()->json('threads'));
        $this->assertNull($list->firstWhere('id', $thread->id));

        $show = $this->getJson(route('chat.api.threads.show', $thread));
        $this->assertNotSame(500, $show->getStatusCode());
        $show->assertForbidden();
    }
}
