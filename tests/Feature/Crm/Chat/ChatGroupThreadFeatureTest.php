<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Chat;

use App\Events\InboxBump;
use App\Models\ChatParticipant;
use App\Models\ChatThread;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;

/**
 * P1: создание группового чата — API, права, 422 под полями, inbox.bump, не дедуп 1-на-1.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ChatGroupThreadFeatureTest extends ChatTestCase
{
    public function test_guest_cannot_create_group_thread(): void
    {
        $a = $this->makePeer('GGuestA_');
        $b = $this->makePeer('GGuestB_');
        Auth::logout();

        $json = $this->postJson(route('chat.api.threads.groups.store'), [
            'title' => 'Группа',
            'user_ids' => [$a->id, $b->id],
        ]);
        $this->assertNotSame(500, $json->getStatusCode());
        $json->assertUnauthorized();

        $html = $this->from(route('chat.index'))->post(route('chat.api.threads.groups.store'), [
            'title' => 'Группа',
            'user_ids' => [$a->id, $b->id],
        ]);
        $this->assertNotSame(500, $html->getStatusCode());
        $this->assertNotSame(200, $html->getStatusCode());
        $this->assertTrue($html->isRedirect());
        $this->assertGuest();
        $this->assertSame(0, ChatThread::query()->count());
    }

    public function test_user_without_messages_view_gets_403_on_create_group(): void
    {
        $a = $this->makePeer('GDeniedA_');
        $b = $this->makePeer('GDeniedB_');
        $denied = $this->createUserWithoutPermission('messages.view', $this->partner);
        $this->actingInPartner($denied);

        $json = $this->postJson(route('chat.api.threads.groups.store'), [
            'title' => 'Группа',
            'user_ids' => [$a->id, $b->id],
        ]);
        $this->assertSame(403, $json->getStatusCode());
        $this->assertSame(0, ChatThread::query()->count());
    }

    public function test_ajax_create_group_returns_payload_and_does_not_dedupe(): void
    {
        $a = $this->makePeer('GAjaxA_');
        $b = $this->makePeer('GAjaxB_');

        $first = $this->postJson(route('chat.api.threads.groups.store'), [
            'title' => 'Тренеры',
            'user_ids' => [$a->id, $b->id],
        ])
            ->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('created', true)
            ->assertJsonPath('thread.title', 'Тренеры')
            ->assertJsonPath('thread.is_group', true)
            ->assertJsonPath('thread.peer_id', null)
            ->assertJsonPath('thread.peer_is_online', false)
            ->assertJsonStructure([
                'ok',
                'created',
                'thread_id',
                'thread' => ['id', 'title', 'avatar', 'peer_id', 'peer_is_online', 'is_group'],
            ]);

        $firstId = (int) $first->json('thread_id');
        $this->assertGreaterThan(0, $firstId);
        $this->assertSame(3, ChatParticipant::query()->where('thread_id', $firstId)->count());
        $this->assertTrue(
            ChatThread::query()->whereKey($firstId)->where('is_group', true)->where('subject', 'Тренеры')->exists()
        );

        $second = $this->postJson(route('chat.api.threads.groups.store'), [
            'title' => 'Тренеры',
            'user_ids' => [$a->id, $b->id],
        ])->assertCreated();
        $secondId = (int) $second->json('thread_id');
        $this->assertNotSame($firstId, $secondId);

        $list = collect($this->getJson(route('chat.api.threads.index'))->assertOk()->json('threads'));
        $this->assertNotNull($list->firstWhere('id', $firstId));
        $this->assertNotNull($list->firstWhere('id', $secondId));
        $row = $list->firstWhere('id', $firstId);
        $this->assertTrue((bool) $row['is_group']);
        $this->assertNull($row['peer_id']);
        $this->assertSame('Тренеры', $row['title']);
        $this->assertSame(0, (int) $row['unread_count']);
    }

    public function test_group_does_not_hide_private_dialog_with_same_member(): void
    {
        $a = $this->makePeer('GKeepA_');
        $b = $this->makePeer('GKeepB_');

        $privateId = (int) $this->postJson(route('chat.api.threads.store'), [
            'user_id' => $a->id,
        ])->assertCreated()->json('thread_id');

        $groupId = (int) $this->postJson(route('chat.api.threads.groups.store'), [
            'title' => 'Общая',
            'user_ids' => [$a->id, $b->id],
        ])->assertCreated()->json('thread_id');

        $list = collect($this->getJson(route('chat.api.threads.index'))->assertOk()->json('threads'));
        $this->assertNotNull($list->firstWhere('id', $privateId));
        $this->assertNotNull($list->firstWhere('id', $groupId));
        $private = $list->firstWhere('id', $privateId);
        $this->assertFalse((bool) ($private['is_group'] ?? false));
        $this->assertSame((int) $a->id, (int) $private['peer_id']);
    }

    public function test_group_with_two_live_members_is_not_reused_as_private_thread(): void
    {
        $a = $this->makePeer('GTwoA_');
        $b = $this->makePeer('GTwoB_');
        $group = $this->createGroupThreadForUsers([$this->user->id, $a->id, $b->id], 'Двое');

        ChatParticipant::query()
            ->where('thread_id', $group->id)
            ->where('user_id', $b->id)
            ->delete();

        $this->assertSame(2, ChatParticipant::query()->where('thread_id', $group->id)->count());
        $this->assertTrue((bool) $group->fresh()->is_group);

        $created = $this->postJson(route('chat.api.threads.store'), [
            'user_id' => $a->id,
        ])->assertCreated();

        $privateId = (int) $created->json('thread_id');
        $this->assertNotSame((int) $group->id, $privateId);
        $this->assertFalse((bool) $created->json('thread.is_group'));
        $this->assertTrue(
            ChatThread::query()->whereKey($group->id)->where('is_group', true)->exists()
        );
    }

    public function test_create_group_broadcasts_inbox_bump_to_every_member_without_unread(): void
    {
        $a = $this->makePeer('GBumpA_');
        $b = $this->makePeer('GBumpB_');

        Event::fake([InboxBump::class]);

        $created = $this->postJson(route('chat.api.threads.groups.store'), [
            'title' => 'Сборная',
            'user_ids' => [$a->id, $b->id],
        ])->assertCreated();
        $threadId = (int) $created->json('thread_id');

        $seen = [];
        Event::assertDispatched(InboxBump::class, function (InboxBump $event) use ($threadId, &$seen, $a, $b) {
            if ((int) $event->payload['thread_id'] !== $threadId) {
                return false;
            }
            $this->assertTrue((bool) $event->payload['is_group']);
            $this->assertSame('Сборная', $event->payload['title']);
            $this->assertNull($event->payload['peer_id']);
            $this->assertNull($event->payload['last_message']);
            $this->assertNull($event->payload['last_message_time']);
            $this->assertSame(0, (int) $event->payload['unread_count']);
            $this->assertContains($event->userId, [(int) $this->user->id, (int) $a->id, (int) $b->id]);
            $seen[$event->userId] = true;

            return true;
        });
        $this->assertCount(3, $seen);
    }

    public function test_validation_errors_stay_under_title_and_user_ids(): void
    {
        $this->postJson(route('chat.api.threads.groups.store'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'user_ids'])
            ->assertJsonPath('errors.title.0', 'Введите название группы.')
            ->assertJsonPath('errors.user_ids.0', 'Выберите минимум двух участников.');

        $onlyOne = $this->makePeer('GOne_');
        $this->postJson(route('chat.api.threads.groups.store'), [
            'title' => 'Мало',
            'user_ids' => [$onlyOne->id],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['user_ids'])
            ->assertJsonPath('errors.user_ids.0', 'Выберите минимум двух участников.')
            ->assertJsonMissingPath('errors.title');

        $this->postJson(route('chat.api.threads.groups.store'), [
            'title' => str_repeat('я', 101),
            'user_ids' => [$this->makePeer('GLongA_')->id, $this->makePeer('GLongB_')->id],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title'])
            ->assertJsonPath('errors.title.0', 'Название группы слишком длинное (максимум 100 символов).');

        $this->postJson(route('chat.api.threads.groups.store'), [
            'title' => 'Я',
            'user_ids' => [$this->user->id, $this->makePeer('GSelf_')->id],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['user_ids'])
            ->assertJsonPath('errors.user_ids.0', 'Нельзя добавить себя в список участников.');

        $disabled = $this->makePeer('GOff_', ['is_enabled' => 0]);
        $this->postJson(route('chat.api.threads.groups.store'), [
            'title' => 'Выкл',
            'user_ids' => [$this->makePeer('GOn_')->id, $disabled->id],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['user_ids'])
            ->assertJsonPath('errors.user_ids.0', 'Этот пользователь отключён.');

        $this->postJson(route('chat.api.threads.groups.store'), [
            'title' => 'Чужая',
            'user_ids' => [$this->makePeer('GOwn_')->id, $this->foreignUser->id],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['user_ids'])
            ->assertJsonPath('errors.user_ids.0', 'Нельзя добавить пользователя другой организации.');

        $this->assertSame(0, ChatThread::query()->count());
    }

    public function test_native_create_group_redirects_and_persists(): void
    {
        $a = $this->makePeer('GNatA_');
        $b = $this->makePeer('GNatB_');

        $response = $this->from(route('chat.index'))
            ->post(route('chat.api.threads.groups.store'), [
                'title' => 'Нативная',
                'user_ids' => [$a->id, $b->id],
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertNotSame(201, $response->getStatusCode());
        $response->assertRedirect(route('chat.index'));
        $this->assertTrue(
            ChatThread::query()->where('is_group', true)->where('subject', 'Нативная')->exists()
        );
    }

    public function test_native_create_group_validation_redirects_with_field_errors(): void
    {
        $response = $this->from(route('chat.index'))
            ->post(route('chat.api.threads.groups.store'), []);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertRedirect(route('chat.index'));
        $response->assertSessionHasErrors(['title', 'user_ids']);
        $this->assertSame(0, ChatThread::query()->count());
    }

    public function test_wrong_methods_on_create_group_are_not_empty_200(): void
    {
        foreach (['GET', 'PATCH', 'DELETE'] as $method) {
            $json = $this->json($method, route('chat.api.threads.groups.store'), [
                'title' => 'x',
                'user_ids' => [1, 2],
            ]);
            $this->assertNotSame(500, $json->getStatusCode(), $method.' JSON не 500');
            $this->assertNotSame(200, $json->getStatusCode(), $method.' не пустой 200');
            $this->assertContains($json->getStatusCode(), [404, 405], $method.' JSON 404/405');

            $html = $this->call($method, route('chat.api.threads.groups.store'), [
                'title' => 'x',
                'user_ids' => [1, 2],
            ]);
            $this->assertNotSame(500, $html->getStatusCode(), $method.' HTML не 500');
            $this->assertNotSame(200, $html->getStatusCode(), $method.' HTML не пустой 200');
            $this->assertContains($html->getStatusCode(), [404, 405], $method.' HTML 404/405');
        }
    }

    public function test_member_can_open_created_group_and_outsider_gets_403(): void
    {
        $a = $this->makePeer('GShowA_');
        $b = $this->makePeer('GShowB_');
        $outsider = $this->makePeer('GOut_');

        $threadId = (int) $this->postJson(route('chat.api.threads.groups.store'), [
            'title' => 'Открыть',
            'user_ids' => [$a->id, $b->id],
        ])->assertCreated()->json('thread_id');

        $this->getJson(route('chat.api.threads.show', $threadId))
            ->assertOk()
            ->assertJsonPath('thread.is_group', true)
            ->assertJsonPath('thread.title', 'Открыть')
            ->assertJsonPath('thread.peer_id', null)
            ->assertJsonPath('thread.members_total', 3)
            ->assertJsonPath('thread.header_subtitle', '3 участника');

        $this->actingInPartner($a);
        $this->getJson(route('chat.api.threads.show', $threadId))
            ->assertOk()
            ->assertJsonPath('thread.title', 'Открыть');

        $this->actingInPartner($outsider);
        $this->getJson(route('chat.api.threads.show', $threadId))->assertForbidden();
    }

    public function test_user_without_messages_view_native_post_gets_403_and_does_not_create(): void
    {
        $a = $this->makePeer('GHtmlDeniedA_');
        $b = $this->makePeer('GHtmlDeniedB_');
        $denied = $this->createUserWithoutPermission('messages.view', $this->partner);
        $this->actingInPartner($denied);

        $response = $this->from(route('chat.index'))->post(route('chat.api.threads.groups.store'), [
            'title' => 'Группа',
            'user_ids' => [$a->id, $b->id],
        ]);
        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame(0, ChatThread::query()->count());
    }

    public function test_whitespace_title_returns_422_under_title_and_does_not_create(): void
    {
        $a = $this->makePeer('GWsA_');
        $b = $this->makePeer('GWsB_');

        $this->postJson(route('chat.api.threads.groups.store'), [
            'title' => '   ',
            'user_ids' => [$a->id, $b->id],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title'])
            ->assertJsonPath('errors.title.0', 'Введите название группы.')
            ->assertJsonMissingPath('errors.user_ids');

        $this->assertSame(0, ChatThread::query()->count());
    }

    public function test_title_of_max_length_is_accepted_and_longer_is_not(): void
    {
        $a = $this->makePeer('GMaxA_');
        $b = $this->makePeer('GMaxB_');

        $this->postJson(route('chat.api.threads.groups.store'), [
            'title' => str_repeat('я', 100),
            'user_ids' => [$a->id, $b->id],
        ])->assertCreated()->assertJsonPath('ok', true);

        $this->postJson(route('chat.api.threads.groups.store'), [
            'title' => str_repeat('я', 101),
            'user_ids' => [$a->id, $b->id],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    public function test_duplicate_member_ids_return_422_under_user_ids(): void
    {
        $a = $this->makePeer('GDup_');

        $this->postJson(route('chat.api.threads.groups.store'), [
            'title' => 'Дубли',
            'user_ids' => [$a->id, $a->id],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['user_ids'])
            ->assertJsonPath('errors.user_ids.0', 'Список участников содержит повторы.');

        $this->assertSame(0, ChatThread::query()->count());
    }

    public function test_unknown_member_returns_422_under_user_ids_not_only_nested_key(): void
    {
        $a = $this->makePeer('GExist_');

        $this->postJson(route('chat.api.threads.groups.store'), [
            'title' => 'Нет такого',
            'user_ids' => [$a->id, 999999999],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['user_ids'])
            ->assertJsonPath('errors.user_ids.0', 'Выбранный пользователь не найден.');

        $this->assertSame(0, ChatThread::query()->count());
    }

    public function test_native_form_sends_string_user_ids_and_still_creates_group(): void
    {
        $a = $this->makePeer('GStrA_');
        $b = $this->makePeer('GStrB_');

        $response = $this->from(route('chat.index'))
            ->post(route('chat.api.threads.groups.store'), [
                'title' => 'Строки',
                'user_ids' => [(string) $a->id, (string) $b->id],
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertNotSame(201, $response->getStatusCode());
        $response->assertRedirect(route('chat.index'));
        $this->assertTrue(
            ChatThread::query()->where('is_group', true)->where('subject', 'Строки')->exists()
        );
    }

    public function test_native_foreign_member_redirects_with_user_ids_error(): void
    {
        $own = $this->makePeer('GNatOwn_');

        $response = $this->from(route('chat.index'))
            ->post(route('chat.api.threads.groups.store'), [
                'title' => 'Чужая',
                'user_ids' => [$own->id, $this->foreignUser->id],
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertRedirect(route('chat.index'));
        $response->assertSessionHasErrors(['user_ids']);
        $this->assertSame(0, ChatThread::query()->count());
    }

    public function test_native_disabled_member_redirects_with_user_ids_error(): void
    {
        $on = $this->makePeer('GNatOn_');
        $off = $this->makePeer('GNatOff_', ['is_enabled' => 0]);

        $response = $this->from(route('chat.index'))
            ->post(route('chat.api.threads.groups.store'), [
                'title' => 'Выкл',
                'user_ids' => [$on->id, $off->id],
            ]);

        $response->assertRedirect(route('chat.index'));
        $response->assertSessionHasErrors(['user_ids']);
        $this->assertSame(0, ChatThread::query()->count());
    }

    public function test_member_sees_new_group_in_inbox_with_zero_unread(): void
    {
        $a = $this->makePeer('GSeeA_');
        $b = $this->makePeer('GSeeB_');

        $threadId = (int) $this->postJson(route('chat.api.threads.groups.store'), [
            'title' => 'Видимая',
            'user_ids' => [$a->id, $b->id],
        ])->assertCreated()->json('thread_id');

        $this->actingInPartner($a);
        $this->getJson(route('chat.api.unread'))->assertOk()->assertJsonPath('unread_total', 0);
        $row = collect($this->getJson(route('chat.api.threads.index'))->assertOk()->json('threads'))
            ->firstWhere('id', $threadId);
        $this->assertNotNull($row);
        $this->assertTrue((bool) $row['is_group']);
        $this->assertSame('Видимая', $row['title']);
        $this->assertNull($row['peer_id']);
        $this->assertSame(0, (int) $row['unread_count']);
    }

    public function test_creating_group_then_private_dialog_with_same_person_keeps_both(): void
    {
        $a = $this->makePeer('GThenPrivA_');
        $b = $this->makePeer('GThenPrivB_');

        $groupId = (int) $this->postJson(route('chat.api.threads.groups.store'), [
            'title' => 'Сначала группа',
            'user_ids' => [$a->id, $b->id],
        ])->assertCreated()->json('thread_id');

        $privateId = (int) $this->postJson(route('chat.api.threads.store'), [
            'user_id' => $a->id,
        ])->assertCreated()->json('thread_id');
        $this->assertNotSame($groupId, $privateId);

        $list = collect($this->getJson(route('chat.api.threads.index'))->assertOk()->json('threads'));
        $this->assertNotNull($list->firstWhere('id', $groupId));
        $this->assertNotNull($list->firstWhere('id', $privateId));
        $this->assertFalse((bool) ($list->firstWhere('id', $privateId)['is_group'] ?? false));
        $this->assertTrue((bool) $list->firstWhere('id', $groupId)['is_group']);
    }

    public function test_group_accepts_messages_and_keeps_is_group_in_list(): void
    {
        $a = $this->makePeer('GMsgA_');
        $b = $this->makePeer('GMsgB_');
        $threadId = (int) $this->postJson(route('chat.api.threads.groups.store'), [
            'title' => 'Переписка',
            'user_ids' => [$a->id, $b->id],
        ])->assertCreated()->json('thread_id');

        $this->postJson(route('chat.api.threads.messages.store', $threadId), [
            'body' => 'Всем привет',
        ])->assertCreated()->assertJsonPath('body', 'Всем привет');

        $this->actingInPartner($a);
        $row = collect($this->getJson(route('chat.api.threads.index'))->assertOk()->json('threads'))
            ->firstWhere('id', $threadId);
        $this->assertNotNull($row);
        $this->assertTrue((bool) $row['is_group']);
        $this->assertNull($row['peer_id']);
        $this->assertSame('Переписка', $row['title']);
        $this->assertSame('Всем привет', $row['last_message']);
        $this->assertGreaterThan(0, (int) $row['unread_count']);

        $this->getJson(route('chat.api.threads.show', $threadId))
            ->assertOk()
            ->assertJsonPath('thread.is_group', true)
            ->assertJsonPath('thread.title', 'Переписка');
    }

    public function test_ajax_create_group_returns_json_not_redirect(): void
    {
        $a = $this->makePeer('GJsonA_');
        $b = $this->makePeer('GJsonB_');

        $this->postJson(route('chat.api.threads.groups.store'), [
            'title' => 'JSON',
            'user_ids' => [$a->id, $b->id],
        ])
            ->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('created', true)
            ->assertJsonStructure(['ok', 'created', 'thread_id', 'thread']);
    }

    public function test_admin_and_trainer_with_messages_view_can_create_group(): void
    {
        $a = $this->makePeer('GStaffA_');
        $b = $this->makePeer('GStaffB_');

        $admin = $this->createUserWithRole('admin');
        $this->actingInPartner($admin);
        $this->postJson(route('chat.api.threads.groups.store'), [
            'title' => 'Админская',
            'user_ids' => [$a->id, $b->id],
        ])->assertCreated()->assertJsonPath('thread.is_group', true);

        $trainer = $this->createUserWithRole('trainer');
        $this->actingInPartner($trainer);
        $this->postJson(route('chat.api.threads.groups.store'), [
            'title' => 'Тренерская',
            'user_ids' => [$a->id, $b->id],
        ])->assertCreated()->assertJsonPath('thread.title', 'Тренерская');
    }

    public function test_inbox_shows_group_name_not_first_member_full_name(): void
    {
        $a = $this->makePeer('GTitleA_', ['lastname' => 'СидоровГруппа', 'name' => 'Пётр']);
        $b = $this->makePeer('GTitleB_', ['lastname' => 'КозловГруппа', 'name' => 'Олег']);

        $threadId = (int) $this->postJson(route('chat.api.threads.groups.store'), [
            'title' => 'Тренерская смена',
            'user_ids' => [$a->id, $b->id],
        ])->assertCreated()->json('thread_id');

        foreach ([$this->user, $a] as $actor) {
            $this->actingInPartner($actor);
            $row = collect($this->getJson(route('chat.api.threads.index'))->assertOk()->json('threads'))
                ->firstWhere('id', $threadId);
            $this->assertNotNull($row);
            $this->assertSame('Тренерская смена', $row['title']);
            $this->assertNotSame($a->full_name, $row['title']);
            $this->assertNotSame($b->full_name, $row['title']);
            $this->assertTrue((bool) $row['is_group']);
            $this->assertNull($row['peer_id']);
        }
    }

    public function test_after_group_message_list_still_shows_group_name(): void
    {
        $a = $this->makePeer('GKeepA_', ['lastname' => 'СмирновЧат', 'name' => 'Алексей']);
        $b = $this->makePeer('GKeepB_');
        $threadId = (int) $this->postJson(route('chat.api.threads.groups.store'), [
            'title' => 'Переписка',
            'user_ids' => [$a->id, $b->id],
        ])->assertCreated()->json('thread_id');

        Event::fake([InboxBump::class]);
        $this->postJson(route('chat.api.threads.messages.store', $threadId), [
            'body' => 'Всем привет',
        ])->assertCreated();

        Event::assertDispatched(InboxBump::class, function (InboxBump $event) use ($threadId) {
            if ((int) $event->payload['thread_id'] !== $threadId) {
                return false;
            }
            $this->assertSame('Переписка', $event->payload['title']);
            $this->assertTrue((bool) $event->payload['is_group']);
            $this->assertNull($event->payload['peer_id']);

            return true;
        });

        $this->actingInPartner($a);
        $row = collect($this->getJson(route('chat.api.threads.index'))->assertOk()->json('threads'))
            ->firstWhere('id', $threadId);
        $this->assertNotNull($row);
        $this->assertSame('Переписка', $row['title']);
        $this->assertNotSame($a->full_name, $row['title']);
        $this->assertNotSame($this->user->full_name, $row['title']);
    }

    public function test_two_person_dialog_keeps_peer_name_even_if_subject_filled(): void
    {
        $peer = $this->makePeer('SubjPeer_', ['lastname' => 'Личный', 'name' => 'Клиент']);
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id], 'Не имя группы');

        $row = collect($this->getJson(route('chat.api.threads.index'))->assertOk()->json('threads'))
            ->firstWhere('id', $thread->id);
        $this->assertNotNull($row);
        $this->assertFalse((bool) ($row['is_group'] ?? false));
        $this->assertSame($peer->full_name, $row['title']);
        $this->assertNotSame('Не имя группы', $row['title']);
        $this->assertSame((int) $peer->id, (int) $row['peer_id']);
    }

    public function test_foreign_school_does_not_see_our_group_in_inbox(): void
    {
        $a = $this->makePeer('GForeignA_');
        $b = $this->makePeer('GForeignB_');
        $threadId = (int) $this->postJson(route('chat.api.threads.groups.store'), [
            'title' => 'Чужая школа не видит',
            'user_ids' => [$a->id, $b->id],
        ])->assertCreated()->json('thread_id');

        $this->grantPermission($this->foreignUser, 'messages.view', (int) $this->foreignUser->partner_id);
        $this->asForeignUser();

        $list = collect($this->getJson(route('chat.api.threads.index'))->assertOk()->json('threads'));
        $this->assertNull($list->firstWhere('id', $threadId));
    }

    public function test_superadmin_can_create_group(): void
    {
        $this->asSuperadmin();
        $a = $this->makePeer('GSuperA_');
        $b = $this->makePeer('GSuperB_');

        $this->postJson(route('chat.api.threads.groups.store'), [
            'title' => 'Супергруппа',
            'user_ids' => [$a->id, $b->id],
        ])
            ->assertCreated()
            ->assertJsonPath('thread.title', 'Супергруппа')
            ->assertJsonPath('thread.is_group', true)
            ->assertJsonPath('thread.peer_id', null);
    }

    public function test_three_participant_thread_without_flag_is_still_a_group(): void
    {
        $a = $this->makePeer('GLegacyA_');
        $b = $this->makePeer('GLegacyB_');
        $thread = ChatThread::query()->create([
            'subject' => 'Сборная',
            'is_group' => false,
        ]);
        foreach ([$this->user->id, $a->id, $b->id] as $userId) {
            ChatParticipant::query()->create([
                'thread_id' => $thread->id,
                'user_id' => $userId,
            ]);
        }

        $privateId = (int) $this->postJson(route('chat.api.threads.store'), [
            'user_id' => $a->id,
        ])->assertCreated()->json('thread_id');

        $list = collect($this->getJson(route('chat.api.threads.index'))->assertOk()->json('threads'));
        $group = $list->firstWhere('id', $thread->id);
        $this->assertNotNull($group);
        $this->assertTrue((bool) $group['is_group']);
        $this->assertSame('Сборная', $group['title']);
        $this->assertNull($group['peer_id']);
        $this->assertNotNull($list->firstWhere('id', $privateId));
        $this->assertSame((int) $a->id, (int) $list->firstWhere('id', $privateId)['peer_id']);
    }

    public function test_group_without_subject_is_named_group_not_dialog(): void
    {
        $a = $this->makePeer('GEmptyA_');
        $b = $this->makePeer('GEmptyB_');
        $thread = ChatThread::query()->create([
            'subject' => '',
            'is_group' => true,
        ]);
        foreach ([$this->user->id, $a->id, $b->id] as $userId) {
            ChatParticipant::query()->create([
                'thread_id' => $thread->id,
                'user_id' => $userId,
            ]);
        }

        $row = collect($this->getJson(route('chat.api.threads.index'))->assertOk()->json('threads'))
            ->firstWhere('id', $thread->id);
        $this->assertNotNull($row);
        $this->assertTrue((bool) $row['is_group']);
        $this->assertSame('Группа', $row['title']);
        $this->assertNotSame('Диалог', $row['title']);

        $this->getJson(route('chat.api.threads.show', $thread->id))
            ->assertOk()
            ->assertJsonPath('thread.title', 'Группа')
            ->assertJsonPath('thread.is_group', true);
    }

    public function test_put_on_create_group_is_not_empty_200(): void
    {
        $json = $this->json('PUT', route('chat.api.threads.groups.store'), [
            'title' => 'x',
            'user_ids' => [1, 2],
        ]);
        $this->assertNotSame(500, $json->getStatusCode());
        $this->assertNotSame(200, $json->getStatusCode());
        $this->assertContains($json->getStatusCode(), [404, 405]);

        $html = $this->call('PUT', route('chat.api.threads.groups.store'), [
            'title' => 'x',
            'user_ids' => [1, 2],
        ]);
        $this->assertNotSame(500, $html->getStatusCode());
        $this->assertNotSame(200, $html->getStatusCode());
        $this->assertContains($html->getStatusCode(), [404, 405]);
    }

    public function test_members_modal_lists_own_teams_in_order_and_defaults_to_all(): void
    {
        $later = \App\Models\Team::factory()->create([
            'partner_id' => $this->partner->id,
            'order_by' => 20,
            'title' => 'ГрПозже_'.uniqid('', true),
        ]);
        $earlier = \App\Models\Team::factory()->create([
            'partner_id' => $this->partner->id,
            'order_by' => 1,
            'title' => 'ГрРаньше_'.uniqid('', true),
        ]);
        $deleted = \App\Models\Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'ГрУдалена_'.uniqid('', true),
        ]);
        $deleted->delete();
        $foreign = \App\Models\Team::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'title' => 'ГрЧужая_'.uniqid('', true),
        ]);

        $html = $this->get(route('chat.index'))->assertOk()->getContent();
        $start = strpos($html, 'id="createGroupMembersModal"');
        $this->assertNotFalse($start);
        $modal = substr($html, $start, 3500);

        $filterPos = strpos($modal, 'id="createGroupMembersTeamFilter"');
        $teamErrPos = strpos($modal, 'id="createGroupMembersTeamError"');
        $searchPos = strpos($modal, 'id="createGroupMembersSearch"');
        $allPos = strpos($modal, 'Все группы');
        $nonePos = strpos($modal, 'Без группы');
        $earlyPos = strpos($modal, (string) $earlier->title);
        $latePos = strpos($modal, (string) $later->title);

        $this->assertNotFalse($filterPos);
        $this->assertNotFalse($teamErrPos);
        $this->assertNotFalse($searchPos);
        $this->assertLessThan($teamErrPos, $filterPos);
        $this->assertLessThan($searchPos, $teamErrPos);
        $this->assertLessThan($nonePos, $allPos);
        $this->assertLessThan($earlyPos, $nonePos);
        $this->assertLessThan($latePos, $earlyPos);
        $this->assertDoesNotMatchRegularExpression('/<option[^>]+selected/i', $modal);
        $this->assertStringNotContainsString((string) $deleted->title, $modal);
        $this->assertStringNotContainsString((string) $foreign->title, $modal);
        $this->assertStringNotContainsString('modal-xl', $modal);
        $this->assertStringNotContainsString('select2', strtolower($modal));
    }
}
