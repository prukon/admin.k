<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Chat;

use App\Models\ChatMessage;
use App\Models\ChatThread;
use Illuminate\Support\Facades\Auth;

/**
 * P1: гость / без messages.view / со messages.view без delete / superadmin — удаление своих сообщений.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ChatMessageDeleteFullAccessFeatureTest extends ChatTestCase
{
    public function test_guest_html_delete_redirects_and_does_not_remove_message(): void
    {
        $peer = $this->makePeer('MsgDelGuest_');
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);
        $message = $this->seedMessage($thread, $this->user->id, 'Гость');

        Auth::logout();

        $response = $this->from(route('chat.index'))
            ->delete($this->destroyUrl($thread, $message));
        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertTrue($response->isRedirect());
        $this->assertGuest();
        $this->assertNotNull(ChatMessage::query()->find($message->id));
    }

    public function test_guest_json_delete_is_unauthorized(): void
    {
        $peer = $this->makePeer('MsgDelGuestJson_');
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);
        $message = $this->seedMessage($thread, $this->user->id, 'Гость JSON');

        Auth::logout();

        $this->deleteJson($this->destroyUrl($thread, $message))->assertUnauthorized();
        $this->assertNotNull(ChatMessage::query()->find($message->id));
    }

    public function test_user_without_messages_view_gets_403(): void
    {
        $denied = $this->createUserWithoutPermission('messages.view', $this->partner);
        $peer = $this->makePeer('MsgDelNoView_');
        $thread = $this->createThreadForUsers([$denied->id, $peer->id]);
        $message = $this->seedMessage($thread, $denied->id, 'Без view');
        $this->actingInPartner($denied);

        $this->deleteJson($this->destroyUrl($thread, $message))->assertForbidden();
        $this->assertNotNull(ChatMessage::query()->find($message->id));
    }

    public function test_user_with_messages_view_without_delete_gets_403(): void
    {
        $peer = $this->makePeer('MsgDelViewOnly_');
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);
        $message = $this->seedMessage($thread, $this->user->id, 'Только view');

        $this->deleteJson($this->destroyUrl($thread, $message))->assertForbidden();
        $this->assertNotNull(ChatMessage::query()->find($message->id));
    }

    public function test_put_on_destroy_is_405(): void
    {
        $peer = $this->makePeer('MsgDelPut_');
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);
        $message = $this->seedMessage($thread, $this->user->id, 'PUT');
        $this->grantPermission($this->user, 'messages.own.delete');

        $json = $this->json('PUT', $this->destroyUrl($thread, $message));
        $this->assertNotSame(500, $json->getStatusCode());
        $this->assertNotSame(200, $json->getStatusCode());
        $json->assertStatus(405);

        $html = $this->call('PUT', $this->destroyUrl($thread, $message));
        $this->assertNotSame(500, $html->getStatusCode());
        $this->assertNotSame(200, $html->getStatusCode());
        $this->assertSame(405, $html->getStatusCode());
        $this->assertNotNull(ChatMessage::query()->find($message->id));
    }

    public function test_admin_and_trainer_without_grant_cannot_delete(): void
    {
        $peer = $this->makePeer('MsgDelStaff_');

        $admin = $this->createUserWithRole('admin');
        $thread = $this->createThreadForUsers([$admin->id, $peer->id]);
        $adminMsg = $this->seedMessage($thread, $admin->id, 'Админ без права');
        $this->actingInPartner($admin);
        $this->deleteJson($this->destroyUrl($thread, $adminMsg))->assertForbidden();
        $this->assertNotNull(ChatMessage::query()->find($adminMsg->id));

        $trainer = $this->createUserWithRole('trainer');
        $trainerThread = $this->createThreadForUsers([$trainer->id, $peer->id]);
        $trainerMsg = $this->seedMessage($trainerThread, $trainer->id, 'Тренер без права');
        $this->actingInPartner($trainer);
        $this->deleteJson($this->destroyUrl($trainerThread, $trainerMsg))->assertForbidden();
        $this->assertNotNull(ChatMessage::query()->find($trainerMsg->id));
    }

    public function test_admin_and_trainer_with_grant_can_delete_own_message(): void
    {
        $peer = $this->makePeer('MsgDelGrantStaff_');

        $admin = $this->createUserWithRole('admin');
        $adminThread = $this->createThreadForUsers([$admin->id, $peer->id]);
        $adminMsg = $this->seedMessage($adminThread, $admin->id, 'Админ своё');
        $this->actingInPartner($admin);
        $this->grantPermission($admin, 'messages.own.delete');
        $this->deleteJson($this->destroyUrl($adminThread, $adminMsg))
            ->assertOk()
            ->assertJsonPath('ok', true);
        $this->assertSoftDeleted('messages', ['id' => $adminMsg->id]);

        $trainer = $this->createUserWithRole('trainer');
        $trainerThread = $this->createThreadForUsers([$trainer->id, $peer->id]);
        $trainerMsg = $this->seedMessage($trainerThread, $trainer->id, 'Тренер своё');
        $this->actingInPartner($trainer);
        $this->grantPermission($trainer, 'messages.own.delete');
        $this->deleteJson($this->destroyUrl($trainerThread, $trainerMsg))
            ->assertOk()
            ->assertJsonPath('message', 'Сообщение удалено.');
        $this->assertSoftDeleted('messages', ['id' => $trainerMsg->id]);
    }

    public function test_html_delete_without_permission_is_403_not_empty_200(): void
    {
        $peer = $this->makePeer('MsgDelHtml403_');
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);
        $message = $this->seedMessage($thread, $this->user->id, 'HTML 403');

        $response = $this->from(route('chat.index'))
            ->delete($this->destroyUrl($thread, $message));
        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertForbidden();
        $this->assertNotNull(ChatMessage::query()->find($message->id));
    }

    private function destroyUrl(ChatThread $thread, ChatMessage $message): string
    {
        return route('chat.api.threads.messages.destroy', [
            'thread' => $thread->id,
            'message' => $message->id,
        ]);
    }
}
