<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Chat;

use App\Events\InboxBump;
use App\Events\MessageReactionUpdated;
use App\Models\ChatMessageReaction;
use Illuminate\Support\Facades\Event;

/**
 * Реакции на сообщения: один смайл на человека, несколько чипов, без unread/inbox.bump.
 */
final class ChatReactionFeatureTest extends ChatTestCase
{
    public function test_participant_can_set_switch_and_remove_own_reaction(): void
    {
        $peer = $this->makePeer();
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);
        $message = $this->seedMessage($thread, $peer->id, 'Привет');

        Event::fake([InboxBump::class, MessageReactionUpdated::class]);

        $set = $this->putJson($this->reactionUrl($thread->id, $message->id), ['emoji' => '👍'])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('message_id', $message->id)
            ->assertJsonPath('reactions.0.emoji', '👍')
            ->assertJsonPath('reactions.0.count', 1)
            ->assertJsonPath('reactions.0.mine', true)
            ->assertJsonPath('reactions.0.users.0.id', $this->user->id);
        $this->assertCount(1, $set->json('reactions'));

        $this->assertDatabaseHas('message_reactions', [
            'message_id' => $message->id,
            'user_id' => $this->user->id,
            'emoji' => '👍',
        ]);

        $switched = $this->putJson($this->reactionUrl($thread->id, $message->id), ['emoji' => '❤️'])
            ->assertOk()
            ->assertJsonPath('reactions.0.emoji', '❤️')
            ->assertJsonPath('reactions.0.count', 1);
        $this->assertCount(1, $switched->json('reactions'));

        $this->assertSame(1, ChatMessageReaction::query()->where('message_id', $message->id)->count());
        $this->assertDatabaseHas('message_reactions', [
            'message_id' => $message->id,
            'user_id' => $this->user->id,
            'emoji' => '❤️',
        ]);

        $this->deleteJson($this->reactionUrl($thread->id, $message->id))
            ->assertOk()
            ->assertJsonPath('reactions', []);

        $this->assertSame(0, ChatMessageReaction::query()->where('message_id', $message->id)->count());

        Event::assertDispatched(MessageReactionUpdated::class);
        Event::assertNotDispatched(InboxBump::class);
    }

    public function test_can_react_to_own_message_and_show_includes_reactions(): void
    {
        $peer = $this->makePeer();
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);
        $message = $this->seedMessage($thread, $this->user->id, '👍');

        $this->putJson($this->reactionUrl($thread->id, $message->id), ['emoji' => '🔥'])
            ->assertOk()
            ->assertJsonPath('reactions.0.mine', true);

        $this->getJson(route('chat.api.threads.show', $thread->id))
            ->assertOk()
            ->assertJsonPath('messages.0.reactions.0.emoji', '🔥')
            ->assertJsonPath('messages.0.reactions.0.mine', true);

        $this->actingInPartner($peer);
        $this->getJson(route('chat.api.threads.show', $thread->id))
            ->assertOk()
            ->assertJsonPath('messages.0.reactions.0.emoji', '🔥')
            ->assertJsonPath('messages.0.reactions.0.mine', false)
            ->assertJsonPath('messages.0.reactions.0.user_ids.0', $this->user->id);
    }

    public function test_group_members_join_same_emoji_and_avatars_become_count_at_four(): void
    {
        $peers = [
            $this->makePeer('R1_'),
            $this->makePeer('R2_'),
            $this->makePeer('R3_'),
            $this->makePeer('R4_'),
        ];
        $ids = array_merge([$this->user->id], array_map(fn ($p) => (int) $p->id, $peers));
        $thread = $this->createGroupThreadForUsers($ids, 'Реакции');
        $message = $this->seedMessage($thread, $this->user->id, 'Го');

        foreach (array_slice($peers, 0, 3) as $peer) {
            $this->actingInPartner($peer);
            $this->putJson($this->reactionUrl($thread->id, $message->id), ['emoji' => '👍'])->assertOk();
        }

        $this->actingInPartner($this->user);
        $three = $this->getJson(route('chat.api.threads.show', $thread->id))->assertOk()->json('messages.0.reactions.0');
        $this->assertSame('👍', $three['emoji']);
        $this->assertSame(3, $three['count']);
        $this->assertCount(3, $three['users']);
        $this->assertNotEmpty($three['users'][0]['avatar']);
        $this->assertNotEmpty($three['users'][0]['name']);

        $this->actingInPartner($peers[3]);
        $four = $this->putJson($this->reactionUrl($thread->id, $message->id), ['emoji' => '👍'])
            ->assertOk()
            ->json('reactions.0');
        $this->assertSame(4, $four['count']);
        $this->assertSame([], $four['users']);
        $this->assertContains((int) $peers[3]->id, $four['user_ids']);
    }

    public function test_two_different_chips_can_sit_on_one_message(): void
    {
        $peer = $this->makePeer();
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);
        $message = $this->seedMessage($thread, $this->user->id, 'hi');

        $this->putJson($this->reactionUrl($thread->id, $message->id), ['emoji' => '👍'])->assertOk();
        $this->actingInPartner($peer);
        $payload = $this->putJson($this->reactionUrl($thread->id, $message->id), ['emoji' => '❤️'])
            ->assertOk()
            ->json('reactions');

        $this->assertCount(2, $payload);
        $emojis = array_column($payload, 'emoji');
        $this->assertContains('👍', $emojis);
        $this->assertContains('❤️', $emojis);
        $heart = collect($payload)->firstWhere('emoji', '❤️');
        $thumb = collect($payload)->firstWhere('emoji', '👍');
        $this->assertNotNull($heart);
        $this->assertNotNull($thumb);
        $this->assertTrue($heart['mine']);
        $this->assertFalse($thumb['mine']);
    }

    public function test_reaction_does_not_change_unread_and_validation_puts_error_under_emoji(): void
    {
        $peer = $this->makePeer();
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);
        $message = $this->seedMessage($thread, $peer->id, 'Непрочитанное');

        $this->actingInPartner($this->user);
        $this->getJson(route('chat.api.unread'))->assertOk()->assertJsonPath('unread_total', 1);

        Event::fake([InboxBump::class, MessageReactionUpdated::class]);
        $this->putJson($this->reactionUrl($thread->id, $message->id), ['emoji' => '👏'])->assertOk();
        Event::assertNotDispatched(InboxBump::class);

        $this->getJson(route('chat.api.unread'))->assertOk()->assertJsonPath('unread_total', 1);

        $this->putJson($this->reactionUrl($thread->id, $message->id), ['emoji' => '😀'])
            ->assertStatus(422)
            ->assertJsonPath('errors.emoji.0', 'Этот смайлик нельзя поставить как реакцию.');

        $this->putJson($this->reactionUrl($thread->id, $message->id), ['emoji' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['emoji']);

        $this->putJson($this->reactionUrl($thread->id, $message->id), [])
            ->assertStatus(422)
            ->assertJsonPath('errors.emoji.0', 'Выберите смайлик.');
    }

    public function test_foreign_thread_and_mismatched_message_are_forbidden_or_not_found(): void
    {
        $peer = $this->makePeer();
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);
        $message = $this->seedMessage($thread, $this->user->id, 'a');

        $other = $this->makePeer('Other_');
        $foreign = $this->createThreadForUsers([$other->id, $this->makePeer('Other2_')->id]);
        $foreignMsg = $this->seedMessage($foreign, $other->id, 'b');

        $this->putJson($this->reactionUrl($foreign->id, $foreignMsg->id), ['emoji' => '👍'])->assertForbidden();
        $this->putJson($this->reactionUrl($thread->id, $foreignMsg->id), ['emoji' => '👍'])->assertNotFound();
        $this->putJson($this->reactionUrl($thread->id, 9_999_999), ['emoji' => '👍'])->assertNotFound();
    }

    public function test_non_ajax_put_and_delete_redirect_and_persist(): void
    {
        $peer = $this->makePeer();
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);
        $message = $this->seedMessage($thread, $peer->id, 'x');

        $put = $this->from(route('chat.index'))
            ->put($this->reactionUrl($thread->id, $message->id), ['emoji' => '🎉']);
        $this->assertNotSame(500, $put->getStatusCode());
        $this->assertNotSame(200, $put->getStatusCode());
        $put->assertRedirect(route('chat.index'));
        $this->assertDatabaseHas('message_reactions', [
            'message_id' => $message->id,
            'user_id' => $this->user->id,
            'emoji' => '🎉',
        ]);

        $delete = $this->from(route('chat.index'))
            ->delete($this->reactionUrl($thread->id, $message->id));
        $this->assertNotSame(200, $delete->getStatusCode());
        $delete->assertRedirect(route('chat.index'));
        $this->assertSame(0, ChatMessageReaction::query()->where('message_id', $message->id)->count());
    }

    public function test_non_ajax_invalid_emoji_redirects_with_field_error(): void
    {
        $peer = $this->makePeer();
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);
        $message = $this->seedMessage($thread, $peer->id, 'x');

        $response = $this->from(route('chat.index'))
            ->put($this->reactionUrl($thread->id, $message->id), ['emoji' => 'nope']);
        $response->assertRedirect(route('chat.index'));
        $response->assertSessionHasErrors(['emoji']);
    }

    public function test_wrong_methods_are_not_empty_200(): void
    {
        $peer = $this->makePeer();
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);
        $message = $this->seedMessage($thread, $peer->id, 'x');
        $url = $this->reactionUrl($thread->id, $message->id);

        foreach (['GET', 'POST', 'PATCH'] as $method) {
            $html = $this->call($method, $url, ['emoji' => '👍']);
            $this->assertNotSame(500, $html->getStatusCode(), $method.' HTML не 500');
            $this->assertNotSame(200, $html->getStatusCode(), $method.' HTML не пустой 200');
            $this->assertContains($html->getStatusCode(), [404, 405], $method.' HTML '.$html->getStatusCode());
        }
    }

    private function reactionUrl(int $threadId, int $messageId): string
    {
        return route('chat.api.threads.messages.reaction.update', [
            'thread' => $threadId,
            'message' => $messageId,
        ]);
    }
}
