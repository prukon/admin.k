<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Chat;

use App\Models\ChatParticipant;
use App\Models\ChatThread;
use App\Models\Team;
use App\Services\Chat\ChatSupportIdentity;
use Illuminate\Support\Facades\Auth;

/**
 * Название партнёра в JSON карточки контакта и состава группы.
 * Не в inbox / show шапки / списке контактов.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ChatPartnerNameFeatureTest extends ChatTestCase
{
    use InteractsWithChatSupportIdentity;

    public function test_guest_cannot_read_peer_card_partner_name(): void
    {
        $peer = $this->makePeer('PnGuest_');
        Auth::logout();

        $json = $this->getJson(route('chat.api.users.show', $peer));
        $this->assertNotSame(500, $json->getStatusCode());
        $json->assertUnauthorized();
    }

    public function test_user_without_messages_view_cannot_read_peer_card(): void
    {
        $denied = $this->createUserWithoutPermission('messages.view', $this->partner);
        $peer = $this->makePeer('PnDenied_');
        $this->actingInPartner($denied);

        $this->getJson(route('chat.api.users.show', $peer))->assertForbidden();
    }

    public function test_peer_card_returns_current_school_title(): void
    {
        $this->partner->forceFill(['title' => 'Школа ПартнёрЧат'])->save();
        $peer = $this->makePeer('PnPeer_', [
            'lastname' => 'ИвановPn',
            'name' => 'Иван',
        ]);

        $this->getJson(route('chat.api.users.show', $peer))
            ->assertOk()
            ->assertJsonPath('full_name', 'ИвановPn Иван')
            ->assertJsonPath('partner_name', 'Школа ПартнёрЧат');
    }

    public function test_own_card_json_still_has_partner_name_for_account_tab_to_ignore(): void
    {
        $this->partner->forceFill(['title' => 'СвояШколаЧат'])->save();

        $this->getJson(route('chat.api.users.show', $this->user))
            ->assertOk()
            ->assertJsonPath('id', (int) $this->user->id)
            ->assertJsonPath('partner_name', 'СвояШколаЧат');
    }

    public function test_contacts_list_and_inbox_and_open_thread_omit_partner_name(): void
    {
        $peer = $this->makePeer('PnOmit_');
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id], 'PnOmit');

        $contacts = $this->getJson(route('chat.api.users'))->assertOk()->json();
        $this->assertIsArray($contacts);
        $this->assertNotEmpty($contacts);
        foreach ($contacts as $row) {
            $this->assertArrayNotHasKey('partner_name', $row, 'Список контактов не отдаёт partner_name');
        }

        $inboxRow = collect($this->getJson(route('chat.api.threads.index'))->assertOk()->json('threads'))
            ->firstWhere('id', $thread->id);
        $this->assertIsArray($inboxRow);
        $this->assertArrayNotHasKey('partner_name', $inboxRow);

        $show = $this->getJson(route('chat.api.threads.show', $thread))->assertOk()->json('thread');
        $this->assertIsArray($show);
        $this->assertArrayNotHasKey('partner_name', $show);
    }

    public function test_group_members_json_includes_partner_title(): void
    {
        $this->partner->forceFill(['title' => 'ГруппаПартнёрЧат'])->save();
        $admin = $this->createUserWithRole('admin');
        $this->grantPermission($admin, 'messages.view');
        $a = $this->makePeer('PnGa_');
        $thread = $this->createGroupThreadForUsers([$admin->id, $a->id, $this->user->id], 'PnGroup');
        $this->actingInPartner($admin);

        $this->getJson(route('chat.api.threads.participants.index', $thread))
            ->assertOk()
            ->assertJsonPath('thread.partner_name', 'ГруппаПартнёрЧат')
            ->assertJsonPath('thread.title', 'PnGroup');
    }

    public function test_team_group_chat_members_json_includes_partner_title(): void
    {
        $this->partner->forceFill(['title' => 'УчебнаяПартнёрЧат'])->save();
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'УчебнаяPn',
        ]);
        $admin = $this->createUserWithRole('admin');
        $this->grantPermission($admin, 'messages.view');
        $thread = ChatThread::query()->create([
            'subject' => 'УчебнаяPn',
            'is_group' => true,
            'team_id' => $team->id,
        ]);
        foreach ([$admin->id, $this->user->id] as $userId) {
            ChatParticipant::query()->create([
                'thread_id' => $thread->id,
                'user_id' => $userId,
            ]);
        }
        $this->actingInPartner($admin);

        $this->getJson(route('chat.api.threads.participants.index', $thread))
            ->assertOk()
            ->assertJsonPath('thread.partner_name', 'УчебнаяПартнёрЧат')
            ->assertJsonPath('thread.title', 'УчебнаяPn');
    }

    public function test_support_card_partner_name_is_empty(): void
    {
        $canonical = $this->makeSupport('PnСа_', 'Секрет', null);

        $this->getJson(route('chat.api.users.show', $canonical))
            ->assertOk()
            ->assertJsonPath('full_name', ChatSupportIdentity::DISPLAY_NAME)
            ->assertJsonPath('partner_name', '');
    }

    public function test_foreign_peer_card_is_403(): void
    {
        $foreign = $this->makePeer('PnForeign_', ['partner_id' => $this->foreignPartner->id]);

        $this->getJson(route('chat.api.users.show', $foreign))->assertForbidden();
    }

    public function test_superadmin_sees_session_partner_title_on_card_and_group(): void
    {
        $this->foreignPartner->forceFill(['title' => 'ЧужаяШколаЧат'])->save();
        $this->asSuperadmin();
        $this->withSession([
            'current_partner' => (int) $this->foreignPartner->id,
            '2fa:passed' => true,
        ]);

        $this->getJson(route('chat.api.users.show', $this->foreignUser))
            ->assertOk()
            ->assertJsonPath('partner_name', 'ЧужаяШколаЧат');

        $peer = $this->makePeer('PnSaGroup_', ['partner_id' => $this->foreignPartner->id]);
        $thread = $this->createGroupThreadForUsers([(int) $this->user->id, (int) $this->foreignUser->id, $peer->id], 'PnSa');

        $this->getJson(route('chat.api.threads.participants.index', $thread))
            ->assertOk()
            ->assertJsonPath('thread.partner_name', 'ЧужаяШколаЧат');
    }
}
