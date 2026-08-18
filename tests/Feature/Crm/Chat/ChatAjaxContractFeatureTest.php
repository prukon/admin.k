<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Chat;

use App\Models\ParentProfile;
use App\Models\Team;
use App\Services\TeamUserSyncService;

/**
 * P1: JSON-контракт API чата — структура, errors[field], история, прочитанность.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ChatAjaxContractFeatureTest extends ChatTestCase
{
    public function test_ajax_create_thread_returns_thread_payload_with_peer_title(): void
    {
        $peer = $this->makePeer('AjaxPeer_');

        $this->postJson(route('chat.api.threads.store'), [
            'user_id' => $peer->id,
        ])
            ->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('created', true)
            ->assertJsonPath('thread.title', $peer->full_name)
            ->assertJsonPath('thread.peer_id', $peer->id)
            ->assertJsonStructure([
                'ok',
                'created',
                'thread_id',
                'thread' => ['id', 'title', 'avatar', 'peer_id', 'peer_is_online'],
            ]);
    }

    public function test_ajax_send_message_returns_created_entity_and_body_error_on_empty(): void
    {
        $peer = $this->makePeer();
        $threadId = (int) $this->postJson(route('chat.api.threads.store'), [
            'user_id' => $peer->id,
        ])->json('thread_id');

        $this->postJson(route('chat.api.threads.messages.store', $threadId), [
            'body' => 'Привет AJAX',
        ])
            ->assertCreated()
            ->assertJsonPath('body', 'Привет AJAX')
            ->assertJsonPath('user_id', $this->user->id)
            ->assertJsonStructure(['id', 'user_id', 'body', 'created_at', 'is_read']);

        $this->postJson(route('chat.api.threads.messages.store', $threadId), [
            'body' => '',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['body']);

        $tooLong = str_repeat('я', 5001);
        $this->postJson(route('chat.api.threads.messages.store', $threadId), [
            'body' => $tooLong,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['body']);
    }

    public function test_ajax_store_thread_returns_field_errors_for_missing_and_disabled_peer(): void
    {
        $this->postJson(route('chat.api.threads.store'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);

        $disabled = $this->makePeer('Disabled_', ['is_enabled' => 0]);
        $this->postJson(route('chat.api.threads.store'), [
            'user_id' => $disabled->id,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);
    }

    public function test_opening_thread_marks_it_read_but_listing_threads_does_not(): void
    {
        $peer = $this->makePeer();
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);
        $this->seedMessage($thread, $peer->id, 'Пока не открывали');

        $this->getJson(route('chat.api.threads.index'))
            ->assertOk()
            ->assertJsonPath('unread_total', 1);

        $row = collect($this->getJson(route('chat.api.threads.index'))->json('threads'))
            ->firstWhere('id', $thread->id);
        $this->assertSame(1, (int) ($row['unread_count'] ?? 0));

        $this->getJson(route('chat.api.threads.show', $thread->id))
            ->assertOk()
            ->assertJsonPath('thread.id', $thread->id)
            ->assertJsonPath('unread_total', 0);

        $this->getJson(route('chat.api.unread'))
            ->assertOk()
            ->assertJsonPath('unread_total', 0);
    }

    public function test_history_before_id_loads_older_messages_after_id_loads_newer(): void
    {
        $peer = $this->makePeer();
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);

        for ($i = 1; $i <= 45; $i++) {
            $this->seedMessage($thread, $this->user->id, 'msg-'.$i);
        }

        $page = $this->getJson(route('chat.api.threads.show', $thread->id))
            ->assertOk()
            ->json('messages');

        $this->assertCount(40, $page);
        $this->assertSame('msg-6', $page[0]['body']);
        $this->assertSame('msg-45', $page[39]['body']);

        $older = $this->getJson(route('chat.api.threads.messages.index', [
            'thread' => $thread->id,
            'before_id' => $page[0]['id'],
        ]))->assertOk()->json();

        $this->assertCount(5, $older);
        $this->assertSame('msg-1', $older[0]['body']);
        $this->assertSame('msg-5', $older[4]['body']);

        $lastId = (int) $page[39]['id'];
        $this->getJson(route('chat.api.threads.messages.index', [
            'thread' => $thread->id,
            'after_id' => $lastId,
        ]))->assertOk()->assertExactJson([]);

        $this->postJson(route('chat.api.threads.messages.store', $thread->id), [
            'body' => 'msg-46',
        ])->assertCreated();

        $newer = $this->getJson(route('chat.api.threads.messages.index', [
            'thread' => $thread->id,
            'after_id' => $lastId,
        ]))->assertOk()->json();

        $this->assertCount(1, $newer);
        $this->assertSame('msg-46', $newer[0]['body']);
    }

    public function test_own_message_is_unread_until_peer_opens_thread(): void
    {
        $peer = $this->makePeer();
        $threadId = (int) $this->postJson(route('chat.api.threads.store'), [
            'user_id' => $peer->id,
        ])->json('thread_id');

        $sent = $this->postJson(route('chat.api.threads.messages.store', $threadId), [
            'body' => 'Жду прочтения',
        ])->assertCreated();

        $this->assertFalse((bool) $sent->json('is_read'));

        $this->actingInPartner($peer);
        $this->getJson(route('chat.api.threads.show', $threadId))->assertOk();

        $this->actingInPartner($this->user);
        $messages = $this->getJson(route('chat.api.threads.messages.index', $threadId))
            ->assertOk()
            ->json();

        $mine = collect($messages)->firstWhere('id', $sent->json('id'));
        $this->assertTrue((bool) $mine['is_read']);
    }

    public function test_invalid_message_cursor_returns_422_under_field(): void
    {
        $peer = $this->makePeer();
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);

        $this->getJson(route('chat.api.threads.messages.index', [
            'thread' => $thread->id,
            'before_id' => 0,
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['before_id']);

        $this->getJson(route('chat.api.users', ['q' => str_repeat('a', 121)]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['q']);
    }

    public function test_thread_preview_is_trimmed_and_contacts_exclude_self_and_disabled(): void
    {
        $peer = $this->makePeer('VisiblePeer_');
        $disabled = $this->makePeer('OffPeer_', ['is_enabled' => 0]);

        $threadId = (int) $this->postJson(route('chat.api.threads.store'), [
            'user_id' => $peer->id,
        ])->json('thread_id');

        $long = str_repeat('б', 120);
        $this->postJson(route('chat.api.threads.messages.store', $threadId), [
            'body' => $long,
        ])->assertCreated();

        $row = collect($this->getJson(route('chat.api.threads.index'))->json('threads'))
            ->firstWhere('id', $threadId);
        $this->assertNotNull($row);
        $this->assertLessThanOrEqual(91, mb_strlen((string) $row['last_message']));
        $this->assertStringEndsWith('…', (string) $row['last_message']);

        $contacts = $this->getJson(route('chat.api.users'))->assertOk()->json();
        $this->assertIsArray($contacts);
        $this->assertTrue(array_is_list($contacts), 'GET /chat/api/users — сырой массив, не {users:[]}');

        $ids = collect($contacts)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->assertContains((int) $peer->id, $ids);
        $this->assertNotContains((int) $this->user->id, $ids);
        $this->assertNotContains((int) $disabled->id, $ids);
    }

    public function test_own_outgoing_message_does_not_increase_sender_unread(): void
    {
        $peer = $this->makePeer();
        $threadId = (int) $this->postJson(route('chat.api.threads.store'), [
            'user_id' => $peer->id,
        ])->json('thread_id');

        $this->postJson(route('chat.api.threads.messages.store', $threadId), [
            'body' => 'Своё',
        ])->assertCreated();

        $this->getJson(route('chat.api.unread'))
            ->assertOk()
            ->assertJsonPath('unread_total', 0);
    }

    public function test_ajax_mark_read_returns_unread_total(): void
    {
        $peer = $this->makePeer();
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);
        $this->seedMessage($thread, $peer->id, 'Прочитать');

        $this->patchJson(route('chat.api.threads.read', $thread->id))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('unread_total', 0);
    }

    public function test_opening_and_sending_still_work_when_reverb_is_down(): void
    {
        $this->useUnreachableReverb();

        $peer = $this->makePeer();
        $threadId = (int) $this->postJson(route('chat.api.threads.store'), [
            'user_id' => $peer->id,
        ])->assertCreated()->json('thread_id');

        $this->getJson(route('chat.api.threads.show', $threadId))
            ->assertOk()
            ->assertJsonPath('thread.id', $threadId)
            ->assertJsonStructure(['thread', 'messages']);

        $this->postJson(route('chat.api.threads.messages.store', $threadId), [
            'body' => 'Без сокета',
        ])
            ->assertCreated()
            ->assertJsonPath('body', 'Без сокета');
    }

    public function test_thread_list_shows_one_dialog_per_peer_even_if_duplicates_exist(): void
    {
        $peer = $this->makePeer('DupPeer_');
        $older = $this->createThreadForUsers([$this->user->id, $peer->id], 'older');
        $older->forceFill(['updated_at' => now()->subMinute()])->save();
        $newer = $this->createThreadForUsers([$this->user->id, $peer->id], 'newer');
        $newer->forceFill(['updated_at' => now()])->save();

        $threads = $this->getJson(route('chat.api.threads.index'))
            ->assertOk()
            ->json('threads');

        $withPeer = collect($threads)->where('peer_id', $peer->id)->values();
        $this->assertCount(1, $withPeer);
        $this->assertSame($newer->id, (int) $withPeer[0]['id']);

        $reuse = $this->postJson(route('chat.api.threads.store'), [
            'user_id' => $peer->id,
        ])->assertOk();

        $this->assertFalse((bool) $reuse->json('created'));
        $this->assertSame($newer->id, (int) $reuse->json('thread_id'));
    }

    public function test_ajax_validation_puts_russian_messages_under_the_field(): void
    {
        $this->postJson(route('chat.api.threads.store'), [])
            ->assertStatus(422)
            ->assertJsonPath('errors.user_id.0', 'Выберите собеседника.');

        $this->postJson(route('chat.api.threads.store'), [
            'user_id' => $this->user->id,
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.user_id.0', 'Нельзя создать диалог с самим собой.');

        $disabled = $this->makePeer('OffPeer_', ['is_enabled' => 0]);
        $this->postJson(route('chat.api.threads.store'), [
            'user_id' => $disabled->id,
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.user_id.0', 'Этот пользователь отключён.');

        $peer = $this->makePeer();
        $threadId = (int) $this->postJson(route('chat.api.threads.store'), [
            'user_id' => $peer->id,
        ])->json('thread_id');

        $this->postJson(route('chat.api.threads.messages.store', $threadId), [
            'body' => '',
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.body.0', 'Введите текст сообщения.');

        $this->postJson(route('chat.api.threads.messages.store', $threadId), [
            'body' => str_repeat('я', 5001),
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.body.0', 'Сообщение слишком длинное (максимум 5000 символов).');

        $this->getJson(route('chat.api.threads.messages.index', [
            'thread' => $threadId,
            'after_id' => 'abc',
        ]))
            ->assertStatus(422)
            ->assertJsonPath('errors.after_id.0', 'Некорректный идентификатор сообщения.');
    }

    public function test_missing_thread_returns_404_not_server_error(): void
    {
        $response = $this->getJson(route('chat.api.threads.show', 9_999_999));
        $this->assertNotSame(500, $response->getStatusCode());
        $response->assertNotFound();

        $response = $this->postJson(route('chat.api.threads.messages.store', 9_999_999), [
            'body' => 'x',
        ]);
        $this->assertNotSame(500, $response->getStatusCode());
        $response->assertNotFound();
    }

    public function test_contacts_payload_is_a_list_with_picker_fields(): void
    {
        $peer = $this->makePeer('PickerPeer_');

        $contacts = $this->getJson(route('chat.api.users'))
            ->assertOk()
            ->assertJsonStructure([
                '*' => ['id', 'name', 'email', 'avatar', 'role_name', 'role_label', 'team_title', 'is_online', 'parent_full_name'],
            ])
            ->json();

        $this->assertTrue(array_is_list($contacts));
        $row = collect($contacts)->firstWhere('id', $peer->id);
        $this->assertNotNull($row);
        $this->assertSame($peer->full_name, $row['name']);
        $this->assertStringContainsString('/img/default-avatar.png', (string) $row['avatar']);
    }

    public function test_contacts_and_thread_title_use_lastname_then_name(): void
    {
        $withFio = $this->makePeer('NamePart_', [
            'lastname' => 'СмирновЧат',
            'name' => 'АлексейЧат',
        ]);
        $noLast = $this->makePeer('NoLast_', [
            'lastname' => '',
            'name' => 'ТолькоИмяЧат_'.uniqid('', true),
        ]);

        $contacts = collect($this->getJson(route('chat.api.users'))->assertOk()->json());
        $withRow = $contacts->firstWhere('id', $withFio->id);
        $noRow = $contacts->firstWhere('id', $noLast->id);
        $this->assertNotNull($withRow);
        $this->assertNotNull($noRow);
        $this->assertSame('СмирновЧат АлексейЧат', $withRow['name']);
        $this->assertSame($noLast->name, $noRow['name']);

        $created = $this->postJson(route('chat.api.threads.store'), ['user_id' => $withFio->id])
            ->assertCreated();
        $created->assertJsonPath('thread.title', 'СмирновЧат АлексейЧат');
        $withThreadId = (int) $created->json('thread_id');

        $indexWith = collect($this->getJson(route('chat.api.threads.index'))->json('threads'))
            ->firstWhere('id', $withThreadId);
        $this->assertNotNull($indexWith);
        $this->assertSame('СмирновЧат АлексейЧат', $indexWith['title']);
        $this->getJson(route('chat.api.threads.show', $withThreadId))
            ->assertOk()
            ->assertJsonPath('thread.title', 'СмирновЧат АлексейЧат');

        $controller = (string) file_get_contents(app_path('Http/Controllers/Chat/ChatApiController.php'));
        $this->assertStringNotContainsString(
            'participants.user:id,name,image_crop,last_seen_at',
            $controller,
            'GET show не должен грузить собеседника без lastname: openThread перезаписывает title в списке слева'
        );

        $threadId = (int) $this->postJson(route('chat.api.threads.store'), [
            'user_id' => $noLast->id,
        ])->assertCreated()->json('thread_id');

        $row = collect($this->getJson(route('chat.api.threads.index'))->json('threads'))
            ->firstWhere('id', $threadId);
        $this->assertNotNull($row);
        $this->assertSame($noLast->name, $row['title']);
    }

    public function test_contacts_search_matches_user_lastname_and_parent_name(): void
    {
        $parentLast = 'РодитФамЧат_'.uniqid('', true);
        $parentFirst = 'РодитИмяЧат_'.uniqid('', true);
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => $parentLast,
            'firstname' => $parentFirst,
            'middlename' => 'ОтчествоНеИщемЧат',
        ]);
        $kid = $this->makePeer('KidSearch_', [
            'lastname' => 'УченФамЧат_'.uniqid('', true),
            'name' => 'УченИмяЧат',
            'parent_id' => $parent->id,
        ]);
        $other = $this->makePeer('OtherSearch_');

        $byUserLast = collect($this->getJson(route('chat.api.users', ['q' => $kid->lastname]))->assertOk()->json());
        $this->assertNotNull($byUserLast->firstWhere('id', $kid->id));
        $this->assertNull($byUserLast->firstWhere('id', $other->id));

        $byParentLast = collect($this->getJson(route('chat.api.users', ['q' => $parentLast]))->assertOk()->json());
        $this->assertNotNull($byParentLast->firstWhere('id', $kid->id));
        $this->assertNull($byParentLast->firstWhere('id', $other->id));

        $byParentFirst = collect($this->getJson(route('chat.api.users', ['q' => $parentFirst]))->assertOk()->json());
        $this->assertNotNull($byParentFirst->firstWhere('id', $kid->id));

        $byMiddle = collect($this->getJson(route('chat.api.users', ['q' => 'ОтчествоНеИщемЧат']))->assertOk()->json());
        $this->assertNull($byMiddle->firstWhere('id', $kid->id));

        $parent->delete();
        $afterDelete = collect($this->getJson(route('chat.api.users', ['q' => $parentLast]))->assertOk()->json());
        $this->assertNull($afterDelete->firstWhere('id', $kid->id));
    }

    public function test_empty_inbox_returns_zero_unread_and_empty_thread_list(): void
    {
        $this->getJson(route('chat.api.threads.index'))
            ->assertOk()
            ->assertJsonPath('unread_total', 0)
            ->assertJsonPath('threads', []);

        $this->getJson(route('chat.api.unread'))
            ->assertOk()
            ->assertJsonPath('unread_total', 0)
            ->assertJsonMissingPath('threads');
    }

    public function test_peer_card_returns_profile_fields_and_online_label(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'Сидоров',
            'firstname' => 'Сидор',
            'middlename' => 'Сидорович',
            'phone' => '+79001112233',
        ]);
        $peer = $this->makePeer('CardPeer_', [
            'lastname' => 'Иванов',
            'name' => 'Иван',
            'phone' => '+79005556677',
            'parent_id' => $parent->id,
            'last_seen_at' => now(),
        ]);
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'КарточкаГруппа',
        ]);
        app(TeamUserSyncService::class)->syncTeamsForStudent($peer, [(int) $team->id]);

        $this->getJson(route('chat.api.users.show', $peer))
            ->assertOk()
            ->assertJsonStructure([
                'id', 'avatar', 'full_name', 'phone', 'parent_full_name', 'parent_phone',
                'is_online', 'last_seen_at', 'last_seen_label', 'team_title',
            ])
            ->assertJsonPath('full_name', 'Иванов Иван')
            ->assertJsonPath('phone', '+79005556677')
            ->assertJsonPath('parent_full_name', 'Сидоров Сидор Сидорович')
            ->assertJsonPath('parent_phone', '+79001112233')
            ->assertJsonPath('is_online', true)
            ->assertJsonPath('last_seen_label', 'онлайн')
            ->assertJsonPath('team_title', 'КарточкаГруппа');
    }

    public function test_peer_card_empty_fields_and_missing_last_seen_use_blank_and_dash(): void
    {
        $peer = $this->makePeer('EmptyCard_', [
            'lastname' => 'Петров',
            'name' => 'Пётр',
            'phone' => null,
            'last_seen_at' => null,
            'parent_id' => null,
        ]);

        $this->getJson(route('chat.api.users.show', $peer))
            ->assertOk()
            ->assertJsonPath('full_name', 'Петров Пётр')
            ->assertJsonPath('phone', '')
            ->assertJsonPath('parent_full_name', '')
            ->assertJsonPath('parent_phone', '')
            ->assertJsonPath('is_online', false)
            ->assertJsonPath('last_seen_label', '-')
            ->assertJsonPath('team_title', '');
    }

    public function test_peer_card_offline_last_seen_is_formatted_datetime(): void
    {
        $this->travelTo('2026-08-18 12:00:00');
        $peer = $this->makePeer('SeenCard_', [
            'last_seen_at' => now()->subMinutes(3),
        ]);

        $this->getJson(route('chat.api.users.show', $peer))
            ->assertOk()
            ->assertJsonPath('is_online', false)
            ->assertJsonPath('last_seen_label', '18.08.2026 11:57');
    }
}
