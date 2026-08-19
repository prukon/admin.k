<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Chat;

use App\Models\ParentProfile;
use Illuminate\Support\Facades\Auth;

/**
 * P1: мобильные контакты и модалка «Участники группы» — HTTP/API и разметка,
 * которые кормят выравнивание ФИО и список участников.
 *
 * UX-баг до фикса: форма визарда растягивала оверлей списком учеников,
 * а GET show чужого треда клиент показывал, пока не приходил новый JSON.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ChatMobileContactsAlignFeatureTest extends ChatTestCase
{
    public function test_guest_cannot_open_chat_or_load_contacts_for_members_modal(): void
    {
        Auth::logout();

        $page = $this->get(route('chat.index'));
        $this->assertNotSame(500, $page->getStatusCode());
        $this->assertNotSame(200, $page->getStatusCode(), 'Гость не должен видеть /chat');
        $this->assertTrue($page->isRedirect());
        $html = (string) $page->getContent();
        $this->assertStringNotContainsString('id="createGroupMembersModal"', $html);
        $this->assertStringNotContainsString('id="chatPaneContacts"', $html);
        $this->assertStringNotContainsString('id="createGroupMembersList"', $html);

        $json = $this->getJson(route('chat.api.users'));
        $this->assertNotSame(500, $json->getStatusCode());
        $json->assertUnauthorized();

        $htmlUsers = $this->get(route('chat.api.users'));
        $this->assertNotSame(500, $htmlUsers->getStatusCode());
        $this->assertTrue($htmlUsers->isRedirect());
        $this->assertGuest();
    }

    public function test_user_without_messages_view_cannot_open_chat_or_contacts(): void
    {
        $denied = $this->createUserWithoutPermission('messages.view', $this->partner);
        $this->actingInPartner($denied);

        $page = $this->get(route('chat.index'));
        $this->assertSame(403, $page->getStatusCode());
        $this->assertStringNotContainsString('id="createGroupMembersModal"', $page->getContent());

        $json = $this->getJson(route('chat.api.users'));
        $this->assertSame(403, $json->getStatusCode());

        $htmlUsers = $this->get(route('chat.api.users'));
        $this->assertSame(403, $htmlUsers->getStatusCode());
    }

    public function test_user_with_messages_view_sees_contacts_pane_and_members_form_wrapping_footer(): void
    {
        $html = $this->get(route('chat.index'))->assertOk()->getContent();

        $this->assertStringContainsString('id="chatPaneContacts"', $html);
        $this->assertStringContainsString('id="contactsList"', $html);
        $this->assertStringContainsString('id="createGroupMembersModal"', $html);
        $this->assertStringContainsString('id="createGroupMembersForm"', $html);
        $this->assertStringContainsString('id="createGroupMembersList"', $html);
        $this->assertStringContainsString('class="contact-list"', $html);
        $this->assertSame(2, substr_count($html, 'js-open-create-group'));
        $this->assertStringContainsString('id="openCreateGroupBtn"', $html);
        $this->assertStringContainsString('id="openCreateGroupMobileBtn"', $html);

        $start = strpos($html, 'id="createGroupMembersModal"');
        $this->assertNotFalse($start);
        $modal = substr($html, $start, 2200);
        $formPos = strpos($modal, 'id="createGroupMembersForm"');
        $bodyPos = strpos($modal, 'class="modal-body"');
        $listPos = strpos($modal, 'id="createGroupMembersList"');
        $footerPos = strpos($modal, 'class="modal-footer"');
        $this->assertNotFalse($formPos);
        $this->assertNotFalse($bodyPos);
        $this->assertNotFalse($listPos);
        $this->assertNotFalse($footerPos);
        $this->assertLessThan($bodyPos, $formPos, 'Форма должна оборачивать body, иначе flex на content не сжимает список');
        $this->assertLessThan($listPos, $bodyPos);
        $this->assertLessThan($footerPos, $listPos, 'Футер с «Отмена»/«Создать» должен быть после списка, внутри формы');
        $this->assertStringContainsString('Отмена', $modal);
        $this->assertStringContainsString('Создать', $modal);
        $this->assertStringContainsString('data-error-for="user_ids"', $modal);
        $this->assertStringContainsString('data-error-for="q"', $modal);
        $this->assertStringContainsString('data-error-for="team_id"', $modal);
        $this->assertStringNotContainsString('modal-fullscreen', $modal);
        $this->assertStringNotContainsString('modal-xl', $modal);
        $this->assertStringContainsString('class="modal-dialog"', $modal);
    }

    public function test_admin_and_trainer_with_messages_view_also_see_members_modal_markup(): void
    {
        $admin = $this->createUserWithRole('admin');
        $this->actingInPartner($admin);
        $adminHtml = $this->get(route('chat.index'))->assertOk()->getContent();
        $this->assertStringContainsString('id="createGroupMembersForm"', $adminHtml);
        $this->assertStringContainsString('id="chatPaneContacts"', $adminHtml);

        $trainer = $this->createUserWithRole('trainer');
        $this->actingInPartner($trainer);
        $trainerHtml = $this->get(route('chat.index'))->assertOk()->getContent();
        $this->assertStringContainsString('id="createGroupMembersForm"', $trainerHtml);
        $this->assertStringContainsString('js-open-create-group', $trainerHtml);
    }

    public function test_contacts_json_includes_client_name_and_parent_for_both_mobile_lists(): void
    {
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'РодитФамМоб_',
            'firstname' => 'РодитИмяМоб',
            'middlename' => 'ОтчМоб',
        ]);
        $kid = $this->makePeer('KidAlign_', [
            'lastname' => 'УченФамМоб',
            'name' => 'УченИмяМоб',
            'parent_id' => $parent->id,
        ]);
        $staff = $this->makePeer('StaffAlign_');

        $json = $this->getJson(route('chat.api.users'));
        $this->assertNotSame(500, $json->getStatusCode());
        $json->assertOk()->assertJsonStructure([
            '*' => ['id', 'name', 'avatar', 'role_label', 'team_title', 'parent_full_name'],
        ]);
        $this->assertNotSame('', trim((string) $json->getContent()));

        $rows = collect($json->json());
        $kidRow = $rows->firstWhere('id', $kid->id);
        $staffRow = $rows->firstWhere('id', $staff->id);
        $this->assertNotNull($kidRow);
        $this->assertNotNull($staffRow);
        $this->assertSame('УченФамМоб УченИмяМоб', $kidRow['name']);
        $this->assertSame('РодитФамМоб_ РодитИмяМоб ОтчМоб', $kidRow['parent_full_name']);
        $this->assertSame('', $staffRow['parent_full_name']);
    }

    public function test_contacts_json_does_not_leak_foreign_school_students(): void
    {
        $own = $this->makePeer('OwnAlign_');
        $foreign = $this->makePeer('ForeignAlign_', ['partner_id' => $this->foreignPartner->id]);

        $ids = collect($this->getJson(route('chat.api.users'))->assertOk()->json())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->assertContains((int) $own->id, $ids);
        $this->assertNotContains((int) $foreign->id, $ids);

        $this->asForeignUser();
        $foreignIds = collect($this->getJson(route('chat.api.users'))->assertOk()->json())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $this->assertNotContains((int) $own->id, $foreignIds);
    }

    public function test_oversized_contacts_search_returns_field_error_under_q(): void
    {
        $tooLong = str_repeat('я', 121);

        $json = $this->getJson(route('chat.api.users', ['q' => $tooLong]));
        $this->assertNotSame(500, $json->getStatusCode());
        $this->assertNotSame(200, $json->getStatusCode(), 'Слишком длинный поиск не пустой 200');
        $json->assertStatus(422)
            ->assertJsonValidationErrors(['q'])
            ->assertJsonPath('errors.q.0', 'Строка поиска слишком длинная (максимум 120 символов).');

        $native = $this->from(route('chat.index'))
            ->get(route('chat.api.users', ['q' => $tooLong]));
        $this->assertNotSame(500, $native->getStatusCode());
        $this->assertNotSame(200, $native->getStatusCode(), 'Нативный длинный q не пустой список 200');
        if ($native->isRedirect()) {
            $native->assertSessionHasErrors(['q']);

            return;
        }
        $native->assertStatus(422)->assertJsonValidationErrors(['q']);
    }

    public function test_contacts_search_at_max_length_still_returns_json_list(): void
    {
        $json = $this->getJson(route('chat.api.users', ['q' => str_repeat('я', 120)]));
        $this->assertNotSame(500, $json->getStatusCode());
        $json->assertOk();
        $this->assertIsArray($json->json());
        $this->assertArrayNotHasKey('errors', $json->json());
    }

    public function test_guest_and_user_without_right_cannot_load_thread_used_when_switching_dialogs(): void
    {
        $peer = $this->makePeer('AlignShow_');
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);
        $this->seedMessage($thread, (int) $peer->id, 'ALIGN_SHOW_SECRET');

        Auth::logout();

        $guestJson = $this->getJson(route('chat.api.threads.show', $thread));
        $this->assertNotSame(500, $guestJson->getStatusCode());
        $this->assertNotSame(200, $guestJson->getStatusCode(), 'Гость не должен видеть историю диалога');
        $guestJson->assertUnauthorized();
        $this->assertStringNotContainsString('ALIGN_SHOW_SECRET', (string) $guestJson->getContent());

        $guestHtml = $this->get(route('chat.api.threads.show', $thread));
        $this->assertNotSame(500, $guestHtml->getStatusCode());
        $this->assertNotSame(200, $guestHtml->getStatusCode());
        $this->assertTrue($guestHtml->isRedirect());
        $this->assertStringNotContainsString('ALIGN_SHOW_SECRET', (string) $guestHtml->getContent());

        $denied = $this->createUserWithoutPermission('messages.view', $this->partner);
        $this->actingInPartner($denied);
        $deniedJson = $this->getJson(route('chat.api.threads.show', $thread));
        $this->assertSame(403, $deniedJson->getStatusCode());
        $this->assertStringNotContainsString('ALIGN_SHOW_SECRET', (string) $deniedJson->getContent());

        $deniedHtml = $this->get(route('chat.api.threads.show', $thread));
        $this->assertSame(403, $deniedHtml->getStatusCode());
        $this->assertStringNotContainsString('ALIGN_SHOW_SECRET', (string) $deniedHtml->getContent());
    }

    public function test_foreign_thread_show_is_forbidden_and_does_not_leak_history(): void
    {
        $foreignPeer = $this->makePeer('ForeignShow_', ['partner_id' => $this->foreignPartner->id]);
        $foreignThread = $this->createThreadForUsers([$this->foreignUser->id, $foreignPeer->id]);
        $this->seedMessage($foreignThread, (int) $foreignPeer->id, 'FOREIGN_ALIGN_SECRET');

        $json = $this->getJson(route('chat.api.threads.show', $foreignThread));
        $this->assertNotSame(500, $json->getStatusCode());
        $this->assertSame(403, $json->getStatusCode());
        $this->assertStringNotContainsString('FOREIGN_ALIGN_SECRET', (string) $json->getContent());

        $html = $this->get(route('chat.api.threads.show', $foreignThread));
        $this->assertNotSame(500, $html->getStatusCode());
        $this->assertSame(403, $html->getStatusCode());
        $this->assertStringNotContainsString('FOREIGN_ALIGN_SECRET', (string) $html->getContent());
    }

    public function test_opening_second_thread_json_does_not_contain_first_thread_messages(): void
    {
        $peerOne = $this->makePeer('SwitchOne_');
        $peerTwo = $this->makePeer('SwitchTwo_');
        $one = $this->createThreadForUsers([$this->user->id, $peerOne->id], 'Диалог один');
        $two = $this->createThreadForUsers([$this->user->id, $peerTwo->id], 'Диалог два');
        $this->seedMessage($one, (int) $peerOne->id, 'FROM_DIALOG_ONE_UNIQUE');
        $this->seedMessage($two, (int) $peerTwo->id, 'FROM_DIALOG_TWO_UNIQUE');

        $showTwo = $this->getJson(route('chat.api.threads.show', $two));
        $this->assertNotSame(500, $showTwo->getStatusCode());
        $showTwo->assertOk()->assertJsonStructure(['thread' => ['id'], 'messages']);
        $this->assertSame((int) $two->id, (int) $showTwo->json('thread.id'));
        $bodies = collect($showTwo->json('messages'))->pluck('body')->all();
        $this->assertContains('FROM_DIALOG_TWO_UNIQUE', $bodies);
        $this->assertNotContains(
            'FROM_DIALOG_ONE_UNIQUE',
            $bodies,
            'Show второго диалога не должен отдавать переписку первого'
        );

        $showOne = $this->getJson(route('chat.api.threads.show', $one))->assertOk();
        $this->assertContains('FROM_DIALOG_ONE_UNIQUE', collect($showOne->json('messages'))->pluck('body')->all());
        $this->assertNotContains('FROM_DIALOG_TWO_UNIQUE', collect($showOne->json('messages'))->pluck('body')->all());
    }

    public function test_contacts_and_thread_show_wrong_methods_are_not_empty_200(): void
    {
        $peer = $this->makePeer('MethodAlign_');
        $thread = $this->createThreadForUsers([$this->user->id, $peer->id]);

        foreach (['POST', 'PATCH'] as $method) {
            $usersJson = $this->json($method, route('chat.api.users'));
            $this->assertNotSame(500, $usersJson->getStatusCode(), $method.' users JSON не 500');
            $this->assertNotSame(200, $usersJson->getStatusCode(), $method.' users не пустой 200');
            $this->assertSame(405, $usersJson->getStatusCode(), $method.' users должен быть 405');

            $usersHtml = $this->call($method, route('chat.api.users'));
            $this->assertNotSame(500, $usersHtml->getStatusCode(), $method.' users HTML не 500');
            $this->assertNotSame(200, $usersHtml->getStatusCode(), $method.' users HTML не пустой 200');
            $this->assertSame(405, $usersHtml->getStatusCode());

            $showJson = $this->json($method, route('chat.api.threads.show', $thread));
            $this->assertNotSame(500, $showJson->getStatusCode(), $method.' show JSON не 500');
            $this->assertNotSame(200, $showJson->getStatusCode(), $method.' show не пустой 200');
            $this->assertContains($showJson->getStatusCode(), [404, 405], $method.' show JSON');

            $showHtml = $this->call($method, route('chat.api.threads.show', $thread));
            $this->assertNotSame(500, $showHtml->getStatusCode(), $method.' show HTML не 500');
            $this->assertNotSame(200, $showHtml->getStatusCode(), $method.' show HTML не пустой 200');
            $this->assertContains($showHtml->getStatusCode(), [404, 405], $method.' show HTML');
        }

        $this->json('DELETE', route('chat.api.users'))->assertStatus(405);
        $this->call('DELETE', route('chat.api.users'))->assertStatus(405);

        $deleteJson = $this->json('DELETE', route('chat.api.threads.show', $thread));
        $this->assertNotSame(500, $deleteJson->getStatusCode());
        $deleteJson->assertForbidden();

        $deleteHtml = $this->call('DELETE', route('chat.api.threads.show', $thread));
        $this->assertNotSame(500, $deleteHtml->getStatusCode());
        $this->assertNotSame(200, $deleteHtml->getStatusCode());
        $deleteHtml->assertForbidden();
    }
}
