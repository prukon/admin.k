<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Chat;

use App\Events\InboxBump;
use App\Models\ChatParticipant;
use App\Models\ChatThread;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;

/**
 * Участники группового чата: список с курсором, добавить (только admin),
 * удалить другого (только admin), покинуть, 0 участников — soft-delete треда.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ChatGroupMembersFeatureTest extends ChatTestCase
{
    public function test_guest_cannot_read_or_change_group_members(): void
    {
        $admin = $this->createUserWithRole('admin');
        $a = $this->makePeer('GMemGuestA_');
        $b = $this->makePeer('GMemGuestB_');
        $thread = $this->createGroupThreadForUsers([$admin->id, $a->id, $b->id], 'Гость');

        Auth::logout();

        $json = $this->getJson(route('chat.api.threads.participants.index', $thread));
        $this->assertNotSame(500, $json->getStatusCode());
        $json->assertUnauthorized();

        $html = $this->get(route('chat.api.threads.participants.index', $thread));
        $this->assertNotSame(500, $html->getStatusCode());
        $this->assertTrue($html->isRedirect());
        $this->assertGuest();
    }

    public function test_user_without_messages_view_gets_403_on_members_api(): void
    {
        $denied = $this->createUserWithoutPermission('messages.view', $this->partner);
        $a = $this->makePeer('GMemDeniedA_');
        $thread = $this->createGroupThreadForUsers([$denied->id, $a->id, $this->user->id], 'Без права');
        $this->actingInPartner($denied);

        $this->getJson(route('chat.api.threads.participants.index', $thread))->assertForbidden();
        $this->postJson(route('chat.api.threads.participants.store', $thread), [
            'user_ids' => [$a->id],
        ])->assertForbidden();
        $this->deleteJson(route('chat.api.threads.participants.destroy', [$thread, $a]))->assertForbidden();
    }

    public function test_member_sees_paginated_list_with_role_and_count(): void
    {
        $admin = $this->createUserWithRole('admin', $this->partner, [
            'lastname' => 'Админов',
            'name' => 'Андрей',
        ]);
        $first = $this->makePeer('AaaMem_', [
            'lastname' => 'Аааев',
            'name' => 'Иван',
            'role_id' => $this->roleId('user'),
        ]);
        $second = $this->makePeer('BbbMem_', [
            'lastname' => 'Бббев',
            'name' => 'Пётр',
            'role_id' => $this->roleId('trainer'),
        ]);
        $thread = $this->createGroupThreadForUsers([$admin->id, $first->id, $second->id], 'Состав');
        $this->actingInPartner($admin);

        $res = $this->getJson(route('chat.api.threads.participants.index', $thread))
            ->assertOk()
            ->assertJsonPath('thread.title', 'Состав')
            ->assertJsonPath('thread.is_group', true)
            ->assertJsonPath('thread.members_total', 3)
            ->assertJsonPath('thread.header_subtitle', '3 участника')
            ->assertJsonPath('can_manage', true)
            ->assertJsonPath('has_more', false)
            ->assertJsonStructure([
                'thread' => ['id', 'title', 'avatar', 'is_group', 'members_total', 'header_subtitle'],
                'can_manage',
                'members' => [['id', 'avatar', 'full_name', 'role_name', 'role_label']],
                'has_more',
            ]);

        $names = collect($res->json('members'))->pluck('full_name')->all();
        $this->assertSame(['Аааев Иван', 'Админов Андрей', 'Бббев Пётр'], $names);
        $this->assertSame('user', $res->json('members.0.role_name'));
        $this->assertSame(
            (string) Role::query()->where('name', 'user')->value('label'),
            (string) $res->json('members.0.role_label')
        );
        $this->assertNotSame('admin', (string) $res->json('members.0.role_label'));
    }

    public function test_members_cursor_loads_next_page(): void
    {
        $admin = $this->createUserWithRole('admin', $this->partner, [
            'lastname' => 'Аааадмин',
            'name' => 'Андрей',
        ]);
        $ids = [$admin->id];
        $created = [];
        for ($i = 0; $i < 16; $i++) {
            $peer = $this->makePeer('Page'.$i.'_', [
                'lastname' => sprintf('Яяя%02d', $i),
                'name' => 'Имя',
            ]);
            $ids[] = $peer->id;
            $created[] = $peer;
        }
        $thread = $this->createGroupThreadForUsers($ids, 'Страницы');
        $this->actingInPartner($admin);

        $first = $this->getJson(route('chat.api.threads.participants.index', $thread))
            ->assertOk()
            ->assertJsonPath('has_more', true)
            ->assertJsonPath('thread.members_total', 17);
        $this->assertCount(15, $first->json('members'));

        $lastId = (int) $first->json('members.14.id');
        $second = $this->getJson(route('chat.api.threads.participants.index', $thread).'?after_user_id='.$lastId)
            ->assertOk()
            ->assertJsonPath('has_more', false);
        $this->assertCount(2, $second->json('members'));
        $this->assertNotContains($lastId, collect($second->json('members'))->pluck('id')->all());
    }

    public function test_after_user_id_zero_returns_422_under_field(): void
    {
        $admin = $this->createUserWithRole('admin');
        $a = $this->makePeer('ZeroA_');
        $b = $this->makePeer('ZeroB_');
        $thread = $this->createGroupThreadForUsers([$admin->id, $a->id, $b->id], 'Ноль');
        $this->actingInPartner($admin);

        $this->getJson(route('chat.api.threads.participants.index', $thread).'?after_user_id=0')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['after_user_id'])
            ->assertJsonPath('errors.after_user_id.0', 'Некорректный идентификатор участника.');

        $native = $this->from(route('chat.index'))
            ->get(route('chat.api.threads.participants.index', $thread).'?after_user_id=0');
        $this->assertNotSame(500, $native->getStatusCode());
        $this->assertNotSame(200, $native->getStatusCode());
        $native->assertRedirect(route('chat.index'));
        $native->assertSessionHasErrors(['after_user_id']);
    }

    public function test_private_thread_members_api_is_403(): void
    {
        $peer = $this->makePeer('PrivMem_');
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);

        $this->getJson(route('chat.api.threads.participants.index', $thread))
            ->assertForbidden()
            ->assertJsonPath('message', 'Это не групповой чат.');
    }

    public function test_outsider_cannot_read_members(): void
    {
        $admin = $this->createUserWithRole('admin');
        $a = $this->makePeer('OutA_');
        $b = $this->makePeer('OutB_');
        $outsider = $this->makePeer('OutC_');
        $thread = $this->createGroupThreadForUsers([$admin->id, $a->id, $b->id], 'Чужой');
        $this->actingInPartner($outsider);

        $this->getJson(route('chat.api.threads.participants.index', $thread))->assertForbidden();
    }

    public function test_student_and_trainer_cannot_add_or_kick(): void
    {
        $student = $this->user;
        $trainer = $this->createUserWithRole('trainer');
        $a = $this->makePeer('KickA_');
        $extra = $this->makePeer('KickExtra_');
        $thread = $this->createGroupThreadForUsers([$student->id, $trainer->id, $a->id], 'Нельзя');

        $this->actingInPartner($student);
        $this->postJson(route('chat.api.threads.participants.store', $thread), [
            'user_ids' => [$extra->id],
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Добавлять и удалять участников может только администратор.');
        $this->deleteJson(route('chat.api.threads.participants.destroy', [$thread, $a]))
            ->assertForbidden();

        $this->actingInPartner($trainer);
        $this->postJson(route('chat.api.threads.participants.store', $thread), [
            'user_ids' => [$extra->id],
        ])->assertForbidden();
        $this->deleteJson(route('chat.api.threads.participants.destroy', [$thread, $a]))
            ->assertForbidden();

        $this->assertSame(3, ChatParticipant::query()->where('thread_id', $thread->id)->count());
    }

    public function test_admin_adds_member_and_broadcasts_inbox_bump(): void
    {
        $admin = $this->createUserWithRole('admin');
        $a = $this->makePeer('AddA_');
        $b = $this->makePeer('AddB_');
        $newbie = $this->makePeer('AddNew_');
        $thread = $this->createGroupThreadForUsers([$admin->id, $a->id, $b->id], 'Добавить');
        $this->actingInPartner($admin);

        Event::fake([InboxBump::class]);

        $this->postJson(route('chat.api.threads.participants.store', $thread), [
            'user_ids' => [$newbie->id],
        ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('message', 'Участники добавлены.')
            ->assertJsonPath('members_total', 4)
            ->assertJsonPath('thread.members_total', 4)
            ->assertJsonPath('thread.header_subtitle', '4 участника');

        $this->assertTrue(
            ChatParticipant::query()->where('thread_id', $thread->id)->where('user_id', $newbie->id)->exists()
        );

        Event::assertDispatched(InboxBump::class, function (InboxBump $event) use ($thread, $newbie) {
            return (int) $event->userId === (int) $newbie->id
                && (int) $event->payload['thread_id'] === (int) $thread->id
                && (bool) $event->payload['is_group'] === true;
        });
    }

    public function test_add_already_member_or_self_or_foreign_returns_422_under_user_ids(): void
    {
        $admin = $this->createUserWithRole('admin');
        $a = $this->makePeer('ValA_');
        $b = $this->makePeer('ValB_');
        $thread = $this->createGroupThreadForUsers([$admin->id, $a->id, $b->id], 'Валидация');
        $this->actingInPartner($admin);

        $this->postJson(route('chat.api.threads.participants.store', $thread), [
            'user_ids' => [$a->id],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['user_ids'])
            ->assertJsonPath('errors.user_ids.0', 'Этот пользователь уже в группе.');

        $this->postJson(route('chat.api.threads.participants.store', $thread), [
            'user_ids' => [$admin->id],
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.user_ids.0', 'Нельзя добавить себя в список участников.');

        $this->postJson(route('chat.api.threads.participants.store', $thread), [
            'user_ids' => [$this->foreignUser->id],
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.user_ids.0', 'Нельзя добавить пользователя другой организации.');

        $this->postJson(route('chat.api.threads.participants.store', $thread), [])
            ->assertStatus(422)
            ->assertJsonPath('errors.user_ids.0', 'Выберите хотя бы одного участника.');
    }

    public function test_admin_kicks_member_and_removed_bump(): void
    {
        $admin = $this->createUserWithRole('admin');
        $a = $this->makePeer('RmA_');
        $b = $this->makePeer('RmB_');
        $thread = $this->createGroupThreadForUsers([$admin->id, $a->id, $b->id], 'Кик');
        $this->actingInPartner($admin);

        Event::fake([InboxBump::class]);

        $this->deleteJson(route('chat.api.threads.participants.destroy', [$thread, $a]))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('message', 'Участник удалён.')
            ->assertJsonPath('left', false)
            ->assertJsonPath('members_total', 2);

        $this->assertFalse(
            ChatParticipant::query()->where('thread_id', $thread->id)->where('user_id', $a->id)->exists()
        );
        $this->assertTrue(
            ChatParticipant::withTrashed()->where('thread_id', $thread->id)->where('user_id', $a->id)->exists()
        );

        Event::assertDispatched(InboxBump::class, function (InboxBump $event) use ($thread, $a) {
            return (int) $event->userId === (int) $a->id
                && (int) $event->payload['thread_id'] === (int) $thread->id
                && (bool) ($event->payload['removed'] ?? false) === true;
        });
    }

    public function test_member_can_leave_and_last_member_deletes_thread(): void
    {
        $admin = $this->createUserWithRole('admin');
        $only = $this->makePeer('LastOne_');
        $thread = $this->createGroupThreadForUsers([$only->id], 'Последний');
        $this->actingInPartner($only);

        $this->deleteJson(route('chat.api.threads.participants.destroy', [$thread, $only]))
            ->assertOk()
            ->assertJsonPath('message', 'Вы покинули группу.')
            ->assertJsonPath('left', true)
            ->assertJsonPath('thread_deleted', true)
            ->assertJsonPath('members_total', 0);

        $this->assertSoftDeleted('threads', ['id' => $thread->id]);
        $this->assertSame(0, ChatParticipant::query()->where('thread_id', $thread->id)->count());
        unset($admin);
    }

    public function test_admin_can_readd_after_leave(): void
    {
        $admin = $this->createUserWithRole('admin');
        $a = $this->makePeer('ReA_');
        $b = $this->makePeer('ReB_');
        $thread = $this->createGroupThreadForUsers([$admin->id, $a->id, $b->id], 'Вернуть');
        $this->actingInPartner($a);
        $this->deleteJson(route('chat.api.threads.participants.destroy', [$thread, $a]))->assertOk();

        $this->actingInPartner($admin);
        $this->postJson(route('chat.api.threads.participants.store', $thread), [
            'user_ids' => [$a->id],
        ])->assertOk()->assertJsonPath('members_total', 3);

        $this->assertSame(
            1,
            ChatParticipant::withTrashed()->where('thread_id', $thread->id)->where('user_id', $a->id)->count()
        );
    }

    public function test_native_add_redirects_and_creates_participant(): void
    {
        $admin = $this->createUserWithRole('admin');
        $a = $this->makePeer('NatAddA_');
        $b = $this->makePeer('NatAddB_');
        $newbie = $this->makePeer('NatAddNew_');
        $thread = $this->createGroupThreadForUsers([$admin->id, $a->id, $b->id], 'Натив');
        $this->actingInPartner($admin);

        $response = $this->from(route('chat.index'))
            ->post(route('chat.api.threads.participants.store', $thread), [
                'user_ids' => [$newbie->id],
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertRedirect(route('chat.index'));
        $this->assertTrue(
            ChatParticipant::query()->where('thread_id', $thread->id)->where('user_id', $newbie->id)->exists()
        );
    }

    public function test_native_add_validation_redirects_with_user_ids_error(): void
    {
        $admin = $this->createUserWithRole('admin');
        $a = $this->makePeer('NatErrA_');
        $b = $this->makePeer('NatErrB_');
        $thread = $this->createGroupThreadForUsers([$admin->id, $a->id, $b->id], 'Натив ошибка');
        $this->actingInPartner($admin);

        $response = $this->from(route('chat.index'))
            ->post(route('chat.api.threads.participants.store', $thread), []);

        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertRedirect(route('chat.index'));
        $response->assertSessionHasErrors(['user_ids']);
    }

    public function test_native_leave_redirects(): void
    {
        $admin = $this->createUserWithRole('admin');
        $a = $this->makePeer('NatLeaveA_');
        $b = $this->makePeer('NatLeaveB_');
        $thread = $this->createGroupThreadForUsers([$admin->id, $a->id, $b->id], 'Натив выход');
        $this->actingInPartner($a);

        $response = $this->from(route('chat.index'))
            ->delete(route('chat.api.threads.participants.destroy', [$thread, $a]));

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertRedirect(route('chat.index'));
        $this->assertFalse(
            ChatParticipant::query()->where('thread_id', $thread->id)->where('user_id', $a->id)->exists()
        );
    }

    public function test_wrong_methods_on_participants_are_not_empty_200(): void
    {
        $admin = $this->createUserWithRole('admin');
        $a = $this->makePeer('MethA_');
        $b = $this->makePeer('MethB_');
        $thread = $this->createGroupThreadForUsers([$admin->id, $a->id, $b->id], 'Методы');
        $this->actingInPartner($admin);

        foreach (['PATCH', 'PUT', 'DELETE'] as $method) {
            $json = $this->json($method, route('chat.api.threads.participants.index', $thread));
            $this->assertNotSame(500, $json->getStatusCode(), $method.' JSON index не 500');
            $this->assertNotSame(200, $json->getStatusCode(), $method.' JSON index не пустой 200');
            $this->assertContains($json->getStatusCode(), [404, 405], $method.' JSON index 404/405');

            $html = $this->call($method, route('chat.api.threads.participants.index', $thread));
            $this->assertNotSame(500, $html->getStatusCode(), $method.' HTML index не 500');
            $this->assertNotSame(200, $html->getStatusCode(), $method.' HTML index не пустой 200');
            $this->assertContains($html->getStatusCode(), [404, 405], $method.' HTML index 404/405');
        }

        foreach (['GET', 'POST', 'PATCH', 'PUT'] as $method) {
            $json = $this->json($method, route('chat.api.threads.participants.destroy', [$thread, $a]));
            $this->assertNotSame(500, $json->getStatusCode(), $method.' JSON destroy не 500');
            $this->assertNotSame(200, $json->getStatusCode(), $method.' JSON destroy не пустой 200');
            $this->assertContains($json->getStatusCode(), [404, 405], $method.' JSON destroy 404/405');

            $html = $this->call($method, route('chat.api.threads.participants.destroy', [$thread, $a]));
            $this->assertNotSame(500, $html->getStatusCode(), $method.' HTML destroy не 500');
            $this->assertNotSame(200, $html->getStatusCode(), $method.' HTML destroy не пустой 200');
            $this->assertContains($html->getStatusCode(), [404, 405], $method.' HTML destroy 404/405');
        }
    }

    public function test_exclude_thread_id_hides_current_members_from_picker(): void
    {
        $admin = $this->createUserWithRole('admin');
        $a = $this->makePeer('ExA_');
        $b = $this->makePeer('ExB_');
        $outside = $this->makePeer('ExOut_');
        $thread = $this->createGroupThreadForUsers([$admin->id, $a->id, $b->id], 'Исключить');
        $this->actingInPartner($admin);

        $ids = collect($this->getJson(route('chat.api.users', ['exclude_thread_id' => $thread->id]))
            ->assertOk()
            ->json())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->assertContains((int) $outside->id, $ids);
        $this->assertNotContains((int) $a->id, $ids);
        $this->assertNotContains((int) $b->id, $ids);
        $this->assertNotContains((int) $admin->id, $ids);
    }

    public function test_student_list_has_can_manage_false_and_admin_true(): void
    {
        $admin = $this->createUserWithRole('admin');
        $a = $this->makePeer('FlagA_');
        $thread = $this->createGroupThreadForUsers([$this->user->id, $admin->id, $a->id], 'Флаг');

        $this->getJson(route('chat.api.threads.participants.index', $thread))
            ->assertOk()
            ->assertJsonPath('can_manage', false);

        $this->actingInPartner($admin);
        $this->getJson(route('chat.api.threads.participants.index', $thread))
            ->assertOk()
            ->assertJsonPath('can_manage', true);
    }

    public function test_guest_cannot_add_or_remove_members_via_json_or_form(): void
    {
        $admin = $this->createUserWithRole('admin');
        $a = $this->makePeer('GMemGuestPostA_');
        $b = $this->makePeer('GMemGuestPostB_');
        $thread = $this->createGroupThreadForUsers([$admin->id, $a->id, $b->id], 'Гость мутации');

        Auth::logout();

        $postJson = $this->postJson(route('chat.api.threads.participants.store', $thread), [
            'user_ids' => [$a->id],
        ]);
        $this->assertNotSame(500, $postJson->getStatusCode());
        $this->assertNotSame(200, $postJson->getStatusCode());
        $postJson->assertUnauthorized();

        $deleteJson = $this->deleteJson(route('chat.api.threads.participants.destroy', [$thread, $a]));
        $this->assertNotSame(500, $deleteJson->getStatusCode());
        $deleteJson->assertUnauthorized();

        $postHtml = $this->from(route('chat.index'))
            ->post(route('chat.api.threads.participants.store', $thread), [
                'user_ids' => [$a->id],
            ]);
        $this->assertNotSame(500, $postHtml->getStatusCode());
        $this->assertNotSame(200, $postHtml->getStatusCode());
        $this->assertTrue($postHtml->isRedirect());

        $deleteHtml = $this->from(route('chat.index'))
            ->delete(route('chat.api.threads.participants.destroy', [$thread, $a]));
        $this->assertNotSame(500, $deleteHtml->getStatusCode());
        $this->assertNotSame(200, $deleteHtml->getStatusCode());
        $this->assertTrue($deleteHtml->isRedirect());
        $this->assertGuest();
        $this->assertSame(3, ChatParticipant::query()->where('thread_id', $thread->id)->count());
    }

    public function test_missing_and_deleted_thread_members_api_is_404(): void
    {
        $admin = $this->createUserWithRole('admin');
        $a = $this->makePeer('MissA_');
        $b = $this->makePeer('MissB_');
        $thread = $this->createGroupThreadForUsers([$admin->id, $a->id, $b->id], 'Удалят');
        $this->actingInPartner($admin);

        $missingId = ((int) ChatThread::query()->max('id')) + 999;
        foreach (['GET', 'POST'] as $method) {
            $json = $this->json($method, '/chat/api/threads/'.$missingId.'/participants', [
                'user_ids' => [$a->id],
            ]);
            $this->assertNotSame(500, $json->getStatusCode(), $method.' missing JSON не 500');
            $this->assertNotSame(200, $json->getStatusCode(), $method.' missing не пустой 200');
            $json->assertNotFound();
        }

        $thread->delete();
        $this->getJson(route('chat.api.threads.participants.index', $thread->id))
            ->assertNotFound();
        $this->postJson(route('chat.api.threads.participants.store', $thread->id), [
            'user_ids' => [$a->id],
        ])->assertNotFound();
    }

    public function test_private_thread_cannot_add_or_kick(): void
    {
        $peer = $this->makePeer('PrivKick_');
        $extra = $this->makePeer('PrivExtra_');
        $admin = $this->createUserWithRole('admin');
        $thread = $this->createThreadForUsers([$admin->id, $peer->id]);
        $this->actingInPartner($admin);

        $this->postJson(route('chat.api.threads.participants.store', $thread), [
            'user_ids' => [$extra->id],
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Это не групповой чат.');
        $this->deleteJson(route('chat.api.threads.participants.destroy', [$thread, $peer]))
            ->assertForbidden()
            ->assertJsonPath('message', 'Это не групповой чат.');
        $this->assertSame(2, ChatParticipant::query()->where('thread_id', $thread->id)->count());
    }

    public function test_outsider_cannot_add_or_kick(): void
    {
        $admin = $this->createUserWithRole('admin');
        $a = $this->makePeer('OutKickA_');
        $b = $this->makePeer('OutKickB_');
        $outsider = $this->makePeer('OutKickC_');
        $extra = $this->makePeer('OutKickD_');
        $thread = $this->createGroupThreadForUsers([$admin->id, $a->id, $b->id], 'Чужой мутации');
        $this->actingInPartner($outsider);

        $this->getJson(route('chat.api.threads.participants.index', $thread))
            ->assertForbidden()
            ->assertJsonPath('message', 'Нет доступа к этому диалогу.');
        $this->postJson(route('chat.api.threads.participants.store', $thread), [
            'user_ids' => [$extra->id],
        ])->assertForbidden();
        $this->deleteJson(route('chat.api.threads.participants.destroy', [$thread, $a]))
            ->assertForbidden();
        $this->assertSame(3, ChatParticipant::query()->where('thread_id', $thread->id)->count());
    }

    public function test_superadmin_can_add_and_kick_members(): void
    {
        $super = $this->createUserWithRole('superadmin');
        $a = $this->makePeer('SupA_');
        $b = $this->makePeer('SupB_');
        $newbie = $this->makePeer('SupNew_');
        $thread = $this->createGroupThreadForUsers([$super->id, $a->id, $b->id], 'Супер');
        $this->actingInPartner($super);

        $this->getJson(route('chat.api.threads.participants.index', $thread))
            ->assertOk()
            ->assertJsonPath('can_manage', true);

        $this->postJson(route('chat.api.threads.participants.store', $thread), [
            'user_ids' => [$newbie->id],
        ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('members_total', 4)
            ->assertJsonStructure(['ok', 'message', 'members_total', 'thread']);

        $this->deleteJson(route('chat.api.threads.participants.destroy', [$thread, $a]))
            ->assertOk()
            ->assertJsonPath('left', false)
            ->assertJsonPath('members_total', 3);
    }

    public function test_student_and_admin_can_leave_without_deleting_thread_when_others_remain(): void
    {
        $admin = $this->createUserWithRole('admin');
        $student = $this->user;
        $a = $this->makePeer('StayA_');
        $thread = $this->createGroupThreadForUsers([$admin->id, $student->id, $a->id], 'Остаются');

        $this->deleteJson(route('chat.api.threads.participants.destroy', [$thread, $student]))
            ->assertOk()
            ->assertJsonPath('left', true)
            ->assertJsonPath('thread_deleted', false)
            ->assertJsonPath('members_total', 2);
        $this->assertNull($thread->fresh()->deleted_at);

        $this->actingInPartner($admin);
        $this->deleteJson(route('chat.api.threads.participants.destroy', [$thread, $admin]))
            ->assertOk()
            ->assertJsonPath('left', true)
            ->assertJsonPath('thread_deleted', false);
        $this->assertNull($thread->fresh()->deleted_at);
        $this->assertSame(1, ChatParticipant::query()->where('thread_id', $thread->id)->count());
    }

    public function test_former_member_cannot_read_members(): void
    {
        $admin = $this->createUserWithRole('admin');
        $a = $this->makePeer('FormerA_');
        $b = $this->makePeer('FormerB_');
        $thread = $this->createGroupThreadForUsers([$admin->id, $a->id, $b->id], 'Бывший');
        $this->actingInPartner($a);
        $this->deleteJson(route('chat.api.threads.participants.destroy', [$thread, $a]))->assertOk();

        $this->getJson(route('chat.api.threads.participants.index', $thread))
            ->assertForbidden()
            ->assertJsonPath('message', 'Нет доступа к этому диалогу.');
    }

    public function test_kick_user_not_in_group_returns_422_under_user(): void
    {
        $admin = $this->createUserWithRole('admin');
        $a = $this->makePeer('NotInA_');
        $b = $this->makePeer('NotInB_');
        $outsider = $this->makePeer('NotInC_');
        $thread = $this->createGroupThreadForUsers([$admin->id, $a->id, $b->id], 'Не состоит');
        $this->actingInPartner($admin);

        $this->deleteJson(route('chat.api.threads.participants.destroy', [$thread, $outsider]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['user'])
            ->assertJsonPath('errors.user.0', 'Этот пользователь не состоит в группе.');

        $missingUserId = ((int) User::query()->max('id')) + 999;
        $missing = $this->deleteJson('/chat/api/threads/'.$thread->id.'/participants/'.$missingUserId);
        $this->assertNotSame(500, $missing->getStatusCode());
        $this->assertNotSame(200, $missing->getStatusCode());
        $this->assertContains($missing->getStatusCode(), [404, 405]);
    }

    public function test_native_kick_missing_member_redirects_with_user_error(): void
    {
        $admin = $this->createUserWithRole('admin');
        $a = $this->makePeer('NatMissA_');
        $b = $this->makePeer('NatMissB_');
        $outsider = $this->makePeer('NatMissC_');
        $thread = $this->createGroupThreadForUsers([$admin->id, $a->id, $b->id], 'Натив не состоит');
        $this->actingInPartner($admin);

        $response = $this->from(route('chat.index'))
            ->delete(route('chat.api.threads.participants.destroy', [$thread, $outsider]));
        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertRedirect(route('chat.index'));
        $response->assertSessionHasErrors(['user']);
    }

    public function test_disabled_unknown_and_duplicate_ids_return_422_under_user_ids(): void
    {
        $admin = $this->createUserWithRole('admin');
        $a = $this->makePeer('BadA_');
        $b = $this->makePeer('BadB_');
        $disabled = $this->makePeer('BadOff_', ['is_enabled' => 0]);
        $extra = $this->makePeer('BadExtra_');
        $thread = $this->createGroupThreadForUsers([$admin->id, $a->id, $b->id], 'Плохие id');
        $this->actingInPartner($admin);

        $this->postJson(route('chat.api.threads.participants.store', $thread), [
            'user_ids' => [$disabled->id],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['user_ids'])
            ->assertJsonPath('errors.user_ids.0', 'Этот пользователь отключён.');

        $this->postJson(route('chat.api.threads.participants.store', $thread), [
            'user_ids' => [999999999],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['user_ids'])
            ->assertJsonPath('errors.user_ids.0', 'Выбранный пользователь не найден.');

        $this->postJson(route('chat.api.threads.participants.store', $thread), [
            'user_ids' => [$extra->id, $extra->id],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['user_ids']);
        $dup = $this->postJson(route('chat.api.threads.participants.store', $thread), [
            'user_ids' => [$extra->id, $extra->id],
        ])->json('errors.user_ids.0');
        $this->assertNotSame('', trim((string) $dup));
        $this->assertTrue(
            str_contains((string) $dup, 'повтор') || str_contains((string) $dup, 'distinct') || str_contains((string) $dup, 'Участник'),
            'Повторы должны быть ошибкой поля user_ids, получено: '.$dup
        );
    }

    public function test_too_many_user_ids_return_422_under_user_ids(): void
    {
        $admin = $this->createUserWithRole('admin');
        $a = $this->makePeer('MaxA_');
        $b = $this->makePeer('MaxB_');
        $thread = $this->createGroupThreadForUsers([$admin->id, $a->id, $b->id], 'Максимум');
        $this->actingInPartner($admin);

        $ids = range(1, 101);
        $this->postJson(route('chat.api.threads.participants.store', $thread), [
            'user_ids' => $ids,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['user_ids'])
            ->assertJsonPath('errors.user_ids.0', 'Слишком много участников (максимум 100).');
    }

    public function test_native_add_accepts_string_user_ids_and_ajax_returns_json_not_redirect(): void
    {
        $admin = $this->createUserWithRole('admin');
        $a = $this->makePeer('StrA_');
        $b = $this->makePeer('StrB_');
        $newbie = $this->makePeer('StrNew_');
        $ajaxNew = $this->makePeer('StrAjax_');
        $thread = $this->createGroupThreadForUsers([$admin->id, $a->id, $b->id], 'Строки');
        $this->actingInPartner($admin);

        $native = $this->from(route('chat.index'))
            ->post(route('chat.api.threads.participants.store', $thread), [
                'user_ids' => [(string) $newbie->id],
            ]);
        $this->assertNotSame(200, $native->getStatusCode());
        $this->assertNotSame(201, $native->getStatusCode());
        $native->assertRedirect(route('chat.index'));
        $this->assertTrue(
            ChatParticipant::query()->where('thread_id', $thread->id)->where('user_id', $newbie->id)->exists()
        );

        $ajax = $this->postJson(route('chat.api.threads.participants.store', $thread), [
            'user_ids' => [$ajaxNew->id],
        ]);
        $this->assertFalse($ajax->isRedirect());
        $ajax->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('message', 'Участники добавлены.');
        $this->assertStringStartsWith('{', trim((string) $ajax->getContent()));
    }

    public function test_native_get_members_returns_json_not_empty_html_page(): void
    {
        $admin = $this->createUserWithRole('admin');
        $a = $this->makePeer('NatGetA_');
        $b = $this->makePeer('NatGetB_');
        $thread = $this->createGroupThreadForUsers([$admin->id, $a->id, $b->id], 'Натив GET');
        $this->actingInPartner($admin);

        $response = $this->get(route('chat.api.threads.participants.index', $thread));
        $this->assertNotSame(500, $response->getStatusCode());
        $response->assertOk();
        $this->assertStringStartsWith('{', trim((string) $response->getContent()));
        $response->assertJsonPath('thread.is_group', true)
            ->assertJsonPath('thread.members_total', 3);
        $this->assertStringNotContainsString('<html', (string) $response->getContent());
    }

    public function test_native_kick_redirects_and_removes_participant(): void
    {
        $admin = $this->createUserWithRole('admin');
        $a = $this->makePeer('NatKickA_');
        $b = $this->makePeer('NatKickB_');
        $thread = $this->createGroupThreadForUsers([$admin->id, $a->id, $b->id], 'Натив кик');
        $this->actingInPartner($admin);

        $response = $this->from(route('chat.index'))
            ->delete(route('chat.api.threads.participants.destroy', [$thread, $a]));
        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertRedirect(route('chat.index'));
        $this->assertFalse(
            ChatParticipant::query()->where('thread_id', $thread->id)->where('user_id', $a->id)->exists()
        );
    }

    public function test_user_without_messages_view_native_add_is_403(): void
    {
        $denied = $this->createUserWithoutPermission('messages.view', $this->partner);
        $a = $this->makePeer('NatDeniedA_');
        $extra = $this->makePeer('NatDeniedExtra_');
        $thread = $this->createGroupThreadForUsers([$denied->id, $a->id, $this->user->id], 'Натив без права');
        $this->actingInPartner($denied);

        $response = $this->from(route('chat.index'))
            ->post(route('chat.api.threads.participants.store', $thread), [
                'user_ids' => [$extra->id],
            ]);
        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertSame(403, $response->getStatusCode());
        $this->assertFalse(
            ChatParticipant::query()->where('thread_id', $thread->id)->where('user_id', $extra->id)->exists()
        );
    }

    public function test_exclude_thread_id_zero_private_or_outsider_returns_422_under_field(): void
    {
        $admin = $this->createUserWithRole('admin');
        $a = $this->makePeer('ExZeroA_');
        $b = $this->makePeer('ExZeroB_');
        $outsider = $this->makePeer('ExZeroOut_');
        $thread = $this->createGroupThreadForUsers([$admin->id, $a->id, $b->id], 'Exclude');
        $private = $this->createThreadForUsers([$admin->id, $a->id]);
        $this->actingInPartner($admin);

        $this->getJson(route('chat.api.users', ['exclude_thread_id' => 0]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['exclude_thread_id'])
            ->assertJsonPath('errors.exclude_thread_id.0', 'Некорректный идентификатор чата.');

        $native = $this->from(route('chat.index'))
            ->get(route('chat.api.users', ['exclude_thread_id' => 0]));
        $this->assertNotSame(500, $native->getStatusCode());
        $this->assertNotSame(200, $native->getStatusCode());
        $native->assertRedirect(route('chat.index'));
        $native->assertSessionHasErrors(['exclude_thread_id']);

        $this->getJson(route('chat.api.users', ['exclude_thread_id' => $private->id]))
            ->assertStatus(422)
            ->assertJsonPath('errors.exclude_thread_id.0', 'Некорректный идентификатор чата.');

        $this->actingInPartner($outsider);
        $this->getJson(route('chat.api.users', ['exclude_thread_id' => $thread->id]))
            ->assertStatus(422)
            ->assertJsonPath('errors.exclude_thread_id.0', 'Нет доступа к этому диалогу.');
    }

    public function test_after_user_id_of_non_member_returns_422_under_field(): void
    {
        $admin = $this->createUserWithRole('admin');
        $a = $this->makePeer('CurA_');
        $b = $this->makePeer('CurB_');
        $stranger = $this->makePeer('CurS_');
        $thread = $this->createGroupThreadForUsers([$admin->id, $a->id, $b->id], 'Курсор');
        $this->actingInPartner($admin);

        $this->getJson(route('chat.api.threads.participants.index', $thread).'?after_user_id='.$stranger->id)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['after_user_id'])
            ->assertJsonPath('errors.after_user_id.0', 'Некорректный идентификатор участника.');
    }

    public function test_last_member_does_not_delete_team_group_chat(): void
    {
        $admin = $this->createUserWithRole('admin');
        $only = $this->makePeer('TeamLast_');
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'УчГруппаЧат_'.uniqid('', true),
        ]);
        $thread = $this->createGroupThreadForUsers([$only->id], 'Учебный чат');
        $thread->forceFill(['team_id' => $team->id])->save();
        $this->actingInPartner($only);

        $this->deleteJson(route('chat.api.threads.participants.destroy', [$thread, $only]))
            ->assertOk()
            ->assertJsonPath('thread_deleted', false)
            ->assertJsonPath('members_total', 0);
        $this->assertNull($thread->fresh()->deleted_at);
        unset($admin);
    }

    public function test_add_still_works_when_reverb_is_down(): void
    {
        $this->useUnreachableReverb();
        $admin = $this->createUserWithRole('admin');
        $a = $this->makePeer('RevA_');
        $b = $this->makePeer('RevB_');
        $newbie = $this->makePeer('RevNew_');
        $thread = $this->createGroupThreadForUsers([$admin->id, $a->id, $b->id], 'Reverb');
        $this->actingInPartner($admin);

        $this->postJson(route('chat.api.threads.participants.store', $thread), [
            'user_ids' => [$newbie->id],
        ])->assertOk()->assertJsonPath('ok', true);
        $this->assertTrue(
            ChatParticipant::query()->where('thread_id', $thread->id)->where('user_id', $newbie->id)->exists()
        );
    }

    public function test_add_members_modal_lists_own_teams_in_order_and_defaults_to_all(): void
    {
        $later = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'order_by' => 20,
            'title' => 'AddГрПозже_'.uniqid('', true),
        ]);
        $earlier = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'order_by' => 1,
            'title' => 'AddГрРаньше_'.uniqid('', true),
        ]);
        $deleted = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'AddГрУдалена_'.uniqid('', true),
        ]);
        $deleted->delete();
        $foreign = Team::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'title' => 'AddГрЧужая_'.uniqid('', true),
        ]);

        $html = $this->get(route('chat.index'))->assertOk()->getContent();
        $start = strpos($html, 'id="addGroupMembersModal"');
        $this->assertNotFalse($start);
        $modal = substr($html, $start, 3500);

        $filterPos = strpos($modal, 'id="addGroupMembersTeamFilter"');
        $teamErrPos = strpos($modal, 'id="addGroupMembersTeamError"');
        $searchPos = strpos($modal, 'id="addGroupMembersSearch"');
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
        $this->assertStringContainsString('id="addGroupMembersForm"', $modal);
        $this->assertStringContainsString('data-error-for="user_ids"', $modal);
        $this->assertStringContainsString('data-error-for="team_id"', $modal);
        $this->assertStringContainsString('data-error-for="q"', $modal);
    }
}
