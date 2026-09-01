<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Chat;

use App\Models\Team;

/**
 * P1: разметка страницы, сайдбар @can, дефолты формы, JS/CSS-контракт (Vite-источники, не только 200 OK).
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ChatUiContractsFeatureTest extends ChatTestCase
{
    public function test_chat_page_starts_with_composer_disabled_until_dialog_is_chosen(): void
    {
        $html = $this->get(route('chat.index'))->assertOk()->getContent();
        $start = strpos($html, 'id="chatApp"');
        $this->assertNotFalse($start);
        $page = substr($html, $start, 5000);

        $this->assertStringContainsString('id="threadTitle"', $page);
        $this->assertStringContainsString('id="threadSubtitle"', $page);
        $this->assertStringContainsString('chat-header-subtitle', $page);
        $this->assertStringContainsString('Выберите диалог', $page);
        $titlePos = strpos($page, 'id="threadTitle"');
        $subPos = strpos($page, 'id="threadSubtitle"');
        $this->assertNotFalse($titlePos);
        $this->assertNotFalse($subPos);
        $this->assertGreaterThan($titlePos, $subPos, 'Подпись должна быть под названием, не над ним');
        $this->assertMatchesRegularExpression(
            '/id="threadSubtitle"[^>]*style="display:none;"/',
            $page
        );
        $this->assertStringContainsString('id="threadAvatar"', $page);
        $this->assertStringContainsString('style="display:none;"', $page);
        $this->assertStringContainsString('id="msgInput"', $page);
        $this->assertStringContainsString('id="emojiBtn"', $html);
        $this->assertStringContainsString('id="emojiPicker"', $html);
        $this->assertStringContainsString('id="reactionPicker"', $html);
        $this->assertStringContainsString('id="msgReactionError"', $html);
        $this->assertStringContainsString('data-error-for="emoji"', $html);
        $this->assertStringContainsString('chat-composer-field', $html);
        $this->assertMatchesRegularExpression(
            '/id="emojiBtn"[^>]*\bdisabled/',
            $html
        );
        $this->assertStringContainsString('id="composerEmojisJson"', $html);
        $this->assertStringContainsString('id="reactionEmojisJson"', $html);
        $this->assertStringContainsString('name="body"', $page);
        $this->assertStringContainsString('autocomplete="off" disabled', $page);
        $this->assertDoesNotMatchRegularExpression(
            '/id="msgInput"[^>]*\bvalue="/',
            $page,
            'Поле ввода при первом открытии страницы не должно содержать заранее вписанный текст'
        );
        $this->assertStringContainsString('type="submit" disabled', $page);
        $this->assertStringContainsString('Сообщения появятся здесь', $page);
        $this->assertStringContainsString('id="openContactsBtn"', $page);
        $this->assertStringContainsString('Контакты', $page);
        $this->assertStringContainsString('data-store-thread-url="'.route('chat.api.threads.store').'"', $html);
        $this->assertStringContainsString('id="openCreateGroupBtn"', $page);
        $this->assertStringContainsString('Создать группу', $html);
        $this->assertStringContainsString('data-store-group-url', $html);

        $deskBtnStart = strpos($html, 'id="openCreateGroupBtn"');
        $this->assertNotFalse($deskBtnStart);
        $deskBtn = substr($html, max(0, $deskBtnStart - 180), 280);
        $this->assertStringContainsString('js-open-create-group', $deskBtn);
        $this->assertStringContainsString('d-none d-lg-inline-block', $deskBtn);

        $mobBtnStart = strpos($html, 'id="openCreateGroupMobileBtn"');
        $this->assertNotFalse($mobBtnStart);
        $mobBtn = substr($html, max(0, $mobBtnStart - 160), 260);
        $this->assertStringContainsString('js-open-create-group', $mobBtn);
        $this->assertStringNotContainsString('d-none', $mobBtn);

        $modalStart = strpos($html, 'id="contactsModal"');
        $this->assertNotFalse($modalStart);
        $modal = substr($html, $modalStart, 2500);
        $this->assertStringNotContainsString('modal-xl', $modal);
        $this->assertStringNotContainsString('modal-fullscreen', $modal);
        $this->assertStringNotContainsString('modal-dialog-scrollable', $modal);
        $this->assertStringContainsString('class="modal-dialog"', $modal);
        $this->assertStringContainsString('id="contactsSearch"', $modal);
        $this->assertStringContainsString('id="contactsTeamFilter"', $modal);
        $this->assertStringContainsString('id="contactsTeamError"', $modal);
        $this->assertStringContainsString('data-error-for="team_id"', $modal);
        $this->assertStringContainsString('Все группы', $modal);
        $this->assertStringContainsString('Без группы', $modal);
        $this->assertStringContainsString('id="contactsError"', $modal);
        $this->assertStringContainsString('id="msgBodyError"', $page);
        $this->assertStringContainsString('id="threadPeerHit"', $page);
        $this->assertStringContainsString('chat-header-peer is-idle', $page);
        $this->assertStringContainsString('id="peerCardModal"', $html);
        $this->assertStringContainsString('id="peerCardError"', $html);
        $this->assertStringContainsString('id="peerCardBody"', $html);
        $this->assertStringContainsString('id="groupCardModal"', $html);
        $this->assertStringContainsString('id="groupCardError"', $html);
        $this->assertStringContainsString('id="groupCardPartner"', $html);
        $this->assertStringContainsString('id="groupMembersBody"', $html);
        $this->assertStringContainsString('id="addGroupMembersModal"', $html);
        $this->assertStringContainsString('id="addGroupMembersBtn"', $html);
        $this->assertStringContainsString('id="leaveGroupBtn"', $html);
        $this->assertStringNotContainsString('id="deleteThreadBtn"', $html);
        $this->assertStringNotContainsString('data-can-delete-thread="1"', $html);
        $this->assertStringContainsString('data-can-delete-thread="0"', $html);
        $this->assertStringContainsString('id="addGroupMembersForm"', $html);
        $this->assertStringContainsString('id="addGroupMembersTeamFilter"', $html);
        $this->assertStringContainsString('id="addGroupMembersSearch"', $html);
        $this->assertStringContainsString('fa-user-plus', $html);
        $this->assertStringContainsString('fa-right-from-bracket', $html);
        $groupCardStart = strpos($html, 'id="groupCardModal"');
        $this->assertNotFalse($groupCardStart);
        $groupCardModal = substr($html, $groupCardStart, 2800);
        $this->assertStringNotContainsString('modal-xl', $groupCardModal);
        $this->assertStringNotContainsString('modal-fullscreen', $groupCardModal);
        $this->assertStringContainsString('class="modal-dialog"', $groupCardModal);
        $this->assertStringContainsString('ФИО клиента', $groupCardModal);
        $avatarTh = strpos($groupCardModal, '>Аватар<');
        $fioTh = strpos($groupCardModal, '>ФИО клиента<');
        $roleTh = strpos($groupCardModal, '>Роль<');
        $this->assertNotFalse($avatarTh);
        $this->assertNotFalse($fioTh);
        $this->assertNotFalse($roleTh);
        $this->assertLessThan($fioTh, $avatarTh);
        $this->assertLessThan($roleTh, $fioTh);
        $addMembersStart = strpos($html, 'id="addGroupMembersModal"');
        $this->assertNotFalse($addMembersStart);
        $addMembersModal = substr($html, $addMembersStart, 2200);
        $this->assertStringNotContainsString('modal-xl', $addMembersModal);
        $this->assertStringNotContainsString('modal-fullscreen', $addMembersModal);
        $this->assertStringContainsString('class="modal-dialog"', $addMembersModal);
        $this->assertStringContainsString('Все группы', $addMembersModal);
        $this->assertStringContainsString('Без группы', $addMembersModal);
        $this->assertStringContainsString('data-error-for="team_id"', $addMembersModal);
        $this->assertStringContainsString('data-error-for="q"', $addMembersModal);
        $this->assertStringContainsString('data-error-for="user_ids"', $addMembersModal);
        $this->assertDoesNotMatchRegularExpression('/<option[^>]+selected/i', $addMembersModal);
        $peerModalStart = strpos($html, 'id="peerCardModal"');
        $this->assertNotFalse($peerModalStart);
        $peerModal = substr($html, $peerModalStart, 900);
        $this->assertStringNotContainsString('modal-xl', $peerModal);
        $this->assertStringNotContainsString('modal-fullscreen', $peerModal);
        $this->assertStringContainsString('class="modal-dialog"', $peerModal);

        $groupNameStart = strpos($html, 'id="createGroupNameModal"');
        $this->assertNotFalse($groupNameStart);
        $groupNameModal = substr($html, $groupNameStart, 1600);
        $this->assertStringNotContainsString('modal-xl', $groupNameModal);
        $this->assertStringNotContainsString('modal-fullscreen', $groupNameModal);
        $this->assertStringContainsString('class="modal-dialog"', $groupNameModal);
        $this->assertStringContainsString('id="createGroupTitle"', $groupNameModal);
        $this->assertStringContainsString('id="createGroupTitleError"', $groupNameModal);
        $this->assertStringContainsString('data-error-for="title"', $groupNameModal);
        $this->assertStringContainsString('Отмена', $groupNameModal);
        $this->assertStringContainsString('Создать', $groupNameModal);
        $this->assertDoesNotMatchRegularExpression('/id="createGroupTitle"[^>]*\bvalue=/', $groupNameModal);

        $groupMembersStart = strpos($html, 'id="createGroupMembersModal"');
        $this->assertNotFalse($groupMembersStart);
        $groupMembersModal = substr($html, $groupMembersStart, 2200);
        $this->assertStringNotContainsString('modal-xl', $groupMembersModal);
        $this->assertStringNotContainsString('modal-fullscreen', $groupMembersModal);
        $this->assertStringContainsString('class="modal-dialog"', $groupMembersModal);
        $this->assertStringContainsString('id="createGroupMembersTeamFilter"', $groupMembersModal);
        $this->assertStringContainsString('Все группы', $groupMembersModal);
        $this->assertStringContainsString('Без группы', $groupMembersModal);
        $this->assertStringContainsString('id="createGroupMembersList"', $groupMembersModal);
        $this->assertStringContainsString('id="createGroupMembersError"', $groupMembersModal);
        $this->assertStringContainsString('data-error-for="user_ids"', $groupMembersModal);
        $this->assertDoesNotMatchRegularExpression('/<option[^>]+selected/i', $groupMembersModal);

        $css = (string) file_get_contents(resource_path('css/chat.css'));
        $this->assertStringContainsString('max-height: min(60vh, 520px)', $css);
        $this->assertStringContainsString('.chat-online-dot', $css);
        $this->assertStringContainsString('.contact-online-dot', $css);
        $this->assertStringContainsString('.contact-parent', $css);
        $this->assertStringContainsString('.contact-main', $css);
        $this->assertStringContainsString('.contact-team', $css);
        $this->assertStringContainsString('.contact-role', $css);
        $this->assertMatchesRegularExpression(
            '/\.contact-main\s*\{[^}]*text-align:\s*left/',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.contact-name\s*\{[^}]*text-align:\s*left/',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.contact-parent\s*\{[^}]*text-align:\s*left/',
            $css
        );
        $contactMainPos = strpos($css, '.contact-main {');
        $contactNamePos = strpos($css, '.contact-name {');
        $contactParentPos = strpos($css, '.contact-parent {');
        $this->assertNotFalse($contactMainPos);
        $this->assertNotFalse($contactNamePos);
        $this->assertNotFalse($contactParentPos);
        $this->assertStringContainsString('align-items: flex-start', $css);
        $this->assertStringContainsString('.chat-li-unread', $css);
        $this->assertStringContainsString('.chat-header-peer { min-width: 0; flex: 1 1 auto; text-align: left; }', $css);
        $this->assertStringContainsString('.chat-header-subtitle {', $css);
        $this->assertStringContainsString('.peer-card-partner {', $css);
        $this->assertStringContainsString('.group-card-partner {', $css);
        $this->assertMatchesRegularExpression(
            '/\.chat-header-text\s*\{[^}]*text-align:\s*left/',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.chat-header-text #threadTitle\s*\{[^}]*text-align:\s*left/',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.chat-header-subtitle\s*\{[^}]*text-align:\s*left/',
            $css
        );
        $headerPeerPos = strpos($css, '.chat-header-peer {');
        $headerTextPos = strpos($css, '.chat-header-text {');
        $headerTitlePos = strpos($css, '.chat-header-text #threadTitle {');
        $headerSubPos = strpos($css, '.chat-header-subtitle {');
        $this->assertNotFalse($headerPeerPos);
        $this->assertNotFalse($headerTextPos);
        $this->assertNotFalse($headerTitlePos);
        $this->assertNotFalse($headerSubPos);
        $headerMediaPos = strpos($css, '@media (max-width: 991.98px)');
        $this->assertNotFalse($headerMediaPos);
        $this->assertLessThan($headerMediaPos, $headerPeerPos, 'Имя и подзаголовок шапки слева и на десктопе, не только в мобильном @media');
        $this->assertLessThan($headerMediaPos, $headerTextPos);
        $this->assertLessThan($headerMediaPos, $headerTitlePos);
        $this->assertLessThan($headerMediaPos, $headerSubPos);
        $this->assertLessThan($headerMediaPos, $contactMainPos, 'Имя клиента и родителя слева и на десктопе: иначе body.sidebar-mini центрирует');
        $this->assertLessThan($headerMediaPos, $contactNamePos);
        $this->assertLessThan($headerMediaPos, $contactParentPos);
        $this->assertStringContainsString('#f3a12b', $css);
        $this->assertStringContainsString('.group-pick-check', $css);
        $this->assertStringContainsString('.group-card-actions', $css);
        $this->assertStringContainsString('.group-members-wrap', $css);
        $this->assertStringContainsString('.group-member-remove', $css);
        $this->assertStringContainsString('.group-card-action.is-hidden { display: none; }', $css);
        $removePos = strpos($css, '.group-member-remove {');
        $this->assertNotFalse($removePos);
        $removeBlock = substr($css, $removePos, 220);
        $this->assertStringContainsString('opacity: 0', $removeBlock);
        $this->assertStringContainsString('.group-members-table tbody tr:hover .group-member-remove { opacity: 1; }', $css);
        $mediaPosForRemove = strpos($css, '@media (max-width: 991.98px)');
        $this->assertNotFalse($mediaPosForRemove);
        $this->assertLessThan($mediaPosForRemove, $removePos, 'На десктопе «удалить» скрыто opacity:0, не только в @media');
        $this->assertStringContainsString('.group-member-remove { opacity: 1; }', substr($css, $mediaPosForRemove, 400));
        $this->assertStringContainsString('.group-member-row.is-selected', $css);
        $pickPos = strpos($css, '.group-pick-check {');
        $this->assertNotFalse($pickPos);
        $this->assertStringContainsString('#f3a12b', substr($css, $pickPos, 250));
        $this->assertStringContainsString('.chat-li-preview.is-draft', $css);
        $this->assertMatchesRegularExpression(
            '/\.chat-li-title\s*\{[^}]*text-align:\s*left/',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.chat-li-middle\s*\{[^}]*text-align:\s*left/',
            $css
        );
        $mediaPos = strpos($css, '@media (max-width: 991.98px)');
        $titlePos = strpos($css, '.chat-li-title {');
        $middlePos = strpos($css, '.chat-li-middle {');
        $this->assertNotFalse($mediaPos);
        $this->assertNotFalse($titlePos);
        $this->assertNotFalse($middlePos);
        $this->assertLessThan($mediaPos, $titlePos, 'Имена в списке слева выравниваются влево и на десктопе, не только в мобильном @media');
        $this->assertLessThan($mediaPos, $middlePos);
        $titleBlock = substr($css, $titlePos, (int) strpos($css, '}', $titlePos) - $titlePos);
        $middleBlock = substr($css, $middlePos, (int) strpos($css, '}', $middlePos) - $middlePos);
        $this->assertStringContainsString('text-align: left', $titleBlock);
        $this->assertStringContainsString('text-align: left', $middleBlock);
        $this->assertStringNotContainsString('text-align: center', $titleBlock);
        $this->assertStringNotContainsString('text-align: center', $middleBlock);
        $this->assertStringContainsString('.chat-emoji-btn {', $css);
        $this->assertStringContainsString('.msg-reactions {', $css);
        $this->assertStringContainsString('.msg-bubble.is-big-emoji {', $css);
        $this->assertStringContainsString('.msg-reaction-chip.is-mine {', $css);
        $this->assertStringContainsString('chat-composer-field', $css);
        $this->assertStringContainsString('.msg-row.msg-mine { justify-content: flex-end; }', $css);
        $this->assertStringContainsString('.msg-row.msg-other { justify-content: flex-start; }', $css);
        $this->assertStringContainsString(
            "white-space: nowrap; word-break: normal; flex-shrink: 0;\n}",
            $css
        );
        $this->assertStringContainsString('.msg-meta .time { white-space: nowrap; word-break: keep-all; flex-shrink: 0; }', $css);
        $this->assertStringContainsString('min-width: 6.5rem', $css);
        $this->assertStringContainsString('#messagesBox {', $css);
        $this->assertStringContainsString('overscroll-behavior: contain', $css);
        $this->assertStringContainsString('-webkit-overflow-scrolling: touch', $css);
        $this->assertStringContainsString('.chat-dialog-col .dialog-bg {', $css);
        $this->assertStringContainsString('height: 0 !important; flex: 1 1 auto', $css);
        $this->assertStringContainsString('grid-template-rows: minmax(0, 1fr) auto', $css);
        $this->assertStringContainsString('.chat-composer { flex: 0 0 auto; margin-top: auto; }', $css);
        $this->assertStringContainsString('margin-bottom: 0 !important', $css);
        $this->assertStringContainsString('#chatApp.is-dialog-open .chat-mobile-nav { display: none; }', $css);
        $this->assertStringNotContainsString(
            '.msg-inner { display: flex; flex-direction: column; width: 100%; }',
            $css,
            'Обёртка пузыря не должна растягиваться на всю ширину — иначе свои и входящие визуально по центру'
        );

        $blade = (string) file_get_contents(resource_path('views/chat/index.blade.php'));
        $this->assertStringContainsString("@vite(['resources/css/chat.css'])", $blade);
        $this->assertStringContainsString("@vite(['resources/js/chat.js'])", $blade);
        $this->assertStringNotContainsString("asset('js/chat.js')", $blade);
        $this->assertDoesNotMatchRegularExpression(
            '/<style[\s>]/i',
            $blade,
            'chat/index.blade.php не должен содержать inline <style>: стили в resources/css/chat.css'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<script(?![^>]*\bsrc\b)[^>]*>/i',
            $blade,
            'chat/index.blade.php не должен содержать inline <script> с AJAX-submit'
        );

        $vite = (string) file_get_contents(base_path('vite.config.js'));
        $this->assertStringContainsString("'resources/css/chat.css'", $vite);
        $this->assertStringContainsString("'resources/js/chat.js'", $vite);

        $layout = (string) file_get_contents(resource_path('views/layouts/admin2.blade.php'));
        $this->assertStringNotContainsString('resources/css/chat.css', $layout);
        $this->assertStringNotContainsString('resources/js/chat.js', $layout);
    }

    public function test_superadmin_sees_delete_thread_button_in_dialog_header(): void
    {
        $html = $this->get(route('chat.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('id="deleteThreadBtn"', $html);

        $superadmin = $this->createUserWithRole('superadmin');
        $this->actingInPartner($superadmin);
        $saHtml = $this->get(route('chat.index'))->assertOk()->getContent();
        $this->assertStringContainsString('id="deleteThreadBtn"', $saHtml);
        $this->assertStringContainsString('data-can-delete-thread="1"', $saHtml);
        $this->assertStringContainsString('fa-trash', $saHtml);
        $this->assertStringContainsString('id="threadDeleteError"', $saHtml);
        $this->assertStringContainsString('data-error-for="thread"', $saHtml);
        $this->assertStringNotContainsString('Удалить чат</button>', $saHtml);
        $this->assertStringContainsString('id="confirmDeleteModal"', $saHtml);
        $this->assertMatchesRegularExpression(
            '/id="deleteThreadBtn"[^>]*style="display:none;"/',
            $saHtml
        );
        $this->assertMatchesRegularExpression(
            '/id="deleteThreadBtn"[^>]*title="Удалить чат"/',
            $saHtml
        );
        $this->assertMatchesRegularExpression(
            '/id="deleteThreadBtn"[^>]*aria-label="Удалить чат"/',
            $saHtml
        );
        $peerPos = strpos($saHtml, 'id="threadPeerHit"');
        $btnPos = strpos($saHtml, 'id="deleteThreadBtn"');
        $this->assertNotFalse($peerPos);
        $this->assertNotFalse($btnPos);
        $this->assertLessThan($btnPos, $peerPos, 'Корзина справа от имени, не слева');

        $blade = (string) file_get_contents(resource_path('views/chat/index.blade.php'));
        $this->assertStringContainsString("@can('messages.threads.delete')", $blade);
        $css = (string) file_get_contents(resource_path('css/chat.css'));
        $this->assertStringContainsString('.chat-header-delete-wrap', $css);
        $this->assertStringContainsString('.chat-header-delete {', $css);
    }

    public function test_granted_user_sees_trash_admin_and_trainer_without_grant_do_not(): void
    {
        $this->grantPermission($this->user, 'messages.threads.delete');
        $granted = $this->get(route('chat.index'))->assertOk()->getContent();
        $this->assertStringContainsString('id="deleteThreadBtn"', $granted);
        $this->assertStringContainsString('data-can-delete-thread="1"', $granted);
        $this->assertStringContainsString('id="threadDeleteError"', $granted);

        $admin = $this->createUserWithRole('admin');
        $this->actingInPartner($admin);
        $adminHtml = $this->get(route('chat.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('id="deleteThreadBtn"', $adminHtml);
        $this->assertStringContainsString('data-can-delete-thread="0"', $adminHtml);

        $trainer = $this->createUserWithRole('trainer');
        $this->actingInPartner($trainer);
        $trainerHtml = $this->get(route('chat.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('id="deleteThreadBtn"', $trainerHtml);
        $this->assertStringContainsString('data-can-delete-thread="0"', $trainerHtml);
    }

    public function test_contacts_modal_lists_own_partner_teams_and_not_foreign(): void
    {
        $own = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'ЧатФильтрСвоя_'.uniqid('', true),
        ]);
        $foreign = Team::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'title' => 'ЧатФильтрЧужая_'.uniqid('', true),
        ]);

        $html = $this->get(route('chat.index'))->assertOk()->getContent();
        $modalStart = strpos($html, 'id="contactsModal"');
        $this->assertNotFalse($modalStart);
        $modalEnd = strpos($html, 'id="peerCardModal"');
        $this->assertNotFalse($modalEnd);
        $modal = substr($html, $modalStart, $modalEnd - $modalStart);

        $this->assertStringContainsString((string) $own->title, $modal);
        $this->assertStringContainsString('value="'.(int) $own->id.'"', $modal);
        $this->assertStringNotContainsString((string) $foreign->title, $modal);
        $this->assertStringNotContainsString('value="'.(int) $foreign->id.'"', $modal);
    }

    public function test_sidebar_hides_messages_item_without_permission_and_hides_badge_when_unread_is_zero(): void
    {
        $zeroHtml = $this->get(route('chat.index'))->assertOk()->getContent();
        $this->assertStringContainsString('Чат', $zeroHtml);
        $this->assertStringContainsString('js-chat-unread-count', $zeroHtml);
        $this->assertMatchesRegularExpression(
            '/js-chat-unread-count"[^>]*style="display:none"/',
            $zeroHtml
        );
        $this->assertStringContainsString('KidsCrmChatSetUnread', $zeroHtml);

        $denied = $this->createUserWithoutPermission('messages.view', $this->partner);
        $this->grantPermission($denied, 'dashboard.view');
        $this->actingInPartner($denied);

        $deniedHtml = $this->get(route('dashboard'))->assertOk()->getContent();
        $this->assertStringNotContainsString('js-chat-unread-count', $deniedHtml);
        $this->assertStringNotContainsString('KidsCrmChatSetUnread', $deniedHtml);
        $this->assertStringNotContainsString(route('chat.index', [], false), $deniedHtml);
        $this->assertStringContainsString('setInterval(ping, 60000)', $deniedHtml);
        $this->assertTrue(
            str_contains($deniedHtml, '/presence/ping') || str_contains($deniedHtml, 'presence\/ping'),
            'Dashboard без messages.view должен пинговать presence'
        );
    }

    public function test_unread_badge_shows_count_when_peer_has_new_messages(): void
    {
        $peer = $this->makePeer();
        $threadId = (int) $this->postJson(route('chat.api.threads.store'), [
            'user_id' => $peer->id,
        ])->json('thread_id');
        $this->postJson(route('chat.api.threads.messages.store', $threadId), [
            'body' => 'Новое',
        ]);

        $this->actingInPartner($peer);
        $html = $this->get(route('chat.index'))->assertOk()->getContent();

        $this->assertStringContainsString('js-chat-unread-count', $html);
        $this->assertDoesNotMatchRegularExpression(
            '/js-chat-unread-count"[^>]*style="display:none"/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/js-chat-unread-count[^>]*>1</',
            $html
        );
    }

    public function test_javascript_enables_composer_only_after_open_thread_and_resets_contacts_search(): void
    {
        $js = (string) file_get_contents(resource_path('js/chat.js'));

        $openThreadPos = strpos($js, 'function openThread(');
        $this->assertNotFalse($openThreadPos);
        $openThreadChunk = substr($js, $openThreadPos, 8000);
        $this->assertStringContainsString('persistLeavingDraft(threadId)', $openThreadChunk);
        $this->assertStringContainsString('openThread._seq', $openThreadChunk);
        $clearBoxPos = strpos($openThreadChunk, "box.innerHTML = ''");
        $fetchPos = strpos($openThreadChunk, 'fetch(threadUrl(threadId)');
        $this->assertNotFalse($clearBoxPos);
        $this->assertNotFalse($fetchPos);
        $this->assertLessThan(
            $fetchPos,
            $clearBoxPos,
            'На мобилке #messagesBox очищается до fetch, иначе видна переписка прошлого диалога'
        );
        $this->assertStringContainsString('setComposerEnabled(true)', $openThreadChunk);
        $this->assertStringContainsString('composerDraftFor(res.thread)', $openThreadChunk);
        $this->assertStringContainsString("getElementById('msgInput').focus()", $openThreadChunk);
        $enablePos = strpos($openThreadChunk, 'setComposerEnabled(true)');
        $draftPos = strpos($openThreadChunk, 'composerDraftFor(res.thread)');
        $focusPos = strpos($openThreadChunk, "getElementById('msgInput').focus()");
        $this->assertNotFalse($enablePos);
        $this->assertNotFalse($draftPos);
        $this->assertNotFalse($focusPos);
        $this->assertGreaterThan(
            $enablePos,
            $draftPos,
            'Черновик в поле ввода — после включения композера'
        );
        $this->assertGreaterThan(
            $draftPos,
            $focusPos,
            'Фокус в поле ввода — только после восстановления черновика'
        );
        $this->assertStringContainsString('setHeaderPeerClickable(!!currentPeerId || currentIsGroup)', $openThreadChunk);
        $this->assertStringContainsString('currentTeamId = res.thread.team_id ? Number(res.thread.team_id) : null;', $openThreadChunk);
        $this->assertStringContainsString('setDeleteThreadVisible();', $openThreadChunk);
        $this->assertStringContainsString("av.style.display = ''", $openThreadChunk);
        $this->assertStringContainsString('subscribeThread(currentThreadId)', $openThreadChunk);
        $this->assertStringContainsString('setUnreadBadge(res.unread_total)', $openThreadChunk);
        $this->assertStringContainsString('if (String(currentThreadId) === String(threadId))', $openThreadChunk);
        $this->assertStringContainsString("showMsgError('Не удалось открыть диалог.')", $openThreadChunk);
        $this->assertStringContainsString("matchMedia('(max-width: 991.98px)')", $openThreadChunk);
        $this->assertStringContainsString('is-dialog-open', $openThreadChunk);
        $this->assertStringContainsString("data-mobile-tab', tab", $openThreadChunk);
        $this->assertStringContainsString("currentIsGroup ? 'groups' : 'messages'", $openThreadChunk);
        $this->assertStringContainsString('row.is_group', $openThreadChunk);
        $this->assertStringContainsString('maybeLoadOlder()', $openThreadChunk);

        $this->assertSame(
            1,
            substr_count($js, 'setComposerEnabled(true)'),
            'Композер включается только после открытия диалога'
        );

        $openContactsPos = strpos($js, "getElementById('openContactsBtn')");
        $this->assertNotFalse($openContactsPos);
        $openContactsChunk = substr($js, $openContactsPos, 900);
        $this->assertStringContainsString("contactsSearch').value = ''", $openContactsChunk);
        $this->assertStringContainsString("contactsTeamFilter').value = ''", $openContactsChunk);
        $this->assertStringContainsString("loadContacts('')", $openContactsChunk);
        $this->assertStringContainsString('contactsModal().show()', $openContactsChunk);
        $this->assertStringNotContainsString('setComposerEnabled(true)', $openContactsChunk);
        $this->assertStringNotContainsString('threadSearch', $openContactsChunk);

        $loadContactsPos = strpos($js, 'function loadContacts(');
        $this->assertNotFalse($loadContactsPos);
        $loadContactsChunk = substr($js, $loadContactsPos, strpos($js, 'function startDialog(') - $loadContactsPos);
        $this->assertStringContainsString("params.set('team_id', teamId)", $loadContactsChunk);
        $this->assertStringContainsString("fieldError(res.data, 'team_id')", $loadContactsChunk);
        $this->assertStringContainsString('showContactsTeamError', $loadContactsChunk);

        $startDialogPos = strpos($js, 'function startDialog(');
        $this->assertNotFalse($startDialogPos);
        $startDialogChunk = substr($js, $startDialogPos, 1600);
        $this->assertStringContainsString('Number(t.peer_id) === Number(userId)', $startDialogChunk);
        $this->assertStringContainsString('!t.is_group &&', $startDialogChunk);
        $this->assertStringContainsString('openThread(existing.id)', $startDialogChunk);
        $this->assertStringContainsString("JSON.stringify({ user_id: userId })", $startDialogChunk);
        $this->assertStringContainsString("fieldError(res.data, 'user_id')", $startDialogChunk);
        $this->assertStringContainsString('if (startDialogBusy)', $startDialogChunk);
        $this->assertStringContainsString('startDialogBusy = true', $startDialogChunk);
        $this->assertStringContainsString('startDialogBusy = false', $startDialogChunk);
        $this->assertStringContainsString('function paintSplitNavBadges(', $js);
        $this->assertStringContainsString("getElementById('groupThreads')", $js);
        $this->assertStringContainsString("filter(function (t) { return !t.is_group; })", $js);
        $this->assertStringContainsString("'Групп нет'", $js);
        $this->assertStringContainsString('function maybeLoadOlder(', $js);
        $this->assertStringContainsString('function olderPrefetchThreshold(', $js);
        $this->assertStringContainsString('clientHeight || 0) * 1.5', $js);
        $this->assertStringContainsString("addEventListener('scroll', maybeLoadOlder", $js);
        $this->assertStringContainsString("addEventListener('gesturestart', preventPageZoom", $js);
        $this->assertStringNotContainsString('scrollTop < 40', $js);
    }

    public function test_javascript_submit_prevents_default_and_maps_body_field_errors(): void
    {
        $js = (string) file_get_contents(resource_path('js/chat.js'));

        $submitPos = strpos($js, "getElementById('sendForm').addEventListener('submit'");
        $this->assertNotFalse($submitPos);
        $submitChunk = substr($js, $submitPos, 2800);
        $this->assertStringContainsString('e.preventDefault()', $submitChunk);
        $this->assertStringContainsString('fetch(', $submitChunk);
        $this->assertStringContainsString("JSON.stringify({ body: text })", $submitChunk);
        $this->assertStringContainsString("fieldError(res.data, 'body')", $submitChunk);
        $this->assertStringContainsString('clearTimeout(draftTimer)', $submitChunk);
        $this->assertStringContainsString("rememberDraft(id, '')", $submitChunk);
        $this->assertStringContainsString('Сначала выберите диалог слева.', $submitChunk);
        $this->assertStringContainsString('Введите текст сообщения.', $submitChunk);
        $this->assertSame(1, substr_count($js, "getElementById('sendForm').addEventListener('submit'"));

        $this->assertStringContainsString("Accept': 'application/json'", $js);
        $this->assertStringContainsString("'X-Requested-With': 'XMLHttpRequest'", $js);
        $this->assertStringContainsString('bootstrap.Modal.getOrCreateInstance', $js);
        $this->assertStringNotContainsString('$.ajax', $js);
        $this->assertStringNotContainsString('.modal(', $js);
        $this->assertStringContainsString('div.textContent', $js);
        $this->assertStringContainsString('}, 1000)', $js);
        $this->assertStringContainsString('KidsCrmChatRefreshInbox = loadThreads', $js);
        $this->assertStringContainsString('KidsCrmChatOnInboxBump = applyInboxBump', $js);
        $this->assertStringContainsString('function applyInboxBump(', $js);
        $this->assertStringContainsString('Number(e.unread_total) - Number(e.unread_count || 0)', $js);
        $startPollPos = strpos($js, 'function startPoll(');
        $this->assertNotFalse($startPollPos);
        $startPollChunk = substr($js, $startPollPos, 900);
        $this->assertStringContainsString('subscribeInbox()', $startPollChunk);
        $this->assertStringNotContainsString('loadThreads()', $startPollChunk);
        $this->assertStringNotContainsString('after_id', $startPollChunk);
        $this->assertStringNotContainsString('/messages', $startPollChunk);
        $this->assertStringNotContainsString("socketState() === 'connected'", $startPollChunk);
        $this->assertStringContainsString('if (!window.Echo) return', $js);
        $this->assertStringContainsString("window.Echo.private('thread.'", $js);
        $this->assertStringContainsString('} catch (e) {}', $js);
        $this->assertStringContainsString('function ticksHtml(', $js);
        $this->assertStringContainsString('checks-read', $js);
        $this->assertStringContainsString('check-second', $js);
        $this->assertStringContainsString('checks-sent', $js);
        $this->assertStringContainsString("data-read', m.is_read ? '1' : '0'", $js);
        $this->assertStringContainsString('syncMineReadStatus', $js);
        $this->assertStringContainsString('inboxPollStamp', $js);
        $this->assertStringNotContainsString("socketState() === 'connected'", $js);
        $this->assertStringContainsString('markThreadRead(threadId)', $js);
        $createdPos = strpos($js, "threadChannel.listen('.message.created'");
        $this->assertNotFalse($createdPos);
        $createdChunk = substr($js, $createdPos, 1600);
        $this->assertStringContainsString('markThreadRead(threadId)', $createdChunk);
        $this->assertStringContainsString('Number(msg.user_id) === me', $createdChunk);
        $this->assertStringContainsString('last_message_is_mine', $js);
        $this->assertStringContainsString('function markListOutgoingRead(', $js);
        $this->assertStringContainsString('chat-online-dot', $js);
        $this->assertStringContainsString('contact-online-dot', $js);
        $this->assertStringContainsString('contact-parent', $js);
        $this->assertStringContainsString('parent_full_name', $js);
        $this->assertStringContainsString('function openPeerCard(', $js);
        $this->assertStringContainsString('threadPeerHit', $js);
        $this->assertStringContainsString('function confirmDeleteThread(', $js);
        $this->assertStringContainsString('function submitDeleteThread(', $js);
        $this->assertStringContainsString('function setDeleteThreadVisible(', $js);
        $this->assertStringContainsString("fieldError(res.data, 'thread')", $js);
        $this->assertStringContainsString("chatToast(res.data.message || 'Чат удалён.')", $js);
        $this->assertStringContainsString("method: 'DELETE'", $js);
        $this->assertStringContainsString('e.stopPropagation()', $js);
        $bumpPos = strpos($js, 'function applyInboxBump(');
        $this->assertNotFalse($bumpPos);
        $bumpChunk = substr($js, $bumpPos, 900);
        $this->assertStringContainsString('if (e.removed)', $bumpChunk);
        $this->assertStringContainsString('closeCurrentThread()', $bumpChunk);
        $clickPos = strpos($js, "deleteThreadBtn.addEventListener('click'");
        $this->assertNotFalse($clickPos);
        $clickChunk = substr($js, $clickPos, 280);
        $this->assertStringContainsString('e.preventDefault()', $clickChunk);
        $this->assertStringContainsString('e.stopPropagation()', $clickChunk);
        $this->assertStringContainsString('confirmDeleteThread()', $clickChunk);
        $submitPos = strpos($js, 'function submitDeleteThread(');
        $this->assertNotFalse($submitPos);
        $submitChunk = substr($js, $submitPos, 900);
        $this->assertStringContainsString('headers(true)', $submitChunk);
        $this->assertStringContainsString("method: 'DELETE'", $submitChunk);
        $this->assertStringContainsString('peerCardModal', $js);
        $this->assertStringContainsString('last_seen_label', $js);
        $this->assertStringContainsString('partner_name', $js);
        $this->assertStringContainsString('peer-card-partner', $js);
        $this->assertStringContainsString('function setGroupCardPartner(', $js);
        $this->assertStringContainsString("getElementById('groupCardPartner')", $js);
        $this->assertStringContainsString("href=\"' + escapeHtml(href)", $js);
        $this->assertStringContainsString("urls.users + '/' + encodeURIComponent", $js);
        $this->assertStringContainsString('function persistLeavingDraft(', $js);
        $this->assertStringContainsString('function scheduleDraftSave(', $js);
        $this->assertStringContainsString("threadUrl(id, '/draft')", $js);
        $this->assertStringContainsString("addEventListener('input', scheduleDraftSave)", $js);
        $this->assertStringContainsString('function loadAccountCard(', $js);
        $this->assertStringContainsString("renderPeerCard(res.data, 'accountCardBody')", $js);
        $this->assertStringContainsString("targetId === 'accountCardBody'", $js);
        $this->assertStringContainsString('function showAccountCardError(', $js);
        $this->assertStringContainsString('function setMobileTab(', $js);
        $this->assertStringContainsString('function placeContactsMount(', $js);
        $this->assertStringContainsString("getElementById('chatMobileBack')", $js);
        $this->assertStringContainsString("matchMedia('(max-width: 991.98px)')", $js);
        $this->assertStringContainsString('function openCreateGroupWizard(', $js);
        $this->assertStringContainsString('function proceedCreateGroupToMembers(', $js);
        $this->assertStringContainsString('function submitCreateGroup(', $js);
        $this->assertStringContainsString('function toggleGroupMember(', $js);
        $this->assertStringContainsString('function resetCreateGroupWizard(', $js);
        $this->assertStringContainsString("JSON.stringify({ title: title, user_ids: ids })", $js);
        $this->assertStringContainsString("fieldError(res.data, 'user_ids')", $js);
        $this->assertStringContainsString('Выберите минимум двух участников.', $js);
        $this->assertStringContainsString('group-pick-check', $js);
        $this->assertStringContainsString('js-open-create-group', $js);
        $this->assertStringContainsString("querySelectorAll('.js-open-create-group')", $js);
        $this->assertStringContainsString('is_group: e.is_group', $js);
        $this->assertStringContainsString('if (patch.peer_id && !patch.is_group) {', $js);

        $this->assertStringContainsString('function threadListTitle(', $js);
        $this->assertStringContainsString("return t && t.is_group ? 'Группа' : 'Диалог';", $js);
        $this->assertStringContainsString("threadTitle').textContent = threadListTitle(res.thread)", $js);
        $this->assertStringContainsString('function setThreadSubtitle(', $js);
        $this->assertStringContainsString("getElementById('threadSubtitle')", $js);
        $this->assertStringContainsString('res.thread.header_subtitle', $js);
        $this->assertStringContainsString("setThreadSubtitle('')", $js);
        $this->assertStringContainsString('setThreadSubtitle(membersCountLabel(thread.members_total))', $js);
        $this->assertStringContainsString('return !t.is_group && Number(t.peer_id) === Number(userId);', $js);

        $loadGroupPos = strpos($js, 'function loadGroupMembers(');
        $this->assertNotFalse($loadGroupPos);
        $loadGroupChunk = substr($js, $loadGroupPos, strpos($js, 'function submitCreateGroup(') - $loadGroupPos);
        $this->assertStringContainsString("params.set('team_id', teamId)", $loadGroupChunk);

        $openWizardPos = strpos($js, 'function openCreateGroupWizard(');
        $this->assertNotFalse($openWizardPos);
        $openWizardChunk = substr($js, $openWizardPos, strpos($js, 'function proceedCreateGroupToMembers(') - $openWizardPos);
        $this->assertStringContainsString('resetCreateGroupWizard()', $openWizardChunk);

        $setTabPos = strpos($js, 'function setMobileTab(');
        $this->assertNotFalse($setTabPos);
        $setTabEnd = strpos($js, 'function leaveMobileDialog(', $setTabPos);
        $this->assertNotFalse($setTabEnd);
        $setTabChunk = substr($js, $setTabPos, $setTabEnd - $setTabPos);
        $this->assertStringNotContainsString('openCreateGroupWizard', $setTabChunk);
        $this->assertStringNotContainsString("tab === 'groups'", $setTabChunk);

        $nameFormPos = strpos($js, "getElementById('createGroupNameForm')");
        $this->assertNotFalse($nameFormPos);
        $membersFormPos = strpos($js, "getElementById('createGroupMembersForm')");
        $this->assertNotFalse($membersFormPos);
        $this->assertStringContainsString('e.preventDefault()', substr($js, $nameFormPos, 220));
        $this->assertStringContainsString('e.preventDefault()', substr($js, $membersFormPos, 220));

        $sortThreadsPos = strpos($js, 'function sortThreads(');
        $this->assertNotFalse($sortThreadsPos);
        $sortThreadsChunk = substr($js, $sortThreadsPos, strpos($js, 'function threadListTitle(') - $sortThreadsPos);
        $this->assertStringContainsString('unread_count', $sortThreadsChunk);
        $this->assertStringContainsString('last_message_time', $sortThreadsChunk);
        $this->assertStringNotContainsString('updated_at', $sortThreadsChunk);

        $renderThreadsPos = strpos($js, 'function renderThreads(');
        $this->assertNotFalse($renderThreadsPos);
        $renderThreadsChunk = substr($js, $renderThreadsPos, strpos($js, 'function upsertThread(') - $renderThreadsPos);
        $this->assertStringContainsString('threadListTitle(t)', $renderThreadsChunk);
        $this->assertStringNotContainsString("t.title || 'Диалог'", $renderThreadsChunk);
        $this->assertStringContainsString('fmtTime(t.last_message_time)', $renderThreadsChunk);
        $this->assertStringContainsString('chat-li-unread', $renderThreadsChunk);
        $this->assertStringContainsString('chat-li-meta', $renderThreadsChunk);
        $this->assertStringContainsString('Черновик: ', $renderThreadsChunk);
        $this->assertStringContainsString('is-draft', $renderThreadsChunk);
        $this->assertStringNotContainsString('bg-primary', $renderThreadsChunk);
        $this->assertStringNotContainsString('last_seen', $renderThreadsChunk);
        $this->assertStringNotContainsString('is-offline', $renderThreadsChunk);
        $this->assertStringContainsString('openThread(t.id)', $renderThreadsChunk);
        $this->assertStringNotContainsString('openPeerCard', $renderThreadsChunk);

        $renderContactsPos = strpos($js, 'function renderContacts(');
        $this->assertNotFalse($renderContactsPos);
        $renderContactsChunk = substr($js, $renderContactsPos, strpos($js, 'function loadContacts(') - $renderContactsPos);
        $this->assertStringContainsString('is-offline', $renderContactsChunk);
        $this->assertStringContainsString("parentFio ? '<div class=\"contact-parent\">'", $renderContactsChunk);
        $this->assertStringContainsString('contact-main', $renderContactsChunk);
        $this->assertStringContainsString('contact-team', $renderContactsChunk);
        $this->assertStringContainsString('contact-role', $renderContactsChunk);
        $this->assertStringNotContainsString('d-flex justify-content-between', $renderContactsChunk);
        $this->assertStringContainsString('startDialog(Number(u.id))', $renderContactsChunk);
        $this->assertStringNotContainsString('openPeerCard', $renderContactsChunk);

        $renderMembersPos = strpos($js, 'function renderGroupMembers(');
        $this->assertNotFalse($renderMembersPos);
        $renderMembersChunk = substr($js, $renderMembersPos, strpos($js, 'function loadGroupMembers(') - $renderMembersPos);
        $this->assertStringContainsString("parentFio ? '<div class=\"contact-parent\">'", $renderMembersChunk);
        $this->assertStringContainsString('contact-name', $renderMembersChunk);
        $this->assertStringContainsString('group-member-row', $renderMembersChunk);
        $this->assertStringNotContainsString('contact-online-dot', $renderMembersChunk);
        $this->assertSame(2, substr_count($js, "parentFio ? '<div class=\"contact-parent\">'"));

        $openPeerPos = strpos($js, 'function openPeerCard(');
        $this->assertNotFalse($openPeerPos);
        $openPeerChunk = substr($js, $openPeerPos, strpos($js, 'function renderContacts(') - $openPeerPos);
        $this->assertStringContainsString('if (!id)', $openPeerChunk);
        $this->assertStringContainsString('function dashText(', $js);
        $this->assertStringContainsString('function openGroupCard(', $js);
        $this->assertStringContainsString('function headerPeerActivate(', $js);
        $this->assertStringContainsString('function fetchGroupMembers(', $js);
        $this->assertStringContainsString('function maybeLoadMoreMembers(', $js);
        $this->assertStringContainsString("threadUrl(currentThreadId, '/participants'", $js);
        $this->assertStringContainsString('window.showToast', $js);
        $this->assertStringContainsString('showConfirmDeleteModal', $js);
        $this->assertStringContainsString('js-remove-group-member', $js);
        $this->assertStringContainsString('e.stopPropagation()', $js);
        $this->assertStringContainsString("openPeerCard(Number(row.getAttribute('data-id')), true)", $js);
        $this->assertStringContainsString("params.set('exclude_thread_id'", $js);
        $this->assertStringContainsString('function maybeFillGroupMembers(', $js);
        $this->assertStringContainsString('if (e.removed)', $js);
        $this->assertStringContainsString("showToast(message, 'success')", $js);
        $this->assertStringContainsString("getElementById('addGroupMembersForm')", $js);
        $addFormPos = strpos($js, "getElementById('addGroupMembersForm')");
        $this->assertNotFalse($addFormPos);
        $addFormChunk = substr($js, $addFormPos, 280);
        $this->assertStringContainsString('e.preventDefault()', $addFormChunk);
        $this->assertStringContainsString('submitAddGroupMembers()', $addFormChunk);
        $this->assertStringContainsString("peerHit.addEventListener('click'", $js);
        $this->assertStringContainsString("peerHit.addEventListener('keydown'", $js);
        $this->assertStringContainsString('headerPeerActivate()', $js);
    }

    public function test_echo_badge_script_hides_zero_and_ignores_foreign_thread_read(): void
    {
        $blade = (string) file_get_contents(resource_path('views/includes/chat/echo.blade.php'));

        $this->assertStringContainsString("@if(auth()->check() && auth()->user()->can('messages.view'))", $blade);
        $this->assertStringContainsString('KidsCrmChatSetUnread', $blade);
        $this->assertStringContainsString("badge.style.display = n > 0 ? '' : 'none'", $blade);
        $this->assertStringContainsString('if (inboxBound || !window.Echo)', $blade);
        $this->assertStringContainsString("Number(payload.user_id) === me", $blade);
        $this->assertStringContainsString("typeof payload.unread_total !== 'undefined'", $blade);
        $this->assertStringContainsString('.inbox.bump', $blade);
        $this->assertStringContainsString('.thread.read', $blade);
        $this->assertStringContainsString("route('chat.api.unread')", $blade);
        $this->assertStringContainsString('refreshInboxOrUnread', $blade);
        $this->assertStringContainsString('KidsCrmChatRefreshInbox', $blade);
        $this->assertStringContainsString('KidsCrmChatOnInboxBump', $blade);
        $this->assertStringContainsString('KidsCrmChatOnInboxBump === \'function\'', $blade);
        $this->assertStringNotContainsString("socketState() === 'connected'", $blade);
        $this->assertStringNotContainsString('onChatPage ? 1000 : 12000', $blade);
        $this->assertStringNotContainsString('lastFallbackPoll', $blade);
        $this->assertStringNotContainsString('12000', $blade);
        $this->assertStringContainsString("connection.bind('connected'", $blade);
    }

    public function test_chat_page_renders_echo_handoff_so_open_dialog_owns_the_badge(): void
    {
        $html = $this->get(route('chat.index'))->assertOk()->getContent();
        $this->assertStringContainsString('KidsCrmChatOnInboxBump', $html);
        $this->assertStringContainsString("channel.listen('.inbox.bump'", $html);
        $this->assertStringContainsString('id="chatApp"', $html);

        $blade = (string) file_get_contents(resource_path('views/chat/index.blade.php'));
        $this->assertStringContainsString("@vite(['resources/js/chat.js'])", $blade);
    }

    public function test_reverb_overlay_is_only_for_users_with_system_monitors_permission_and_stays_on_top(): void
    {
        $userHtml = $this->get(route('chat.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('id="js-reverb-status"', $userHtml);

        $superadmin = $this->createUserWithRole('superadmin');
        $superadmin->forceFill(['system_monitors' => true])->save();
        $this->actingInPartner($superadmin);
        $html = $this->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('id="js-reverb-status"', $html);
        $this->assertStringContainsString('data-status-url="'.route('chat.api.reverb-status').'"', $html);
        $this->assertStringContainsString('z-index: 20000', $html);
        $this->assertStringContainsString('position: fixed', $html);
        $this->assertStringContainsString('data-role="process-dot"', $html);
        $this->assertStringContainsString('data-role="socket-dot"', $html);
        $this->assertStringContainsString("route('chat.api.reverb-status')", (string) file_get_contents(resource_path('views/includes/chat/reverb_status.blade.php')));
        $this->assertStringContainsString("connection.bind('state_change'", (string) file_get_contents(resource_path('views/includes/chat/reverb_status.blade.php')));
        $this->assertStringContainsString('setInterval(refreshProcess, 3000)', (string) file_get_contents(resource_path('views/includes/chat/reverb_status.blade.php')));
        $this->assertStringContainsString('data-role="copy"', $html);
        $this->assertStringContainsString('fa-copy', $html);
        $this->assertStringContainsString('pointer-events: auto', $html);
        $blade = (string) file_get_contents(resource_path('views/includes/chat/reverb_status.blade.php'));
        $this->assertStringContainsString('function copyStatus()', $blade);
        $this->assertStringContainsString('navigator.clipboard.writeText', $blade);
        $this->assertStringContainsString("процесс: '", $blade);
        $this->assertStringContainsString("сокет: '", $blade);
    }

    public function test_mobile_chat_shell_uses_local_fa_tabs_keeps_header_and_bottom_nav_from_tablet(): void
    {
        $html = $this->get(route('chat.index'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/<body[^>]*\bchat-immersive\b/',
            $html
        );
        $this->assertStringContainsString('maximum-scale=1, user-scalable=no', $html);
        $css = (string) file_get_contents(resource_path('css/chat.css'));
        $this->assertStringContainsString('html { touch-action: pan-x pan-y; }', $css);
        $this->assertStringContainsString('max-width: 991.98px', $css);
        $this->assertStringContainsString('body.chat-immersive .main-header', $css);
        $this->assertStringContainsString('body.chat-immersive .main-sidebar', $css);
        $this->assertStringContainsString('body.chat-immersive .main-footer', $css);
        $mediaPos = strpos($css, '@media (max-width: 991.98px)');
        $headerPos = strpos($css, 'body.chat-immersive .main-header');
        $footerHidePos = strpos($css, 'body.chat-immersive .main-footer');
        $navDefaultPos = strpos($css, '.chat-mobile-nav,');
        $this->assertNotFalse($mediaPos);
        $this->assertNotFalse($headerPos);
        $this->assertNotFalse($footerHidePos);
        $this->assertNotFalse($navDefaultPos);
        $this->assertGreaterThan(
            $mediaPos,
            $headerPos,
            'Правила шапки кабинета только внутри медиа < 992px, не на десктопе'
        );
        $this->assertGreaterThan(
            $mediaPos,
            $footerHidePos,
            'Скрытие футера только внутри медиа < 992px, не на десктопе'
        );
        $this->assertLessThan(
            $mediaPos,
            $navDefaultPos,
            'Нижняя панель по умолчанию скрыта (десктоп), показ — в медиа'
        );
        $this->assertStringContainsString(
            '#chatApp.is-dialog-open .chat-mobile-nav { display: none; }',
            $css,
            'Нижняя панель скрывается в открытом диалоге, как в Telegram'
        );
        $headerBlock = substr($css, $headerPos, 180);
        $this->assertStringContainsString('flex: 0 0 auto', $headerBlock);
        $this->assertStringNotContainsString('display: none', $headerBlock);
        $footerBlock = substr($css, $footerHidePos, 160);
        $this->assertStringContainsString('display: none !important', $footerBlock);
        $this->assertStringContainsString('id="chatMobileNav"', $html);
        $this->assertStringContainsString('id="chatMobileBack"', $html);
        $this->assertStringContainsString('fa-solid fa-arrow-left', $html);
        $this->assertStringContainsString('fa-solid fa-address-book', $html);
        $this->assertStringContainsString('fa-solid fa-comment', $html);
        $this->assertStringContainsString('fa-solid fa-comments', $html);
        $this->assertStringContainsString('fa-solid fa-user', $html);
        $this->assertStringContainsString('Личные сообщения', $html);
        $this->assertStringContainsString('id="openCreateGroupMobileBtn"', $html);
        $this->assertStringContainsString('id="createGroupNameModal"', $html);
        $this->assertStringContainsString('id="createGroupMembersModal"', $html);
        $this->assertStringContainsString('id="chatPaneContacts"', $html);
        $this->assertStringContainsString('id="chatPaneGroups"', $html);
        $this->assertStringContainsString('id="groupThreads"', $html);
        $this->assertStringNotContainsString('chat-groups-stub', $html);
        $this->assertStringContainsString('id="chatPaneAccount"', $html);
        $this->assertStringContainsString('id="accountCardBody"', $html);
        $this->assertStringContainsString('id="accountCardError"', $html);
        $this->assertStringContainsString('id="contactsMount"', $html);
        $this->assertStringContainsString('id="contactsModalBody"', $html);
        $this->assertStringContainsString('data-mobile-tab="messages"', $html);
        $this->assertStringContainsString('aria-selected="true"', $html);
        $this->assertStringContainsString('d-none d-lg-inline-block', $html);
        $this->assertStringContainsString('col-lg-4', $html);
        $this->assertStringContainsString('col-lg-8', $html);
        $this->assertStringNotContainsString('ka-f.fontawesome.com', $html);
        $this->assertStringNotContainsString('js/fontawesome/fontawesome.js', $html);
        $this->assertStringContainsString('Создать группу', $html);

        $chatBlade = (string) file_get_contents(resource_path('views/chat/index.blade.php'));
        $this->assertStringNotContainsString('account-settings', $chatBlade);

        $navStart = strpos($html, 'id="chatMobileNav"');
        $this->assertNotFalse($navStart);
        $nav = substr($html, $navStart, 1800);
        $this->assertStringContainsString('js-chat-private-unread-count', $nav);
        $this->assertStringContainsString('js-chat-group-unread-count', $nav);
        $this->assertStringContainsString('id="chatPrivateUnreadBadge"', $nav);
        $this->assertStringContainsString('id="chatGroupUnreadBadge"', $nav);
        $this->assertStringNotContainsString('js-chat-unread-count', $nav);
        $contactsPos = strpos($nav, 'data-mobile-tab="contacts"');
        $messagesPos = strpos($nav, 'data-mobile-tab="messages"');
        $groupsPos = strpos($nav, 'data-mobile-tab="groups"');
        $accountPos = strpos($nav, 'data-mobile-tab="account"');
        $this->assertNotFalse($contactsPos);
        $this->assertNotFalse($messagesPos);
        $this->assertNotFalse($groupsPos);
        $this->assertNotFalse($accountPos);
        $this->assertLessThan($messagesPos, $contactsPos);
        $this->assertLessThan($groupsPos, $messagesPos);
        $this->assertLessThan($accountPos, $groupsPos);

        $modalStart = strpos($html, 'id="contactsModal"');
        $modalEnd = strpos($html, 'id="peerCardModal"');
        $this->assertNotFalse($modalStart);
        $this->assertNotFalse($modalEnd);
        $modal = substr($html, $modalStart, $modalEnd - $modalStart);
        $this->assertStringContainsString('id="contactsMount"', $modal);
        $this->assertStringContainsString('id="contactsTeamFilter"', $modal);
        $this->assertStringNotContainsString('modal-fullscreen', $modal);

        $cabinet = $this->get(route('dashboard'))->assertOk()->getContent();
        $this->assertDoesNotMatchRegularExpression(
            '/<body[^>]*\bchat-immersive\b/',
            $cabinet
        );
        $this->assertStringNotContainsString('id="chatMobileNav"', $cabinet);
        $this->assertStringNotContainsString('user-scalable=no', $cabinet);
    }
}
