<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Chat;

use App\Events\InboxBump;
use App\Models\ChatParticipant;
use Illuminate\Support\Facades\Event;

/**
 * Черновик сообщения: только свой participant, превью в списке, не виден собеседнику.
 */
final class ChatDraftFeatureTest extends ChatTestCase
{
    public function test_participant_can_save_and_clear_own_draft(): void
    {
        $peer = $this->makePeer();
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);

        $this->patchJson(route('chat.api.threads.draft', $thread->id), [
            'body' => '  Неотправленный текст  ',
        ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('draft_body', 'Неотправленный текст');

        $this->assertDatabaseHas('participants', [
            'thread_id' => $thread->id,
            'user_id' => $this->user->id,
            'draft_body' => 'Неотправленный текст',
        ]);

        $mine = collect($this->getJson(route('chat.api.threads.index'))->json('threads'))
            ->firstWhere('id', $thread->id);
        $this->assertNotNull($mine);
        $this->assertSame('Неотправленный текст', $mine['draft_body']);

        $this->getJson(route('chat.api.threads.show', $thread->id))
            ->assertOk()
            ->assertJsonPath('thread.draft_body', 'Неотправленный текст');

        $this->patchJson(route('chat.api.threads.draft', $thread->id), [
            'body' => '   ',
        ])
            ->assertOk()
            ->assertJsonPath('draft_body', '');

        $this->assertDatabaseHas('participants', [
            'thread_id' => $thread->id,
            'user_id' => $this->user->id,
            'draft_body' => null,
        ]);
    }

    public function test_peer_does_not_see_other_user_draft_in_list_or_show(): void
    {
        $peer = $this->makePeer();
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);

        $this->patchJson(route('chat.api.threads.draft', $thread->id), [
            'body' => 'секретный черновик',
        ])->assertOk();

        $this->actingInPartner($peer);

        $row = collect($this->getJson(route('chat.api.threads.index'))->json('threads'))
            ->firstWhere('id', $thread->id);
        $this->assertNotNull($row);
        $this->assertSame('', $row['draft_body']);

        $this->getJson(route('chat.api.threads.show', $thread->id))
            ->assertOk()
            ->assertJsonPath('thread.draft_body', '');
    }

    public function test_sending_message_clears_sender_draft_and_inbox_bump_omits_peer_draft(): void
    {
        $peer = $this->makePeer();
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);

        $this->patchJson(route('chat.api.threads.draft', $thread->id), [
            'body' => 'черновик до отправки',
        ])->assertOk();

        Event::fake([InboxBump::class]);

        $this->postJson(route('chat.api.threads.messages.store', $thread->id), [
            'body' => 'Отправлено',
        ])->assertCreated();

        $this->assertDatabaseHas('participants', [
            'thread_id' => $thread->id,
            'user_id' => $this->user->id,
            'draft_body' => null,
        ]);

        $this->getJson(route('chat.api.threads.index'))
            ->assertOk();
        $row = collect($this->getJson(route('chat.api.threads.index'))->json('threads'))
            ->firstWhere('id', $thread->id);
        $this->assertSame('', $row['draft_body']);

        Event::assertDispatched(InboxBump::class, function (InboxBump $event) {
            if ($event->userId !== (int) $this->user->id) {
                return false;
            }
            $data = $event->broadcastWith();

            return array_key_exists('draft_body', $data) && $data['draft_body'] === '';
        });

        Event::assertDispatched(InboxBump::class, function (InboxBump $event) use ($peer) {
            if ($event->userId !== (int) $peer->id) {
                return false;
            }
            $data = $event->broadcastWith();

            return ! array_key_exists('draft_body', $data);
        });
    }

    public function test_saving_draft_does_not_broadcast_inbox_bump(): void
    {
        $peer = $this->makePeer();
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);

        Event::fake([InboxBump::class]);

        $this->patchJson(route('chat.api.threads.draft', $thread->id), [
            'body' => 'тихо',
        ])->assertOk();

        Event::assertNotDispatched(InboxBump::class);
    }

    public function test_too_long_draft_returns_body_field_error(): void
    {
        $peer = $this->makePeer();
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);

        $this->patchJson(route('chat.api.threads.draft', $thread->id), [
            'body' => str_repeat('я', 5001),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['body']);

        $this->assertNull(
            ChatParticipant::query()
                ->where('thread_id', $thread->id)
                ->where('user_id', $this->user->id)
                ->value('draft_body')
        );
    }

    public function test_wrong_methods_on_draft_uri_are_not_allowed(): void
    {
        $peer = $this->makePeer();
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);
        $url = route('chat.api.threads.draft', $thread->id);

        foreach (['GET', 'POST', 'DELETE'] as $method) {
            $json = $this->json($method, $url, ['body' => 'x']);
            $this->assertNotSame(500, $json->getStatusCode(), $method.' JSON не 500');
            $this->assertNotSame(200, $json->getStatusCode(), $method.' JSON не пустой 200');
            $this->assertContains(
                $json->getStatusCode(),
                [404, 405],
                $method.' JSON должен быть 404/405, получено '.$json->getStatusCode()
            );
        }
    }

    public function test_empty_thread_list_includes_empty_draft_body(): void
    {
        $peer = $this->makePeer();
        $threadId = (int) $this->postJson(route('chat.api.threads.store'), [
            'user_id' => $peer->id,
        ])->json('thread_id');

        $created = $this->postJson(route('chat.api.threads.store'), [
            'user_id' => $peer->id,
        ]);
        $created->assertJsonPath('thread.draft_body', '');

        $row = collect($this->getJson(route('chat.api.threads.index'))->json('threads'))
            ->firstWhere('id', $threadId);
        $this->assertNotNull($row);
        $this->assertSame('', $row['draft_body']);
    }
}
