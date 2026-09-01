<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Chat;

use App\Services\Chat\ChatSupportIdentity;
use App\Services\TeamUserSyncService;

/**
 * В чате superadmin виден как одна «Служба поддержки»: контакты, личка, состав, карточка.
 */
final class ChatSupportIdentityFeatureTest extends ChatTestCase
{
    use InteractsWithChatSupportIdentity;

    public function test_contacts_include_single_support_alias_and_hide_extra_superadmin(): void
    {
        $canonical = $this->makeSupport('КанонСекрет_', 'ИванСекрет');
        $extra = $this->makeSupport('ЛишнийСекрет_', 'ПётрСекрет');

        $contacts = collect($this->getJson(route('chat.api.users'))->assertOk()->json());
        $supportRows = $contacts->where('name', ChatSupportIdentity::DISPLAY_NAME)->values();

        $this->assertCount(1, $supportRows);
        $this->assertSame((int) $canonical->id, (int) $supportRows[0]['id']);
        $this->assertSame(ChatSupportIdentity::DISPLAY_NAME, $supportRows[0]['role_label']);
        $this->assertSame('', $supportRows[0]['email']);
        $this->assertSame('', $supportRows[0]['parent_full_name']);
        $this->assertNull($contacts->firstWhere('id', $extra->id));
        $this->assertNull($contacts->firstWhere('name', $canonical->full_name));
        $this->assertSame($supportRows[0], $contacts->first(), 'Служба поддержки первой строкой контактов');
    }

    public function test_contacts_search_matches_support_label_not_real_fio(): void
    {
        $canonical = $this->makeSupport('НеНайтиФамилию_', 'СекретИмя');

        $byLabel = collect($this->getJson(route('chat.api.users', ['q' => 'поддержк']))->assertOk()->json());
        $this->assertNotNull($byLabel->firstWhere('id', $canonical->id));
        $this->assertSame(ChatSupportIdentity::DISPLAY_NAME, $byLabel->firstWhere('id', $canonical->id)['name']);

        $byReal = collect($this->getJson(route('chat.api.users', ['q' => 'НеНайтиФамилию_']))->assertOk()->json());
        $this->assertNull($byReal->firstWhere('id', $canonical->id));
    }

    public function test_support_is_in_none_filter_not_in_specific_team(): void
    {
        $canonical = $this->makeSupport('ФильтрСа_', 'Са');
        $team = \App\Models\Team::factory()->create(['partner_id' => $this->partner->id]);
        $kid = $this->makePeer('KidForSupportFilter_');
        app(TeamUserSyncService::class)->syncTeamsForStudent($kid, [(int) $team->id]);

        $none = collect($this->getJson(route('chat.api.users', ['team_id' => 'none']))->assertOk()->json());
        $this->assertNotNull($none->firstWhere('id', $canonical->id));

        $teamRows = collect($this->getJson(route('chat.api.users', ['team_id' => $team->id]))->assertOk()->json());
        $this->assertNull($teamRows->firstWhere('id', $canonical->id));
        $this->assertNotNull($teamRows->firstWhere('id', $kid->id));
    }

    public function test_private_thread_with_null_partner_support_uses_alias(): void
    {
        $canonical = $this->makeSupport('ЛичкаСа_', 'Личка', null);

        $created = $this->postJson(route('chat.api.threads.store'), ['user_id' => $canonical->id])
            ->assertCreated()
            ->assertJsonPath('thread.title', ChatSupportIdentity::DISPLAY_NAME)
            ->assertJsonPath('thread.peer_id', $canonical->id);

        $threadId = (int) $created->json('thread_id');
        $this->getJson(route('chat.api.threads.show', $threadId))
            ->assertOk()
            ->assertJsonPath('thread.title', ChatSupportIdentity::DISPLAY_NAME);

        $inbox = collect($this->getJson(route('chat.api.threads.index'))->assertOk()->json('threads'));
        $row = $inbox->firstWhere('id', $threadId);
        $this->assertNotNull($row);
        $this->assertSame(ChatSupportIdentity::DISPLAY_NAME, $row['title']);
    }

    public function test_store_thread_with_extra_superadmin_id_reuses_canonical(): void
    {
        $canonical = $this->makeSupport('ReuseCanon_', 'А');
        $extra = $this->makeSupport('ReuseExtra_', 'Б');

        $created = $this->postJson(route('chat.api.threads.store'), ['user_id' => $extra->id])
            ->assertCreated();

        $this->assertSame((int) $canonical->id, (int) $created->json('thread.peer_id'));
        $this->assertSame(ChatSupportIdentity::DISPLAY_NAME, $created->json('thread.title'));
    }

    public function test_support_card_is_masked_and_extra_superadmin_card_is_403(): void
    {
        $canonical = $this->makeSupport('КарточкаСа_', 'Секрет', null);
        $extra = $this->makeSupport('КарточкаЛишний_', 'ТожеСекрет');

        $this->getJson(route('chat.api.users.show', $canonical))
            ->assertOk()
            ->assertJsonPath('full_name', ChatSupportIdentity::DISPLAY_NAME)
            ->assertJsonPath('phone', '')
            ->assertJsonPath('parent_full_name', '')
            ->assertJsonPath('parent_phone', '')
            ->assertJsonPath('team_title', '')
            ->assertJsonPath('partner_name', '');

        $this->getJson(route('chat.api.users.show', $extra))->assertForbidden();
    }

    public function test_group_members_show_one_support_alias_and_hide_extra(): void
    {
        $this->grantPermission($this->user, 'messages.view');
        $admin = $this->createUserWithRole('admin', $this->partner, [
            'lastname' => 'АдминовЧатСа',
            'name' => 'Андрей',
        ]);
        $this->grantPermission($admin, 'messages.view');
        $canonical = $this->makeSupport('СоставКанон_', 'К');
        $extra = $this->makeSupport('СоставЛишний_', 'Л');
        $peer = $this->makePeer('СоставКлиент_', [
            'lastname' => 'ЯяяКлиентСа',
            'name' => 'Иван',
        ]);

        $thread = $this->createGroupThreadForUsers(
            [$admin->id, $canonical->id, $extra->id, $peer->id],
            'СоставСа'
        );
        $this->actingInPartner($admin);

        $res = $this->getJson(route('chat.api.threads.participants.index', $thread))
            ->assertOk()
            ->assertJsonPath('thread.members_total', 3);

        $members = collect($res->json('members'));
        $this->assertCount(3, $members);
        $this->assertNotNull($members->firstWhere('id', $canonical->id));
        $this->assertSame(
            ChatSupportIdentity::DISPLAY_NAME,
            $members->firstWhere('id', $canonical->id)['full_name']
        );
        $this->assertSame(
            ChatSupportIdentity::DISPLAY_NAME,
            $members->firstWhere('id', $canonical->id)['role_label']
        );
        $this->assertNull($members->firstWhere('id', $extra->id));
        $this->assertNull($members->firstWhere('full_name', $canonical->full_name));
    }

    public function test_superadmin_also_sees_support_alias_in_own_private_thread(): void
    {
        $canonical = $this->makeSupport('СамСа_', 'Секрет');
        $this->grantPermission($canonical, 'messages.view');
        $this->actingInPartner($canonical);

        $created = $this->postJson(route('chat.api.threads.store'), ['user_id' => $this->user->id])
            ->assertCreated();
        $threadId = (int) $created->json('thread_id');

        $this->actingInPartner($this->user);
        $this->getJson(route('chat.api.threads.show', $threadId))
            ->assertOk()
            ->assertJsonPath('thread.title', ChatSupportIdentity::DISPLAY_NAME);

        $this->actingInPartner($canonical);
        $inbox = collect($this->getJson(route('chat.api.threads.index'))->assertOk()->json('threads'));
        $mine = $inbox->firstWhere('id', $threadId);
        $this->assertNotNull($mine);
        $this->assertSame($this->user->full_name, $mine['title']);
    }

    public function test_extra_superadmin_private_thread_is_hidden_from_inbox(): void
    {
        $canonical = $this->makeSupport('InboxCanon_', 'А');
        $extra = $this->makeSupport('InboxExtra_', 'Б');
        $thread = $this->createThreadForUsers([$this->user->id, $extra->id], 'ЛишнийСА');

        $inbox = collect($this->getJson(route('chat.api.threads.index'))->assertOk()->json('threads'));
        $this->assertNull($inbox->firstWhere('id', $thread->id));
        $this->assertNotNull($canonical);
    }

    public function test_disabled_canonical_is_replaced_by_next_enabled_superadmin(): void
    {
        $first = $this->makeSupport('ВыклКанон_', 'А');
        $next = $this->makeSupport('НовыйКанон_', 'Б');
        $first->forceFill(['is_enabled' => 0])->save();

        $contacts = collect($this->getJson(route('chat.api.users'))->assertOk()->json());
        $supportRows = $contacts->where('name', ChatSupportIdentity::DISPLAY_NAME)->values();

        $this->assertCount(1, $supportRows);
        $this->assertSame((int) $next->id, (int) $supportRows[0]['id']);
        $this->assertNull($contacts->firstWhere('id', $first->id));
        $this->assertNull($contacts->firstWhere('name', $next->full_name));
    }

    public function test_contacts_have_no_support_row_when_all_superadmins_are_disabled(): void
    {
        $only = $this->makeSupport('ВсеВыклСа_', 'А');
        $only->forceFill(['is_enabled' => 0])->save();

        $contacts = collect($this->getJson(route('chat.api.users'))->assertOk()->json());
        $this->assertNull($contacts->firstWhere('name', ChatSupportIdentity::DISPLAY_NAME));
        $this->assertNull($contacts->firstWhere('id', $only->id));
    }

    public function test_canonical_does_not_see_themselves_in_contacts(): void
    {
        $canonical = $this->makeSupport('СебяСа_', 'А');
        $this->grantPermission($canonical, 'messages.view');
        $this->actingInPartner($canonical);

        $contacts = collect($this->getJson(route('chat.api.users'))->assertOk()->json());
        $this->assertNull($contacts->firstWhere('id', $canonical->id));
        $this->assertNull($contacts->firstWhere('name', ChatSupportIdentity::DISPLAY_NAME));
    }

    public function test_regular_admin_keeps_real_name_and_is_not_relabeled_as_support(): void
    {
        $this->makeSupport('АдминНеСаКанон_', 'А');
        $admin = $this->createUserWithRole('admin', $this->partner, [
            'lastname' => 'НеПоддержка_'.uniqid('', true),
            'name' => 'Ольга',
            'is_enabled' => 1,
        ]);

        $row = collect($this->getJson(route('chat.api.users'))->assertOk()->json())
            ->firstWhere('id', $admin->id);
        $this->assertNotNull($row);
        $this->assertSame($admin->full_name, $row['name']);
        $this->assertNotSame(ChatSupportIdentity::DISPLAY_NAME, $row['name']);
        $this->assertNotSame(ChatSupportIdentity::DISPLAY_NAME, $row['role_label']);
        $this->assertSame('admin', $row['role_name']);
    }

    public function test_support_is_hidden_from_add_members_picker_when_already_in_group(): void
    {
        $canonical = $this->makeSupport('ИсключитьСа_', 'А');
        $peer = $this->makePeer('ИсключитьПир_');
        $thread = $this->createGroupThreadForUsers(
            [$this->user->id, $peer->id, $canonical->id],
            'ИсключитьСа'
        );

        $rows = collect(
            $this->getJson(route('chat.api.users', ['exclude_thread_id' => $thread->id]))
                ->assertOk()
                ->json()
        );
        $this->assertNull($rows->firstWhere('id', $canonical->id));

        $without = collect($this->getJson(route('chat.api.users'))->assertOk()->json());
        $this->assertNotNull($without->firstWhere('id', $canonical->id));
    }

    public function test_other_school_sees_the_same_canonical_support(): void
    {
        $canonical = $this->makeSupport('ЧужаяШколаСа_', 'А', null);

        $this->actingInPartner($this->foreignUser, $this->foreignPartner);
        $rows = collect($this->getJson(route('chat.api.users'))->assertOk()->json());
        $this->assertNotNull($rows->firstWhere('id', $canonical->id));
        $this->assertSame(
            ChatSupportIdentity::DISPLAY_NAME,
            $rows->firstWhere('id', $canonical->id)['name']
        );
    }

    public function test_chat_page_does_not_leak_superadmin_fio_in_html(): void
    {
        $canonical = $this->makeSupport('УтечкаHtmlСа_', 'Секрет');
        $extra = $this->makeSupport('УтечкаHtmlЛишний_', 'Тоже');

        $html = $this->get(route('chat.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString($canonical->lastname, $html);
        $this->assertStringNotContainsString($extra->lastname, $html);
        $this->assertStringNotContainsString($canonical->email, $html);
    }

    public function test_canonical_and_extra_see_support_alias_on_own_account_card(): void
    {
        $canonical = $this->makeSupport('СвояКарточкаКанон_', 'Секрет');
        $extra = $this->makeSupport('СвояКарточкаЛишний_', 'ТожеСекрет');

        $this->grantPermission($canonical, 'messages.view');
        $this->actingInPartner($canonical);
        $this->getJson(route('chat.api.users.show', $canonical))
            ->assertOk()
            ->assertJsonPath('full_name', ChatSupportIdentity::DISPLAY_NAME)
            ->assertJsonPath('phone', '');

        $this->grantPermission($extra, 'messages.view');
        $this->actingInPartner($extra);
        $this->getJson(route('chat.api.users.show', $extra))
            ->assertOk()
            ->assertJsonPath('full_name', ChatSupportIdentity::DISPLAY_NAME);
        $this->assertStringNotContainsString(
            $extra->lastname,
            (string) $this->getJson(route('chat.api.users.show', $extra))->getContent()
        );
    }

    public function test_opening_hidden_extra_private_thread_still_shows_support_title_not_fio(): void
    {
        $canonical = $this->makeSupport('СкрытаяЛичкаКанон_', 'А');
        $extra = $this->makeSupport('СкрытаяЛичкаЛишний_', 'Б');
        $thread = $this->createThreadForUsers([$this->user->id, $extra->id], 'СкрытаяЛичкаСа');

        $this->getJson(route('chat.api.threads.show', $thread))
            ->assertOk()
            ->assertJsonPath('thread.title', ChatSupportIdentity::DISPLAY_NAME);
        $this->assertStringNotContainsString(
            $extra->lastname,
            (string) $this->getJson(route('chat.api.threads.show', $thread))->getContent()
        );
        $this->assertNotNull($canonical);
    }
}
