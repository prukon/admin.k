<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Chat;

use App\Models\ChatThread;

/**
 * P1: JSON-контракт удаления чата — 200 ok+message, 422 errors.thread, 403.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ChatThreadDeleteAjaxContractFeatureTest extends ChatTestCase
{
    use InteractsWithTeamGroupChats;

    public function test_ajax_delete_returns_ok_message_and_thread_id(): void
    {
        $peer = $this->makePeer('DelAjax_');
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);
        $this->grantPermission($this->user, 'messages.threads.delete');

        $this->deleteJson(route('chat.api.threads.destroy', $thread))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('message', 'Чат удалён.')
            ->assertJsonPath('thread_id', $thread->id)
            ->assertJsonStructure(['ok', 'message', 'thread_id']);
    }

    public function test_ajax_team_chat_returns_thread_field_error(): void
    {
        $admin = $this->createUserWithRole('admin');
        $this->actingInPartner($admin);
        $this->grantPermission($admin, 'groups.view');
        $this->grantPermission($admin, 'messages.threads.delete');
        $team = $this->storeTeamViaAjax('DelAjaxTeam_'.uniqid('', true));
        $thread = $this->teamThread($team);

        $this->deleteJson(route('chat.api.threads.destroy', $thread))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['thread'])
            ->assertJsonPath('errors.thread.0', 'Нельзя удалить чат учебной группы.')
            ->assertJsonMissingPath('ok');
        $this->assertNotNull(ChatThread::query()->find($thread->id));
    }

    public function test_ajax_without_permission_is_403_json(): void
    {
        $peer = $this->makePeer('DelAjax403_');
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);

        $this->deleteJson(route('chat.api.threads.destroy', $thread))
            ->assertForbidden();
        $this->assertNotNull(ChatThread::query()->find($thread->id));
    }

    public function test_show_payload_includes_team_id_null_for_private(): void
    {
        $peer = $this->makePeer('DelAjaxShow_');
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);

        $this->getJson(route('chat.api.threads.show', $thread))
            ->assertOk()
            ->assertJsonPath('thread.team_id', null)
            ->assertJsonPath('thread.is_group', false);
    }

    public function test_open_team_chat_payload_includes_team_id_so_client_hides_trash(): void
    {
        $admin = $this->createUserWithRole('admin');
        $this->actingInPartner($admin);
        $this->grantPermission($admin, 'groups.view');
        $team = $this->storeTeamViaAjax('DelAjaxShowTeam_'.uniqid('', true));
        $thread = $this->teamThread($team);

        $this->getJson(route('chat.api.threads.show', $thread))
            ->assertOk()
            ->assertJsonPath('thread.id', $thread->id)
            ->assertJsonPath('thread.is_group', true)
            ->assertJsonPath('thread.team_id', $team->id);
    }

    public function test_ajax_success_body_is_not_empty(): void
    {
        $peer = $this->makePeer('DelAjaxBody_');
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);
        $this->grantPermission($this->user, 'messages.threads.delete');

        $res = $this->deleteJson(route('chat.api.threads.destroy', $thread))->assertOk();
        $this->assertNotSame('', trim((string) $res->getContent()));
        $this->assertIsInt($res->json('thread_id'));
    }
}
