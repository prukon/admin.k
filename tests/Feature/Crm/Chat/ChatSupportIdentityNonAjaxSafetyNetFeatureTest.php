<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Chat;

use App\Models\ChatParticipant;
use App\Models\ChatThread;
use App\Services\Chat\ChatSupportIdentity;

/**
 * P1: нативный POST/GET без X-Requested-With — не сырой JSON 200, запись есть.
 *
 * UX-баг: клик по «Служба поддержки» без JS не должен оставить белый экран с JSON.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ChatSupportIdentityNonAjaxSafetyNetFeatureTest extends ChatTestCase
{
    use InteractsWithChatSupportIdentity;

    public function test_non_ajax_store_thread_with_support_redirects_and_creates_dialog(): void
    {
        $canonical = $this->makeSupport('НативКанон_', 'А');

        $response = $this->from(route('chat.index'))
            ->post(route('chat.api.threads.store'), [
                'user_id' => $canonical->id,
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode(), 'Нативный POST не должен отдавать сырой JSON 200');
        $this->assertNotSame(201, $response->getStatusCode(), 'Нативный POST не должен отдавать JSON 201');
        $response->assertRedirect(route('chat.index'));

        $this->assertTrue(
            ChatThread::query()
                ->where('is_group', false)
                ->whereHas('participants', fn ($q) => $q->where('user_id', $this->user->id))
                ->whereHas('participants', fn ($q) => $q->where('user_id', $canonical->id))
                ->exists()
        );
    }

    public function test_non_ajax_store_thread_with_extra_superadmin_creates_canonical_not_extra(): void
    {
        $canonical = $this->makeSupport('НативReuseКанон_', 'А');
        $extra = $this->makeSupport('НативReuseЛишний_', 'Б');

        $response = $this->from(route('chat.index'))
            ->post(route('chat.api.threads.store'), [
                'user_id' => $extra->id,
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertNotSame(201, $response->getStatusCode());
        $response->assertRedirect(route('chat.index'));

        $thread = ChatThread::query()->where('is_group', false)->first();
        $this->assertNotNull($thread);
        $this->assertTrue(
            ChatParticipant::query()
                ->where('thread_id', $thread->id)
                ->where('user_id', $canonical->id)
                ->exists()
        );
        $this->assertFalse(
            ChatParticipant::query()
                ->where('thread_id', $thread->id)
                ->where('user_id', $extra->id)
                ->exists(),
            'Лишний superadmin не должен попасть в личку — только канонический'
        );
    }

    public function test_non_ajax_canonical_cannot_open_dialog_with_extra_mapped_to_self(): void
    {
        $canonical = $this->makeSupport('НативСамКанон_', 'А');
        $extra = $this->makeSupport('НативСамЛишний_', 'Б');
        $this->grantPermission($canonical, 'messages.view');
        $this->actingInPartner($canonical);

        $response = $this->from(route('chat.index'))
            ->post(route('chat.api.threads.store'), [
                'user_id' => $extra->id,
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertRedirect(route('chat.index'));
        $response->assertSessionHasErrors(['user_id']);
        $this->assertSame(0, ChatThread::query()->where('is_group', false)->count());
    }

    public function test_non_ajax_store_group_with_extra_superadmin_redirects_and_adds_canonical(): void
    {
        $canonical = $this->makeSupport('НативГруппаКанон_', 'А');
        $extra = $this->makeSupport('НативГруппаЛишний_', 'Б');
        $peer = $this->makePeer('НативГруппаПир_');

        $response = $this->from(route('chat.index'))
            ->post(route('chat.api.threads.groups.store'), [
                'title' => 'НативСаГруппа',
                'user_ids' => [$peer->id, $extra->id],
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $this->assertNotSame(201, $response->getStatusCode());
        $response->assertRedirect(route('chat.index'));

        $thread = ChatThread::query()->where('is_group', true)->where('subject', 'НативСаГруппа')->first();
        $this->assertNotNull($thread);
        $this->assertTrue(
            ChatParticipant::query()
                ->where('thread_id', $thread->id)
                ->where('user_id', $canonical->id)
                ->exists()
        );
        $this->assertFalse(
            ChatParticipant::query()
                ->where('thread_id', $thread->id)
                ->where('user_id', $extra->id)
                ->exists()
        );
    }

    public function test_non_ajax_group_with_extra_and_canonical_ids_redirects_with_user_ids_error(): void
    {
        $canonical = $this->makeSupport('НативДубльКанон_', 'А');
        $extra = $this->makeSupport('НативДубльЛишний_', 'Б');
        $peer = $this->makePeer('НативДубльПир_');

        $response = $this->from(route('chat.index'))
            ->post(route('chat.api.threads.groups.store'), [
                'title' => 'НативДубльСа',
                'user_ids' => [$peer->id, $extra->id, $canonical->id],
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertRedirect(route('chat.index'));
        $response->assertSessionHasErrors(['user_ids']);
        $this->assertFalse(
            ChatThread::query()->where('subject', 'НативДубльСа')->exists(),
            'Повтор канонического после нормализации не должен создать группу'
        );
    }

    public function test_non_ajax_add_extra_superadmin_to_group_redirects_and_adds_canonical(): void
    {
        $admin = $this->createUserWithRole('admin');
        $this->grantPermission($admin, 'messages.view');
        $this->actingInPartner($admin);

        $canonical = $this->makeSupport('НативДобавитьКанон_', 'А');
        $extra = $this->makeSupport('НативДобавитьЛишний_', 'Б');
        $peer = $this->makePeer('НативДобавитьПир_');
        $thread = $this->createGroupThreadForUsers([$admin->id, $peer->id], 'НативДобавитьСа');

        $response = $this->from(route('chat.index'))
            ->post(route('chat.api.threads.participants.store', $thread), [
                'user_ids' => [$extra->id],
            ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode());
        $response->assertRedirect(route('chat.index'));
        $this->assertTrue(
            ChatParticipant::query()
                ->where('thread_id', $thread->id)
                ->where('user_id', $canonical->id)
                ->exists()
        );
        $this->assertFalse(
            ChatParticipant::query()
                ->where('thread_id', $thread->id)
                ->where('user_id', $extra->id)
                ->exists()
        );
    }

    public function test_native_get_contacts_returns_json_with_support_not_empty_page(): void
    {
        $canonical = $this->makeSupport('НативКонтакты_', 'А');

        $response = $this->get(route('chat.api.users'));
        $this->assertNotSame(500, $response->getStatusCode());
        $response->assertOk();
        $this->assertStringContainsString(
            'application/json',
            (string) $response->headers->get('content-type')
        );
        $this->assertNotSame('', trim((string) $response->getContent()));
        $this->assertTrue(array_is_list($response->json()));
        $row = collect($response->json())->firstWhere('id', $canonical->id);
        $this->assertNotNull($row);
        $this->assertSame(ChatSupportIdentity::DISPLAY_NAME, $row['name']);
        $this->assertSame(ChatSupportIdentity::DISPLAY_NAME, $row['role_label']);
    }

    public function test_native_get_support_card_returns_json_mask_not_empty_page(): void
    {
        $canonical = $this->makeSupport('НативКарточка_', 'Секрет');

        $response = $this->get(route('chat.api.users.show', $canonical));
        $this->assertNotSame(500, $response->getStatusCode());
        $response
            ->assertOk()
            ->assertJsonPath('full_name', ChatSupportIdentity::DISPLAY_NAME)
            ->assertJsonPath('phone', '');
        $this->assertStringContainsString(
            'application/json',
            (string) $response->headers->get('content-type')
        );
        $this->assertNotSame('', trim((string) $response->getContent()));
        $this->assertStringNotContainsString($canonical->lastname, (string) $response->getContent());
    }

    public function test_native_get_extra_superadmin_card_is_forbidden_not_empty_200(): void
    {
        $this->makeSupport('НативЛишнийКанон_', 'А');
        $extra = $this->makeSupport('НативЛишнийКарточка_', 'Б');

        $response = $this->get(route('chat.api.users.show', $extra));
        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode(), 'Карточка лишнего SA не должна быть пустым 200');
        $response->assertForbidden();
    }
}
