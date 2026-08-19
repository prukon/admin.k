<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Chat;

use App\Models\ChatThread;

/**
 * P1: нативный DELETE без X-Requested-With — 302 на /chat, soft-delete есть, не сырой JSON 200.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ChatThreadDeleteNonAjaxSafetyNetFeatureTest extends ChatTestCase
{
    use InteractsWithTeamGroupChats;

    public function test_non_ajax_delete_redirects_and_soft_deletes(): void
    {
        $peer = $this->makePeer('DelNative_');
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);
        $this->grantPermission($this->user, 'messages.threads.delete');

        $response = $this->from(route('chat.index'))
            ->delete(route('chat.api.threads.destroy', $thread));

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode(), 'Нативный DELETE не должен отдавать сырой JSON 200');
        $response->assertRedirect(route('chat.index'));
        $response->assertSessionHas('status', 'Чат удалён.');
        $this->assertSoftDeleted('threads', ['id' => $thread->id]);
    }

    public function test_non_ajax_team_chat_redirects_with_thread_field_error(): void
    {
        $admin = $this->createUserWithRole('admin');
        $this->actingInPartner($admin);
        $this->grantPermission($admin, 'groups.view');
        $this->grantPermission($admin, 'messages.threads.delete');
        $team = $this->storeTeamViaAjax('DelNativeTeam_'.uniqid('', true));
        $thread = $this->teamThread($team);

        $response = $this->from(route('chat.index'))
            ->delete(route('chat.api.threads.destroy', $thread));

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertRedirect(route('chat.index'));
        $response->assertSessionHasErrors(['thread']);
        $this->assertNotNull(ChatThread::query()->find($thread->id));
    }

    public function test_non_ajax_without_permission_is_403_not_json_200(): void
    {
        $peer = $this->makePeer('DelNative403_');
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);

        $response = $this->from(route('chat.index'))
            ->delete(route('chat.api.threads.destroy', $thread));

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertForbidden();
        $this->assertNotNull(ChatThread::query()->find($thread->id));
    }

    public function test_native_get_opens_dialog_json_and_does_not_delete(): void
    {
        $peer = $this->makePeer('DelNativeGet_');
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);
        $this->grantPermission($this->user, 'messages.threads.delete');

        $response = $this->get(route('chat.api.threads.destroy', $thread));
        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame('', trim((string) $response->getContent()));
        $response->assertOk();
        $this->assertNotNull(ChatThread::query()->find($thread->id));
    }

    public function test_native_post_and_patch_do_not_delete_the_chat(): void
    {
        $peer = $this->makePeer('DelNativeWrong_');
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);
        $this->grantPermission($this->user, 'messages.threads.delete');
        $url = route('chat.api.threads.destroy', $thread);

        foreach (['POST', 'PATCH'] as $method) {
            $response = $this->from(route('chat.index'))->call($method, $url);
            $this->assertNotSame(500, $response->getStatusCode(), $method.' не 500');
            $this->assertNotSame(200, $response->getStatusCode(), $method.' не сырой JSON 200');
            $this->assertContains($response->getStatusCode(), [404, 405], $method.' 404/405');
        }

        $this->assertNotNull(ChatThread::query()->find($thread->id));
    }
}
