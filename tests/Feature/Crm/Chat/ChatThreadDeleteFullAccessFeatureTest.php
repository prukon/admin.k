<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Chat;

use App\Models\ChatThread;
use Illuminate\Support\Facades\Auth;

/**
 * P1: гость / без messages.view / со messages.view без delete / superadmin — удаление чата.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ChatThreadDeleteFullAccessFeatureTest extends ChatTestCase
{
    public function test_guest_html_delete_redirects_and_does_not_remove_thread(): void
    {
        $peer = $this->makePeer('DelGuest_');
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);

        Auth::logout();

        $response = $this->from(route('chat.index'))
            ->delete(route('chat.api.threads.destroy', $thread));
        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertTrue($response->isRedirect());
        $this->assertGuest();
        $this->assertNotNull(ChatThread::query()->find($thread->id));
    }

    public function test_guest_json_delete_is_unauthorized(): void
    {
        $peer = $this->makePeer('DelGuestJson_');
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);

        Auth::logout();

        $this->deleteJson(route('chat.api.threads.destroy', $thread))->assertUnauthorized();
        $this->assertNotNull(ChatThread::query()->find($thread->id));
    }

    public function test_user_without_messages_view_gets_403(): void
    {
        $denied = $this->createUserWithoutPermission('messages.view', $this->partner);
        $peer = $this->makePeer('DelNoView_');
        $thread = $this->createThreadForUsers([$denied->id, $peer->id]);
        $this->actingInPartner($denied);

        $this->deleteJson(route('chat.api.threads.destroy', $thread))->assertForbidden();
        $this->assertNotNull(ChatThread::query()->find($thread->id));
    }

    public function test_user_with_messages_view_without_delete_gets_403(): void
    {
        $peer = $this->makePeer('DelViewOnly_');
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);

        $this->deleteJson(route('chat.api.threads.destroy', $thread))->assertForbidden();
        $this->assertNotNull(ChatThread::query()->find($thread->id));
    }

    public function test_put_on_destroy_is_405(): void
    {
        $peer = $this->makePeer('DelPut_');
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);
        $this->grantPermission($this->user, 'messages.threads.delete');

        $json = $this->json('PUT', route('chat.api.threads.destroy', $thread));
        $this->assertNotSame(500, $json->getStatusCode());
        $this->assertNotSame(200, $json->getStatusCode());
        $json->assertStatus(405);

        $html = $this->call('PUT', route('chat.api.threads.destroy', $thread));
        $this->assertNotSame(500, $html->getStatusCode());
        $this->assertNotSame(200, $html->getStatusCode());
        $this->assertSame(405, $html->getStatusCode());
    }

    public function test_admin_and_trainer_without_grant_cannot_delete(): void
    {
        $peer = $this->makePeer('DelStaff_');

        $admin = $this->createUserWithRole('admin');
        $thread = $this->createThreadForUsers([$admin->id, $peer->id]);
        $this->actingInPartner($admin);
        $this->deleteJson(route('chat.api.threads.destroy', $thread))->assertForbidden();
        $this->assertNotNull(ChatThread::query()->find($thread->id));

        $trainer = $this->createUserWithRole('trainer');
        $trainerThread = $this->createThreadForUsers([$trainer->id, $peer->id]);
        $this->actingInPartner($trainer);
        $this->deleteJson(route('chat.api.threads.destroy', $trainerThread))->assertForbidden();
        $this->assertNotNull(ChatThread::query()->find($trainerThread->id));
    }

    public function test_admin_and_trainer_with_grant_can_delete_own_private_chat(): void
    {
        $peer = $this->makePeer('DelGrantStaff_');

        $admin = $this->createUserWithRole('admin');
        $adminThread = $this->createThreadForUsers([$admin->id, $peer->id]);
        $this->actingInPartner($admin);
        $this->grantPermission($admin, 'messages.threads.delete');
        $this->deleteJson(route('chat.api.threads.destroy', $adminThread))
            ->assertOk()
            ->assertJsonPath('ok', true);
        $this->assertSoftDeleted('threads', ['id' => $adminThread->id]);

        $trainer = $this->createUserWithRole('trainer');
        $trainerThread = $this->createThreadForUsers([$trainer->id, $peer->id]);
        $this->actingInPartner($trainer);
        $this->grantPermission($trainer, 'messages.threads.delete');
        $this->deleteJson(route('chat.api.threads.destroy', $trainerThread))
            ->assertOk()
            ->assertJsonPath('message', 'Чат удалён.');
        $this->assertSoftDeleted('threads', ['id' => $trainerThread->id]);
    }

    public function test_opening_dialog_get_does_not_delete_the_chat(): void
    {
        $peer = $this->makePeer('DelGetShow_');
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);
        $this->grantPermission($this->user, 'messages.threads.delete');

        $json = $this->getJson(route('chat.api.threads.destroy', $thread));
        $this->assertNotSame(500, $json->getStatusCode());
        $json->assertOk()->assertJsonPath('thread.id', $thread->id);
        $this->assertNotNull(ChatThread::query()->find($thread->id));

        $html = $this->get(route('chat.api.threads.destroy', $thread));
        $this->assertNotSame(500, $html->getStatusCode());
        $this->assertNotSame('', trim((string) $html->getContent()));
        $this->assertNotNull(ChatThread::query()->find($thread->id));
    }

    public function test_post_and_patch_on_destroy_do_not_delete_and_are_not_empty_200(): void
    {
        $peer = $this->makePeer('DelWrong_');
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);
        $this->grantPermission($this->user, 'messages.threads.delete');
        $url = route('chat.api.threads.destroy', $thread);

        foreach (['POST', 'PATCH'] as $method) {
            $json = $this->json($method, $url);
            $this->assertNotSame(500, $json->getStatusCode(), $method.' JSON не 500');
            $this->assertNotSame(200, $json->getStatusCode(), $method.' не пустой 200');
            $this->assertContains($json->getStatusCode(), [404, 405], $method.' JSON 404/405');

            $html = $this->call($method, $url);
            $this->assertNotSame(500, $html->getStatusCode(), $method.' HTML не 500');
            $this->assertNotSame(200, $html->getStatusCode(), $method.' HTML не пустой 200');
            $this->assertContains($html->getStatusCode(), [404, 405], $method.' HTML 404/405');
        }

        $this->assertNotNull(ChatThread::query()->find($thread->id));
    }

    public function test_guest_post_and_patch_on_destroy_redirect_and_leave_thread(): void
    {
        $peer = $this->makePeer('DelGuestWrong_');
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);
        $url = route('chat.api.threads.destroy', $thread);

        Auth::logout();

        foreach (['POST', 'PATCH'] as $method) {
            $html = $this->call($method, $url);
            $this->assertNotSame(500, $html->getStatusCode(), $method.' HTML гость не 500');
            $this->assertNotSame(200, $html->getStatusCode(), $method.' HTML гость не пустой 200');
            $this->assertTrue(
                $html->isRedirect() || in_array($html->getStatusCode(), [401, 403, 404, 405], true),
                $method.' HTML гость: редирект или 401/403/404/405, получено '.$html->getStatusCode()
            );

            $json = $this->json($method, $url);
            $this->assertNotSame(500, $json->getStatusCode(), $method.' JSON гость не 500');
            $this->assertNotSame(200, $json->getStatusCode());
            $this->assertContains(
                $json->getStatusCode(),
                [401, 403, 404, 405],
                $method.' JSON гость 401/403/404/405'
            );
        }

        $this->assertGuest();
        $this->assertNotNull(ChatThread::query()->find($thread->id));
    }

    public function test_html_delete_without_permission_is_403_not_empty_200(): void
    {
        $peer = $this->makePeer('DelHtml403_');
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);

        $response = $this->from(route('chat.index'))
            ->delete(route('chat.api.threads.destroy', $thread));
        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertForbidden();
        $this->assertNotNull(ChatThread::query()->find($thread->id));
    }
}
