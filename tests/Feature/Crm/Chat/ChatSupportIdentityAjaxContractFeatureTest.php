<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Chat;

use App\Models\ChatParticipant;
use App\Models\ChatThread;
use App\Services\Chat\ChatSupportIdentity;

/**
 * P1: AJAX-контракт (X-Requested-With) — JSON, errors[field], нормализация id.
 *
 * UX-баг до фикса: unique() в prepareForValidation схлопывал extra+canonical в один id,
 * и POST группы отдавал 201 вместо 422 под user_ids.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ChatSupportIdentityAjaxContractFeatureTest extends ChatTestCase
{
    use InteractsWithChatSupportIdentity;

    public function test_ajax_store_thread_with_extra_id_returns_canonical_payload(): void
    {
        $canonical = $this->makeSupport('AjaxReuseКанон_', 'А');
        $extra = $this->makeSupport('AjaxReuseЛишний_', 'Б');

        $this->postJson(
            route('chat.api.threads.store'),
            ['user_id' => $extra->id],
            $this->chatAjaxHeaders()
        )
            ->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('created', true)
            ->assertJsonPath('thread.title', ChatSupportIdentity::DISPLAY_NAME)
            ->assertJsonPath('thread.peer_id', $canonical->id)
            ->assertJsonPath('thread.is_group', false)
            ->assertJsonStructure([
                'ok',
                'created',
                'thread_id',
                'thread' => ['id', 'title', 'avatar', 'peer_id', 'peer_is_online'],
            ]);
    }

    public function test_ajax_store_thread_with_canonical_then_reuses_same_thread(): void
    {
        $canonical = $this->makeSupport('AjaxЛичкаКанон_', 'А');

        $created = $this->postJson(
            route('chat.api.threads.store'),
            ['user_id' => $canonical->id],
            $this->chatAjaxHeaders()
        )->assertCreated();

        $threadId = (int) $created->json('thread_id');

        $this->postJson(
            route('chat.api.threads.store'),
            ['user_id' => $canonical->id],
            $this->chatAjaxHeaders()
        )
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('created', false)
            ->assertJsonPath('thread_id', $threadId)
            ->assertJsonPath('thread.title', ChatSupportIdentity::DISPLAY_NAME);
    }

    public function test_ajax_canonical_cannot_start_dialog_with_extra_id_mapped_to_self(): void
    {
        $canonical = $this->makeSupport('AjaxСамКанон_', 'А');
        $extra = $this->makeSupport('AjaxСамЛишний_', 'Б');
        $this->grantPermission($canonical, 'messages.view');
        $this->actingInPartner($canonical);

        $this->postJson(
            route('chat.api.threads.store'),
            ['user_id' => $extra->id],
            $this->chatAjaxHeaders()
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['user_id'])
            ->assertJsonPath('errors.user_id.0', 'Нельзя создать диалог с самим собой.');
    }

    public function test_ajax_foreign_regular_user_is_rejected_under_user_id_not_remapped(): void
    {
        $this->makeSupport('AjaxЧужойКанон_', 'А');

        $this->postJson(
            route('chat.api.threads.store'),
            ['user_id' => $this->foreignUser->id],
            $this->chatAjaxHeaders()
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['user_id'])
            ->assertJsonPath('errors.user_id.0', 'Нельзя добавить пользователя другой организации.');
    }

    public function test_ajax_contacts_payload_keeps_role_name_but_aliases_label(): void
    {
        $canonical = $this->makeSupport('AjaxРольКанон_', 'Секрет');
        $extra = $this->makeSupport('AjaxРольЛишний_', 'Тоже');

        $row = collect(
            $this->getJson(route('chat.api.users'), $this->chatAjaxHeaders())->assertOk()->json()
        )->firstWhere('id', $canonical->id);

        $this->assertNotNull($row);
        $this->assertSame(ChatSupportIdentity::DISPLAY_NAME, $row['name']);
        $this->assertSame(ChatSupportIdentity::DISPLAY_NAME, $row['role_label']);
        $this->assertSame('superadmin', $row['role_name']);
        $this->assertSame('', $row['email']);
        $this->assertSame('', $row['parent_full_name']);
        $this->assertNull(
            collect($this->getJson(route('chat.api.users'))->json())->firstWhere('id', $extra->id)
        );
        $this->assertStringNotContainsString($canonical->lastname, json_encode($row, JSON_UNESCAPED_UNICODE));
        $this->assertStringNotContainsString($canonical->email, json_encode($row, JSON_UNESCAPED_UNICODE));
    }

    public function test_ajax_support_card_hides_phone_parent_and_teams(): void
    {
        $canonical = $this->makeSupport('AjaxКарточка_', 'Секрет', null);

        $this->getJson(route('chat.api.users.show', $canonical), $this->chatAjaxHeaders())
            ->assertOk()
            ->assertJsonPath('id', $canonical->id)
            ->assertJsonPath('full_name', ChatSupportIdentity::DISPLAY_NAME)
            ->assertJsonPath('phone', '')
            ->assertJsonPath('parent_full_name', '')
            ->assertJsonPath('parent_phone', '')
            ->assertJsonPath('team_title', '')
            ->assertJsonStructure(['id', 'full_name', 'phone', 'parent_full_name', 'last_seen_label', 'team_title']);
    }

    public function test_ajax_group_create_with_extra_id_adds_canonical_not_extra(): void
    {
        $canonical = $this->makeSupport('AjaxГруппаКанон_', 'А');
        $extra = $this->makeSupport('AjaxГруппаЛишний_', 'Б');
        $peer = $this->makePeer('AjaxГруппаПир_');

        $created = $this->postJson(
            route('chat.api.threads.groups.store'),
            [
                'title' => 'AjaxСаГруппа',
                'user_ids' => [$peer->id, $extra->id],
            ],
            $this->chatAjaxHeaders()
        )
            ->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('created', true)
            ->assertJsonStructure(['ok', 'created', 'thread_id', 'thread' => ['id', 'title']]);

        $threadId = (int) $created->json('thread_id');
        $this->assertTrue(
            ChatParticipant::query()->where('thread_id', $threadId)->where('user_id', $canonical->id)->exists()
        );
        $this->assertFalse(
            ChatParticipant::query()->where('thread_id', $threadId)->where('user_id', $extra->id)->exists()
        );
    }

    public function test_ajax_group_create_with_extra_and_canonical_ids_returns_user_ids_duplicate_error(): void
    {
        $canonical = $this->makeSupport('AjaxДубльКанон_', 'А');
        $extra = $this->makeSupport('AjaxДубльЛишний_', 'Б');
        $peer = $this->makePeer('AjaxДубльПир_');

        $this->postJson(
            route('chat.api.threads.groups.store'),
            [
                'title' => 'AjaxДубльСа',
                'user_ids' => [$peer->id, $extra->id, $canonical->id],
            ],
            $this->chatAjaxHeaders()
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['user_ids']);

        $this->assertFalse(ChatThread::query()->where('subject', 'AjaxДубльСа')->exists());
    }

    public function test_ajax_add_extra_when_canonical_already_in_group_returns_already_member_error(): void
    {
        $admin = $this->createUserWithRole('admin');
        $this->grantPermission($admin, 'messages.view');
        $this->actingInPartner($admin);

        $canonical = $this->makeSupport('AjaxУжеКанон_', 'А');
        $extra = $this->makeSupport('AjaxУжеЛишний_', 'Б');
        $peer = $this->makePeer('AjaxУжеПир_');
        $thread = $this->createGroupThreadForUsers(
            [$admin->id, $peer->id, $canonical->id],
            'AjaxУжеСа'
        );

        $this->postJson(
            route('chat.api.threads.participants.store', $thread),
            ['user_ids' => [$extra->id]],
            $this->chatAjaxHeaders()
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['user_ids'])
            ->assertJsonPath('errors.user_ids.0', 'Этот пользователь уже в группе.');
    }

    public function test_ajax_add_extra_to_group_returns_message_and_canonical_member(): void
    {
        $admin = $this->createUserWithRole('admin');
        $this->grantPermission($admin, 'messages.view');
        $this->actingInPartner($admin);

        $canonical = $this->makeSupport('AjaxДобавитьКанон_', 'А');
        $extra = $this->makeSupport('AjaxДобавитьЛишний_', 'Б');
        $peer = $this->makePeer('AjaxДобавитьПир_');
        $thread = $this->createGroupThreadForUsers([$admin->id, $peer->id], 'AjaxДобавитьСа');

        $this->postJson(
            route('chat.api.threads.participants.store', $thread),
            ['user_ids' => [$extra->id]],
            $this->chatAjaxHeaders()
        )
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('message', 'Участники добавлены.');

        $this->assertTrue(
            ChatParticipant::query()
                ->where('thread_id', $thread->id)
                ->where('user_id', $canonical->id)
                ->exists()
        );
    }

    public function test_student_cannot_add_support_to_group(): void
    {
        $canonical = $this->makeSupport('AjaxСтудентКанон_', 'А');
        $peer = $this->makePeer('AjaxСтудентПир_');
        $thread = $this->createGroupThreadForUsers(
            [$this->user->id, $peer->id],
            'AjaxСтудентСа'
        );

        $this->postJson(
            route('chat.api.threads.participants.store', $thread),
            ['user_ids' => [$canonical->id]],
            $this->chatAjaxHeaders()
        )
            ->assertForbidden();

        $this->assertFalse(
            ChatParticipant::query()
                ->where('thread_id', $thread->id)
                ->where('user_id', $canonical->id)
                ->exists()
        );
    }
}
