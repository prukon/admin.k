<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Анонс /doc и chat.html должны совпадать с фактическим UX чата
 * (бейдж info, не вспышка в открытом диалоге, без HTTP-опроса inbox, Echo без wsPath).
 */
final class ChatDocumentationContractTest extends TestCase
{
    public function test_doc_index_announces_chat_without_contradicting_live_ux(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="chat-index"', $html);
        $start = strpos($html, 'id="chat-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="cabinet-attach-team-in-app-body-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('badge badge-info', $chunk);
        $this->assertStringContainsString('не вспыхивает', $chunk);
        $this->assertStringContainsString("wsPath: '/app'", $chunk);
        $this->assertStringContainsString('без HTTP-опроса', $chunk);
        $this->assertStringNotContainsString('1 секунду', $chunk);
        $this->assertStringNotContainsString('12 секунд', $chunk);
        $this->assertStringContainsString('reverb-status', $chunk);
        $this->assertStringContainsString('KidsCrmChatOnInboxBump', $chunk);
        $this->assertStringContainsString('/docs/documentation/chat', $chunk);
        $this->assertStringContainsString('nginx.ssl.conf_reverb', $chunk);
        $this->assertStringContainsString(':6009', $chunk);
        $this->assertStringContainsString('ChatReverbOverlayFeatureTest', $chunk);
        $this->assertStringContainsString('reverb-status-overlay-index', $chunk);
        $this->assertStringContainsString('chat-presence-index', $chunk);
        $this->assertStringContainsString('ChatPresenceUxFeatureTest', $chunk);
        $this->assertStringContainsString('Черновик', $chunk);
        $this->assertStringContainsString('draft_body', $chunk);
        $this->assertStringContainsString('ChatDraftUxFeatureTest', $chunk);
        $this->assertStringContainsString('/doc#chat-draft-index', $chunk);
        $this->assertStringContainsString('chat-contacts-team-filter-index', $chunk);
        $this->assertStringContainsString('ChatContactsTeamFilterFeatureTest', $chunk);
        $this->assertStringContainsString('errors.team_id', $chunk);
        $this->assertStringContainsString('Создать группу', $chunk);
        $this->assertStringContainsString('chat-groups-index', $chunk);
        $this->assertStringContainsString('ChatGroupThreadFeatureTest', $chunk);
        $this->assertStringContainsString('chat-team-groups-index', $chunk);
        $this->assertStringContainsString('chat-group-list-title-index', $chunk);
        $this->assertStringContainsString('chat-group-members-index', $chunk);
        $this->assertStringContainsString('ChatGroupMembersFeatureTest', $chunk);
        $this->assertStringContainsString('ChatHeaderSubtitleFeatureTest', $chunk);
        $this->assertStringContainsString('ChatHeaderSubtitleUxFeatureTest', $chunk);
        $this->assertStringContainsString('/doc#chat-header-subtitle-index', $chunk);
        $this->assertStringContainsString('/doc#chat-mobile-index', $chunk);
        $this->assertStringContainsString('/doc#chat-mobile-inbox-split-index', $chunk);
        $this->assertStringContainsString('chat-immersive', $chunk);
        $this->assertStringContainsString('fa-address-book', $chunk);
        $this->assertStringContainsString('Личные сообщения', $chunk);
        $this->assertStringContainsString('ChatMobileFeatureTest', $chunk);
        $this->assertStringContainsString('ChatMobileInboxSplitFeatureTest', $chunk);
        $this->assertStringContainsString('ChatMobileInboxSplitUxFeatureTest', $chunk);
        $this->assertStringContainsString('ChatMobileUxFeatureTest', $chunk);
        $this->assertStringContainsString('ChatMobileHistoryUxFeatureTest', $chunk);
        $this->assertStringContainsString('ChatMobileLayoutUxFeatureTest', $chunk);
        $this->assertStringContainsString('ChatMobileContactsAlignFeatureTest', $chunk);
        $this->assertStringContainsString('ChatMobileContactsAlignUxFeatureTest', $chunk);
        $this->assertStringContainsString('/docs/documentation/chat#mobile', $chunk);
        $this->assertStringContainsString('resources/css/chat.css', $chunk);
        $this->assertStringContainsString('resources/js/chat.js', $chunk);
        $this->assertStringContainsString('Шапка кабинета видна', $chunk);
        $this->assertStringContainsString('нижняя панель на время диалога скрыта', $chunk);
        $this->assertStringContainsString('pinch-zoom', $chunk);
        $this->assertStringContainsString('история догружается заранее', $chunk);
        $this->assertStringContainsString('chat-private-thread-identity-index', $chunk);
        $this->assertStringContainsString('ChatPrivateThreadIdentityFeatureTest', $chunk);
        $this->assertStringContainsString('ChatPrivateThreadIdentityUxFeatureTest', $chunk);
        $this->assertStringContainsString('chat-thread-delete-index', $chunk);
        $this->assertStringContainsString('ChatThreadDeleteFeatureTest', $chunk);
        $this->assertStringContainsString('messages.threads.delete', $chunk);
        $this->assertStringNotContainsString('красный бейдж', $chunk);
        $this->assertStringNotContainsString("wsPath: '/app'", str_replace(
            "<b>без</b> <code>wsPath: '/app'</code>",
            '',
            $chunk
        ));
    }

    public function test_doc_index_announces_chat_header_subtitle_without_contradicting_live_ux(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="chat-header-subtitle-index"', $html);
        $start = strpos($html, 'id="chat-header-subtitle-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="chat-private-thread-identity-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('#threadSubtitle', $chunk);
        $this->assertStringContainsString('#threadTitle', $chunk);
        $this->assertStringContainsString('display:none', $chunk);
        $this->assertStringContainsString('Выберите диалог', $chunk);
        $this->assertStringContainsString('text-align: left', $chunk);
        $this->assertStringContainsString('.chat-header-text', $chunk);
        $this->assertStringContainsString('body.sidebar-mini', $chunk);
        $this->assertStringContainsString('membersCountLabel', $chunk);
        $this->assertStringContainsString('members_total', $chunk);
        $this->assertStringContainsString('visibleGroupMembersCount', $chunk);
        $this->assertStringContainsString('1 участник', $chunk);
        $this->assertStringContainsString('2 участника', $chunk);
        $this->assertStringContainsString('5 участников', $chunk);
        $this->assertStringContainsString('11 участников', $chunk);
        $this->assertStringContainsString('21 участник', $chunk);
        $this->assertStringContainsString('не</b> «онлайн»', $chunk);
        $this->assertStringContainsString('dialogStatusLabel', $chunk);
        $this->assertStringContainsString('120 секунд', $chunk);
        $this->assertStringContainsString('был(а) в сети <b>2</b> минуты назад', $chunk);
        $this->assertStringContainsString('не «1 минуту»', $chunk);
        $this->assertStringContainsString('5 минут назад', $chunk);
        $this->assertStringContainsString('Europe/Moscow', $chunk);
        $this->assertStringContainsString('16:50 18 августа 2028', $chunk);
        $this->assertStringContainsString('d.m.Y H:i', $chunk);
        $this->assertStringContainsString('#peerCardModal', $chunk);
        $this->assertStringContainsString('last_message_time', $chunk);
        $this->assertStringContainsString('header_subtitle', $chunk);
        $this->assertStringContainsString('peer_presence_label', $chunk);
        $this->assertStringContainsString('openThread', $chunk);
        $this->assertStringContainsString('startDialog', $chunk);
        $this->assertStringContainsString('textContent', $chunk);
        $this->assertStringContainsString('closeCurrentThread', $chunk);
        $this->assertStringContainsString('last_seen_at', $chunk);
        $this->assertStringContainsString('peer_is_online', $chunk);
        $this->assertStringContainsString('не</b> трогает', $chunk);
        $this->assertStringContainsString('не</b> переписывает', $chunk);
        $this->assertStringContainsString('serializeThreadHeader', $chunk);
        $this->assertStringContainsString('setThreadSubtitle', $chunk);
        $this->assertStringContainsString('/docs/documentation/chat#header-subtitle', $chunk);
        $this->assertStringContainsString('ChatHeaderSubtitleFeatureTest', $chunk);
        $this->assertStringContainsString('ChatHeaderSubtitleUxFeatureTest', $chunk);
        $this->assertStringContainsString('Не в этом срезе', $chunk);
        $this->assertStringContainsString('счётчик в списке слева', $chunk);

        $this->assertStringNotContainsString('был(а) в сети 1 минуту', $chunk);
        $this->assertStringNotContainsString('Ping обновляет', $chunk);
        $this->assertStringNotContainsString('ping обновляет подзаголовок', $chunk);
        $this->assertStringNotContainsString('inbox.bump обновляет шапку', $chunk);
        $this->assertStringNotContainsString('в списке слева — число участников', $chunk);
        $this->assertStringNotContainsString('карточка last_seen_label</code> — «был(а) в сети в', $chunk);
        $this->assertStringNotContainsString('H:i j F Y', $chunk);
    }

    public function test_chat_page_docs_match_sidebar_badge_and_reverb_status_access(): void
    {
        $html = $this->docFile('chat.html');

        $this->assertStringContainsString('/doc#chat-draft-index', $html);
        $this->assertStringContainsString('id="draft"', $html);
        $this->assertStringContainsString('badge badge-info', $html);
        $this->assertStringContainsString('не вспыхивает', $html);
        $this->assertStringContainsString("wsPath: '/app'", $html);
        $this->assertStringContainsString('без <code>messages.view</code>', $html);
        $this->assertStringContainsString('KidsCrmChatOnInboxBump', $html);
        $this->assertStringContainsString('nginx.ssl.conf_reverb', $html);
        $this->assertStringContainsString('127.0.0.1:6009', $html);
        $this->assertStringContainsString('ChatReverbOverlayFeatureTest', $html);
        $this->assertStringContainsString('cabinet_diagnostics', $html);
        $this->assertStringContainsString('settings.reverbOverlay.manage', $html);
        $this->assertStringContainsString('CabinetDiagnostics::shouldShow()', $html);
        $this->assertStringContainsString('#js-reverb-status', $html);
        $this->assertStringContainsString('/doc#reverb-status-overlay-index', $html);
        $this->assertStringContainsString('id="reverb-overlay"', $html);
        $this->assertStringContainsString('/doc#chat-index', $html);
        $this->assertStringNotContainsString('доступ по Form Request только у superadmin', $html);
        $this->assertStringNotContainsString('не-superadmin 403', $html);
        $this->assertStringNotContainsString('красный бейдж', $html);
        $this->assertStringNotContainsString(
            'опрос <code>is_read</code> раз в 12 секунд',
            $html
        );
        $this->assertStringNotContainsString(
            'опрос <code>/chat/api/unread</code> раз в 12 секунд без перезагрузки',
            $html
        );
        $this->assertStringNotContainsString('раз в 1 секунду', $html);
        $this->assertStringNotContainsString('раз в 12 секунд', $html);
        $this->assertStringContainsString('без HTTP-опроса', $html);
        $this->assertStringContainsString('unread_count', $html);
        $this->assertStringContainsString('last_message_id', $html);
        $this->assertStringContainsString('participants_thread_user_unique', $html);
        $this->assertStringContainsString('restoreOrCreateParticipant', $html);
        $this->assertStringContainsString('restoreParticipantIfTrashed', $html);
        $this->assertStringContainsString('has(participants, 2)', $html);
        $this->assertStringContainsString('ChatPartnerScopeFullAccessFeatureTest', $html);
        $this->assertStringContainsString('ChatPrivateThreadIdentityFeatureTest', $html);
        $this->assertStringContainsString('ChatPrivateThreadIdentityUxFeatureTest', $html);
        $this->assertStringContainsString('ChatThreadDeleteFeatureTest', $html);
        $this->assertStringContainsString('ChatThreadDeleteFullAccessFeatureTest', $html);
        $this->assertStringContainsString('ChatThreadDeleteNonAjaxSafetyNetFeatureTest', $html);
        $this->assertStringContainsString('ChatThreadDeleteAjaxContractFeatureTest', $html);
        $this->assertStringContainsString('ChatThreadDeleteUxFeatureTest', $html);
        $this->assertStringContainsString('messages.threads.delete', $html);
        $this->assertStringContainsString('chat.api.threads.destroy', $html);
        $this->assertStringContainsString('deleteThreadBtn', $html);
        $this->assertStringContainsString('/doc#chat-thread-delete-index', $html);
        $this->assertStringContainsString('ChatUnreadCounterFeatureTest', $html);
        $this->assertStringContainsString('presence/ping', $html);
        $this->assertStringContainsString('last_seen_at', $html);
        $this->assertStringContainsString('parent_full_name', $html);
        $this->assertStringContainsString('contact-team', $html);
        $this->assertStringContainsString('по центру на одной линии с именем', $html);
        $this->assertStringNotContainsString('Ниже — группы ученика', $html);
        $this->assertStringContainsString('users.lastname', $html);
        $this->assertStringContainsString('PEER_USER_COLUMNS', $html);
        $this->assertStringContainsString('parents.firstname', $html);
        $this->assertStringContainsString('contactsTeamFilter', $html);
        $this->assertStringContainsString('contactsTeamError', $html);
        $this->assertStringContainsString('team_id', $html);
        $this->assertStringContainsString('Без группы', $html);
        $this->assertStringContainsString('2 минут', $html);
        $this->assertStringContainsString('chat-li-unread', $html);
        $this->assertStringContainsString('#f3a12b', $html);
        $this->assertStringContainsString('#msgInput', $html);
        $this->assertStringContainsString('draft_body', $html);
        $this->assertStringContainsString('Черновик', $html);
        $this->assertStringContainsString('chat.api.threads.draft', $html);
        $this->assertStringContainsString('ChatDraftFeatureTest', $html);
        $this->assertStringContainsString('ChatDraftUxFeatureTest', $html);
        $this->assertStringContainsString('ChatContactsTeamFilterFeatureTest', $html);
        $this->assertStringContainsString('ChatContactsTeamFilterUxFeatureTest', $html);
        $this->assertStringContainsString('/doc#chat-contacts-team-filter-index', $html);
        $this->assertStringContainsString('не Select2', $html);
        $this->assertStringContainsString('не групповой чат', $html);
        $this->assertStringContainsString('ChatDraftDocumentationContractTest', $html);
        $this->assertStringContainsString('ChatPresenceFeatureTest', $html);
        $this->assertStringContainsString('ChatPresenceUxFeatureTest', $html);
        $this->assertStringContainsString('ChatHeaderSubtitleFeatureTest', $html);
        $this->assertStringContainsString('ChatHeaderSubtitleUxFeatureTest', $html);
        $this->assertStringContainsString('peerCardError', $html);
        $this->assertStringContainsString('chat.api.users.show', $html);
        $this->assertStringContainsString('last_seen_label', $html);
        $this->assertStringContainsString('threadSubtitle', $html);
        $this->assertStringContainsString('.chat-header-text', $html);
        $this->assertStringContainsString('header_subtitle', $html);
        $this->assertStringContainsString('peer_presence_label', $html);
        $this->assertStringContainsString('был(а) в сети', $html);
        $this->assertStringContainsString('не «1 минуту»', $html);
        $this->assertStringContainsString('H:i j F Y', $html);
        $this->assertStringContainsString('serializeThreadHeader', $html);
        $this->assertStringContainsString('dialogStatusLabel', $html);
        $this->assertStringContainsString('membersCountLabel', $html);
        $this->assertStringContainsString('id="presence"', $html);
        $this->assertStringContainsString('id="header-subtitle"', $html);
        $this->assertStringContainsString('/doc#chat-presence-index', $html);
        $this->assertStringContainsString('/doc#chat-header-subtitle-index', $html);
        $this->assertStringContainsString('без</b> <code>can:messages.view</code>', $html);
        $this->assertStringContainsString('без точки и без', $html);
        $this->assertStringContainsString('id="mobile"', $html);
        $this->assertStringContainsString('/doc#chat-mobile-index', $html);
        $this->assertStringContainsString('/doc#chat-mobile-inbox-split-index', $html);
        $this->assertStringContainsString('id="mobile-inbox-split"', $html);
        $this->assertStringContainsString('chat-immersive', $html);
        $this->assertStringContainsString('991.98px', $html);
        $this->assertStringContainsString('chatMobileNav', $html);
        $this->assertStringContainsString('groupThreads', $html);
        $this->assertStringContainsString('js-chat-private-unread-count', $html);
        $this->assertStringContainsString('paintSplitNavBadges', $html);
        $this->assertStringContainsString('unreadPrivateTotal', $html);
        $this->assertStringContainsString('fa-address-book', $html);
        $this->assertStringContainsString('accountCardBody', $html);
        $this->assertStringContainsString('ChatMobileFeatureTest', $html);
        $this->assertStringContainsString('ChatMobileInboxSplitFeatureTest', $html);
        $this->assertStringContainsString('ChatMobileInboxSplitUxFeatureTest', $html);
        $this->assertStringContainsString('ChatMobileUxFeatureTest', $html);
        $this->assertStringContainsString('ChatMobileHistoryUxFeatureTest', $html);
        $this->assertStringContainsString('ChatMobileLayoutUxFeatureTest', $html);
        $this->assertStringContainsString('ChatMobileContactsAlignFeatureTest', $html);
        $this->assertStringContainsString('ChatMobileContactsAlignUxFeatureTest', $html);
        $this->assertStringContainsString('openThread._seq', $html);
        $this->assertStringContainsString('100dvh', $html);
        $this->assertStringContainsString('.contact-name', $html);
        $this->assertStringContainsString('body.sidebar-mini', $html);
        $this->assertStringContainsString('ChatPageController', $html);
        $this->assertStringContainsString('unreadTotal', $html);
        $this->assertStringContainsString('Создать группу', $html);
        $this->assertStringContainsString('id="groups"', $html);
        $this->assertStringContainsString('chat.api.threads.groups.store', $html);
        $this->assertStringContainsString('threads.is_group', $html);
        $this->assertStringContainsString('ChatGroupThreadFeatureTest', $html);
        $this->assertStringContainsString('ChatGroupThreadUxFeatureTest', $html);
        $this->assertStringContainsString('ChatSupportIdentityFeatureTest', $html);
        $this->assertStringContainsString('ChatSupportIdentityFullAccessFeatureTest', $html);
        $this->assertStringContainsString('ChatSupportIdentityNonAjaxSafetyNetFeatureTest', $html);
        $this->assertStringContainsString('ChatSupportIdentityAjaxContractFeatureTest', $html);
        $this->assertStringContainsString('ChatSupportIdentityUxFeatureTest', $html);
        $this->assertStringContainsString('id="support-identity"', $html);
        $this->assertStringContainsString('Служба поддержки', $html);
        $this->assertStringContainsString('/doc#chat-support-identity-index', $html);
        $this->assertStringContainsString('первая строка «Служба поддержки»', $html);
        $this->assertStringContainsString('свою карточку (вкладка «Аккаунт»)', $html);
        $this->assertStringContainsString('id="private-thread-identity"', $html);
        $this->assertStringContainsString('/doc#chat-private-thread-identity-index', $html);
        $this->assertStringContainsString('не «Диалог»', $html);
        $this->assertStringContainsString('не ФИО первого участника', $html);
        $this->assertStringContainsString('/doc#chat-group-list-title-index', $html);
        $this->assertStringContainsString('/doc#chat-group-members-index', $html);
        $this->assertStringContainsString('threadListTitle', $html);
        $this->assertStringContainsString('text-align: left', $html);
        $this->assertStringContainsString('@media (max-width: 991.98px)', $html);
        $this->assertStringContainsString('resources/css/chat.css', $html);
        $this->assertStringContainsString('resources/js/chat.js', $html);
        $this->assertStringContainsString('@vite', $html);
        $this->assertStringContainsString('white-space: nowrap', $html);
        $this->assertStringContainsString('нижняя панель на время диалога скрыта', $html);
        $this->assertStringContainsString('гамбургер', $html);
        $this->assertStringContainsString('колокольчик', $html);
        $this->assertStringContainsString('maybeLoadOlder', $html);
        $this->assertStringContainsString('#messagesBox', $html);
        $this->assertStringContainsString('не уезжает в середину экрана', $html);
        $this->assertStringContainsString('margin-top: auto', $html);
        $this->assertStringContainsString('user-scalable=no', $html);
        $this->assertStringContainsString('maximum-scale=1', $html);
        $this->assertStringContainsString('touch-action: pan-x pan-y', $html);
        $this->assertStringContainsString('gesturestart', $html);
        $this->assertStringContainsString('errors.before_id', $html);
        $this->assertStringContainsString('Некорректный идентификатор сообщения.', $html);
        $this->assertStringContainsString('olderPrefetchThreshold', $html);
        $this->assertStringNotContainsString('public/js/chat.js', $html);
        $this->assertStringNotContainsString('все API чата</td>', $html);
        $this->assertStringNotContainsString('нижняя панель в диалоге остаётся', $html);
        $this->assertStringNotContainsString('Скрыты <code>.main-header</code>', $html);
    }

    public function test_doc_catalog_and_controller_title_mention_no_flash_and_no_wspath(): void
    {
        $index = $this->docFile('index.html');
        $controller = (string) file_get_contents(dirname(__DIR__, 3).'/app/Http/Controllers/DocumentationController.php');

        $this->assertStringContainsString('без вспышки в открытом диалоге', $index);
        $this->assertStringContainsString("Reverb без <code>wsPath</code>", $index);
        $this->assertStringContainsString('без вспышки в открытом диалоге', $controller);
        $this->assertStringContainsString('Reverb без wsPath', $controller);
        $this->assertStringContainsString('оверлей статуса процесса/сокета при флаге и settings.reverbOverlay.manage', $controller);
        $this->assertStringContainsString('онлайн (ping без messages.view)', $controller);
        $this->assertStringContainsString('подзаголовок шапки (участники / был(а) в сети)', $controller);
        $this->assertStringContainsString('карточка собеседника из шапки', $controller);
        $this->assertStringContainsString('черновик на сервере', $controller);
        $this->assertStringContainsString('фильтр контактов по учебной группе', $controller);
        $this->assertStringContainsString('создание группы (название + участники)', $controller);
        $this->assertStringContainsString('мобильная нижняя панель с планшета', $controller);
        $this->assertStringContainsString('inbox JSON смешанный без unread_private', $controller);
        $this->assertStringContainsString('шапка кабинета видна', $controller);
        $this->assertStringContainsString('в диалоге низ скрыт', $controller);
        $this->assertStringContainsString('prefetch истории', $controller);
        $this->assertStringContainsString('зум выключен только на /chat', $controller);
        $this->assertStringContainsString('Vite CSS/JS только на /chat', $controller);
        $this->assertStringContainsString('суперадмин в чате как «Служба поддержки»', $controller);
        $this->assertStringContainsString('UNIQUE участника (thread_id, user_id)', $controller);
        $this->assertStringContainsString('повтор лички после 0/1 живого не плодит тред', $controller);
        $this->assertStringContainsString('/doc#chat-support-identity-index', $index);
        $this->assertStringContainsString('/doc#chat-private-thread-identity-index', $index);
        $this->assertStringContainsString('/doc#chat-contacts-team-filter-index', $index);
        $this->assertStringContainsString('/doc#chat-mobile-index', $index);
        $this->assertStringContainsString('/doc#chat-mobile-inbox-split-index', $index);
        $this->assertStringContainsString('/doc#chat-groups-index', $index);
        $this->assertStringContainsString('/doc#chat-team-groups-index', $index);
        $this->assertStringContainsString('/doc#chat-group-list-title-index', $index);
        $this->assertStringContainsString('/doc#chat-group-members-index', $index);
        $this->assertStringContainsString('/doc#chat-header-subtitle-index', $index);
        $this->assertStringContainsString('/doc#chat-thread-delete-index', $index);
        $this->assertStringContainsString('скрытое messages.threads.delete', $controller);
        $this->assertStringContainsString('имя группы в списке не', $controller);
        $this->assertStringContainsString('добавить/удалить — admin/superadmin', $controller);
        $this->assertStringContainsString('0 участников soft-delete кроме team_id', $controller);
        $this->assertStringContainsString('Создать группу', $index);
        $this->assertStringNotContainsString('без вложений и без групп —', $index);
    }

    public function test_doc_index_announces_chat_mobile_without_contradicting_live_ux(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="chat-mobile-index"', $html);
        $start = strpos($html, 'id="chat-mobile-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="cabinet-diagnostics-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('991.98px', $chunk);
        $this->assertStringContainsString('chat-immersive', $chunk);
        $this->assertStringContainsString('chat.index', $chunk);
        $this->assertStringContainsString('chatMobileNav', $chunk);
        $this->assertStringContainsString('fa-address-book', $chunk);
        $this->assertStringContainsString('Личные сообщения', $chunk);
        $this->assertStringContainsString('js-chat-unread-count', $chunk);
        $this->assertStringContainsString('js-chat-private-unread-count', $chunk);
        $this->assertStringContainsString('js-chat-group-unread-count', $chunk);
        $this->assertStringContainsString('groupThreads', $chunk);
        $this->assertStringContainsString('unreadPrivateTotal', $chunk);
        $this->assertStringContainsString('unreadGroupTotal', $chunk);
        $this->assertStringContainsString('Групп нет', $chunk);
        $this->assertStringContainsString('paintSplitNavBadges', $chunk);
        $this->assertStringContainsString('!is_group', $chunk);
        $this->assertStringContainsString('ChatPageController', $chunk);
        $this->assertStringContainsString('unreadTotal', $chunk);
        $this->assertStringContainsString('is-dialog-open', $chunk);
        $this->assertStringContainsString('chatMobileBack', $chunk);
        $this->assertStringContainsString('contactsModal().show()', $chunk);
        $this->assertStringContainsString('openContactsBtn', $chunk);
        $this->assertStringContainsString('Создать группу', $chunk);
        $this->assertStringContainsString('accountCardBody', $chunk);
        $this->assertStringContainsString('accountCardError', $chunk);
        $this->assertStringContainsString('/account-settings/user/edit', $chunk);
        $this->assertStringContainsString('GET /chat/api/users/{me}', $chunk);
        $this->assertStringContainsString('403 даже на себя', $chunk);
        $this->assertStringContainsString('resources/css/chat.css', $chunk);
        $this->assertStringContainsString('resources/js/chat.js', $chunk);
        $this->assertStringContainsString('@vite', $chunk);
        $this->assertStringContainsString('npm run build', $chunk);
        $this->assertStringContainsString('не в общий бандл', $chunk);
        $this->assertStringContainsString('includes.fontawesome', $chunk);
        $this->assertStringContainsString('ChatMobileFeatureTest', $chunk);
        $this->assertStringContainsString('ChatMobileInboxSplitFeatureTest', $chunk);
        $this->assertStringContainsString('ChatMobileInboxSplitUxFeatureTest', $chunk);
        $this->assertStringContainsString('ChatMobileUxFeatureTest', $chunk);
        $this->assertStringContainsString('ChatMobileHistoryUxFeatureTest', $chunk);
        $this->assertStringContainsString('ChatMobileLayoutUxFeatureTest', $chunk);
        $this->assertStringContainsString('ChatMobileContactsAlignFeatureTest', $chunk);
        $this->assertStringContainsString('ChatMobileContactsAlignUxFeatureTest', $chunk);
        $this->assertStringContainsString('/docs/documentation/chat#mobile', $chunk);
        $this->assertStringContainsString('/docs/documentation/chat#mobile-inbox-split', $chunk);
        $this->assertStringContainsString('/doc#chat-mobile-inbox-split-index', $chunk);
        $this->assertStringContainsString('/doc#chat-index', $chunk);
        $this->assertStringContainsString('/doc#chat-groups-index', $chunk);
        $this->assertStringContainsString('гамбургер', $chunk);
        $this->assertStringContainsString('колокольчик', $chunk);
        $this->assertStringContainsString('Нижняя панель на время диалога скрыта', $chunk);
        $this->assertStringContainsString('pinch-zoom', $chunk);
        $this->assertStringContainsString('начинается заранее', $chunk);
        $this->assertStringContainsString('.main-header', $chunk);
        $this->assertStringContainsString('.main-footer', $chunk);
        $this->assertStringContainsString('olderPrefetchThreshold', $chunk);
        $this->assertStringContainsString('maybeLoadOlder', $chunk);
        $this->assertStringContainsString('scrollTop &lt; 40', $chunk);
        $this->assertStringContainsString('before_id=0', $chunk);
        $this->assertStringContainsString('errors.before_id', $chunk);
        $this->assertStringContainsString('white-space: nowrap', $chunk);
        $this->assertStringContainsString('min-width: 6.5rem', $chunk);
        $this->assertStringContainsString('margin-top: auto', $chunk);
        $this->assertStringContainsString('user-scalable=no', $chunk);
        $this->assertStringContainsString('preventPageZoom', $chunk);
        $this->assertStringContainsString('gesturestart', $chunk);
        $this->assertStringContainsString('POST/PATCH/DELETE <code>/chat</code>', $chunk);
        $this->assertStringContainsString('#messagesBox', $chunk);
        $this->assertStringContainsString('openThread._seq', $chunk);
        $this->assertStringContainsString('100dvh', $chunk);
        $this->assertStringContainsString('.contact-name', $chunk);
        $this->assertStringContainsString('text-align: left', $chunk);

        $this->assertStringNotContainsString('public/js/chat.js', $chunk);
        $this->assertStringNotContainsString('без Vite', $chunk);
        $this->assertStringNotContainsString('заглушка групп', $chunk);
        $this->assertStringNotContainsString('без списка групп', $chunk);
        $this->assertStringNotContainsString('список групп в этом срезе не рисуем', $chunk);
        $this->assertStringNotContainsString('появляются в списке сообщений', $chunk);
        $this->assertStringNotContainsString('групповые чаты включены', $chunk);
        $this->assertStringNotContainsString('редирект на /account-settings', $chunk);
        $this->assertStringNotContainsString('прячет кабинет', $chunk);
        $this->assertStringNotContainsString('Нижняя панель в открытом диалоге остаётся', $chunk);
        $this->assertStringNotContainsString('нижняя панель в диалоге остаётся', $chunk);
    }

    public function test_doc_index_announces_chat_mobile_inbox_split_without_contradicting_live_ux(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="chat-mobile-inbox-split-index"', $html);
        $start = strpos($html, 'id="chat-mobile-inbox-split-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="trainer-salary-kansas-integer-averages-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end, 'Анонс сплита inbox должен стоять первым на /doc.');
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('991.98px', $chunk);
        $this->assertStringContainsString('Личные сообщения', $chunk);
        $this->assertStringContainsString('!is_group', $chunk);
        $this->assertStringContainsString('#groupThreads', $chunk);
        $this->assertStringContainsString('#chatPaneGroups', $chunk);
        $this->assertStringContainsString('is_group', $chunk);
        $this->assertStringContainsString('threadsCache', $chunk);
        $this->assertStringContainsString('renderThreads', $chunk);
        $this->assertStringContainsString('Групп нет', $chunk);
        $this->assertStringContainsString('Диалогов нет', $chunk);
        $this->assertStringContainsString('#threadSearch', $chunk);
        $this->assertStringContainsString('js-chat-private-unread-count', $chunk);
        $this->assertStringContainsString('js-chat-group-unread-count', $chunk);
        $this->assertStringContainsString('js-chat-unread-count', $chunk);
        $this->assertStringContainsString('unreadPrivateTotal', $chunk);
        $this->assertStringContainsString('unreadGroupTotal', $chunk);
        $this->assertStringContainsString('unreadTotal', $chunk);
        $this->assertStringContainsString('ChatPageController', $chunk);
        $this->assertStringContainsString('paintSplitNavBadges', $chunk);
        $this->assertStringContainsString('applyUnread', $chunk);
        $this->assertStringContainsString('data-mobile-tab="groups"', $chunk);
        $this->assertStringContainsString('is-dialog-open', $chunk);
        $this->assertStringContainsString('js-open-create-group', $chunk);
        $this->assertStringContainsString('GET /chat/api/threads', $chunk);
        $this->assertStringContainsString('unread_total', $chunk);
        $this->assertStringContainsString('unread_private', $chunk);
        $this->assertStringContainsString('peer_id: null', $chunk);
        $this->assertStringContainsString('Диалог', $chunk);
        $this->assertStringContainsString('/docs/documentation/chat#mobile-inbox-split', $chunk);
        $this->assertStringContainsString('ChatMobileInboxSplitFeatureTest', $chunk);
        $this->assertStringContainsString('ChatMobileInboxSplitUxFeatureTest', $chunk);
        $this->assertStringContainsString('BladeInlineJsSyntaxTest', $chunk);
        $this->assertStringContainsString('ChatDocumentationContractTest', $chunk);

        $this->assertStringContainsString('общий</b> список', $chunk);
        $this->assertStringContainsString('без</b> поиска', $chunk);
        $this->assertStringNotContainsString('десктоп слева тоже разделён', $chunk);
        $this->assertStringNotContainsString('поиск на вкладке «Чаты»', $chunk);
        $this->assertStringNotContainsString('unread_private в JSON unread', $chunk);
        $this->assertStringNotContainsString('сервер фильтрует is_group', $chunk);
        $this->assertStringNotContainsString('chat-groups-stub', $chunk);
    }

    public function test_doc_index_announces_chat_presence_without_contradicting_live_ux(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="chat-presence-index"', $html);
        $start = strpos($html, 'id="chat-presence-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="admin-password-change-toast-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('last_seen_at', $chunk);
        $this->assertStringContainsString('2 минут', $chunk);
        $this->assertStringContainsString('120 секунд', $chunk);
        $this->assertStringContainsString('/presence/ping', $chunk);
        $this->assertStringContainsString('без</b> права', $chunk);
        $this->assertStringContainsString('messages.view', $chunk);
        $this->assertStringContainsString('{ok: true}', $chunk);
        $this->assertStringContainsString('не 302', $chunk);
        $this->assertStringContainsString('last_message_time', $chunk);
        $this->assertStringContainsString('последнего сообщения', $chunk);
        $this->assertStringContainsString('chat-li-unread', $chunk);
        $this->assertStringContainsString('#f3a12b', $chunk);
        $this->assertStringContainsString('#msgInput', $chunk);
        $this->assertStringContainsString('Черновик', $chunk);
        $this->assertStringContainsString('draft_body', $chunk);
        $this->assertStringContainsString('не</b> рисуем', $chunk);
        $this->assertStringContainsString('красной нет', $chunk);
        $this->assertStringContainsString('parent_full_name', $chunk);
        $this->assertStringContainsString('contact-team', $chunk);
        $this->assertStringContainsString('.contact-name', $chunk);
        $this->assertStringContainsString('text-align: left', $chunk);
        $this->assertStringContainsString('body.sidebar-mini', $chunk);
        $this->assertStringContainsString('по центру на одной линии с именем', $chunk);
        $this->assertStringContainsString('parents.firstname', $chunk);
        $this->assertStringContainsString('contactsTeamFilter', $chunk);
        $this->assertStringContainsString('team_id', $chunk);
        $this->assertStringContainsString('lastname', $chunk);
        $this->assertStringContainsString('threadPeerHit', $chunk);
        $this->assertStringContainsString('#threadSubtitle', $chunk);
        $this->assertStringContainsString('был(а) в сети', $chunk);
        $this->assertStringContainsString('peer_presence_label', $chunk);
        $this->assertStringContainsString('header_subtitle', $chunk);
        $this->assertStringContainsString('is-idle', $chunk);
        $this->assertStringContainsString('tel:', $chunk);
        $this->assertStringContainsString('/docs/documentation/chat#presence', $chunk);
        $this->assertStringContainsString('ChatPresenceFeatureTest', $chunk);
        $this->assertStringContainsString('ChatPresenceUxFeatureTest', $chunk);
        $this->assertStringContainsString('ChatHeaderSubtitleFeatureTest', $chunk);
        $this->assertStringContainsString('ChatHeaderSubtitleUxFeatureTest', $chunk);
        $this->assertStringContainsString('ChatDraftUxFeatureTest', $chunk);
        $this->assertStringContainsString('/doc#chat-draft-index', $chunk);
        $this->assertStringContainsString('/doc#chat-index', $chunk);
        $this->assertStringContainsString('/doc#chat-contacts-team-filter-index', $chunk);
        $this->assertStringContainsString('/doc#chat-header-subtitle-index', $chunk);
        $this->assertStringContainsString('только ученики', $chunk);

        $this->assertStringNotContainsString('ping требует', $chunk);
        $this->assertStringNotContainsString('красная точка в списке', $chunk);
        $this->assertStringNotContainsString('время last_seen справа', $chunk);
        $this->assertStringNotContainsString('карточка из списка диалогов', $chunk);
    }

    public function test_doc_index_announces_reverb_overlay_without_contradicting_live_ux(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="reverb-status-overlay-index"', $html);
        $start = strpos($html, 'id="reverb-status-overlay-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="journal-table-preloader-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('процесс', $chunk);
        $this->assertStringContainsString('сокет', $chunk);
        $this->assertStringContainsString('127.0.0.1:6008', $chunk);
        $this->assertStringContainsString('127.0.0.1:6009', $chunk);
        $this->assertStringContainsString('nginx.ssl.conf_reverb', $chunk);
        $this->assertStringContainsString('laravel-reverb', $chunk);
        $this->assertStringContainsString('reverb-prod.service', $chunk);
        $this->assertStringContainsString('connecting', $chunk);
        $this->assertStringContainsString('is-ok', $chunk);
        $this->assertStringContainsString('без</b> <code>messages.view</code>', $chunk);
        $this->assertStringContainsString('ChatReverbOverlayFeatureTest', $chunk);
        $this->assertStringContainsString('/docs/documentation/chat#reverb-overlay', $chunk);
        $this->assertStringContainsString('cabinet_diagnostics', $chunk);
        $this->assertStringContainsString('CabinetDiagnostics::shouldShow()', $chunk);
        $this->assertStringContainsString('#js-reverb-status', $chunk);
        $this->assertStringContainsString('settings.reverbOverlay.manage', $chunk);
        $this->assertStringContainsString('Gate::before', $chunk);
        $this->assertStringContainsString('JSON-оверлея диагностики на <code>/cabinet</code> нет', $chunk);
        $this->assertStringContainsString('/doc#cabinet-diagnostics-index', $chunk);
        $this->assertStringContainsString('SettingsCabinetDiagnosticsUiContractsFeatureTest', $chunk);
        $this->assertStringNotContainsString('messages.view</code> даёт оверлей', $chunk);
        $this->assertStringNotContainsString('Form Request — только superadmin', $chunk);
        $this->assertStringNotContainsString('PartnerContext::isSuperAdmin()', $chunk);
        $this->assertStringNotContainsString("wsPath: '/app'", str_replace(
            "<b>без</b> <code>wsPath: '/app'</code>",
            '',
            $chunk
        ));
    }

    public function test_doc_index_announces_contacts_team_filter_without_contradicting_live_ux(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="chat-contacts-team-filter-index"', $html);
        $start = strpos($html, 'id="chat-contacts-team-filter-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="users-client-naming-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('contactsTeamFilter', $chunk);
        $this->assertStringContainsString('contactsTeamError', $chunk);
        $this->assertStringContainsString('form-select', $chunk);
        $this->assertStringContainsString('не Select2', $chunk);
        $this->assertStringContainsString('не multi', $chunk);
        $this->assertStringContainsString('Все группы', $chunk);
        $this->assertStringContainsString('Без группы', $chunk);
        $this->assertStringContainsString('none', $chunk);
        $this->assertStringContainsString('team_id', $chunk);
        $this->assertStringContainsString('GET /chat/api/users', $chunk);
        $this->assertStringContainsString('ChatUsersIndexRequest', $chunk);
        $this->assertStringContainsString('messages.view', $chunk);
        $this->assertStringContainsString('team_user', $chunk);
        $this->assertStringContainsString('withSystemRoleUser', $chunk);
        $this->assertStringContainsString('filterByStudentTeam', $chunk);
        $this->assertStringContainsString('только ученики', $chunk);
        $this->assertStringContainsString('team_trainer', $chunk);
        $this->assertStringContainsString('не</b> входит', $chunk);
        $this->assertStringContainsString('errors.team_id', $chunk);
        $this->assertStringContainsString('Выберите группу из списка.', $chunk);
        $this->assertStringContainsString('errors.q', $chunk);
        $this->assertStringContainsString('405', $chunk);
        $this->assertStringContainsString('JSON-список 200', $chunk);
        $this->assertStringContainsString('стандартной ширины Bootstrap', $chunk);
        $this->assertStringContainsString('order_by', $chunk);
        $this->assertStringContainsString('250', $chunk);
        $this->assertStringContainsString('Ничего не найдено', $chunk);
        $this->assertStringContainsString('не</b> затирается', $chunk);
        $this->assertStringContainsString('не</b> групповые чаты', $chunk);
        $this->assertStringContainsString('не</b> фильтр списка диалогов', $chunk);
        $this->assertStringContainsString('/docs/documentation/chat', $chunk);
        $this->assertStringContainsString('student-team-membership', $chunk);
        $this->assertStringContainsString('ChatContactsTeamFilterFeatureTest', $chunk);
        $this->assertStringContainsString('ChatContactsTeamFilterUxFeatureTest', $chunk);
        $this->assertStringContainsString('chat-groups-index', $chunk);
        $this->assertStringContainsString('chat-team-groups-index', $chunk);

        $this->assertStringNotContainsString('их по-прежнему нет', $chunk);
        $this->assertStringNotContainsString('групповые чаты включены', $chunk);
        $this->assertStringNotContainsString('это фильтр списка диалогов', $chunk);
        $this->assertStringNotContainsString('Select2 в модалке контактов', $chunk);
        $this->assertStringNotContainsString('modal-xl', $chunk);
        $this->assertStringNotContainsString('team_trainer</code> входит', $chunk);
    }

    public function test_live_code_matches_documented_contacts_team_filter(): void
    {
        $root = dirname(__DIR__, 3);
        $js = (string) file_get_contents($root.'/resources/js/chat.js');
        $blade = (string) file_get_contents($root.'/resources/views/chat/index.blade.php');
        $service = (string) file_get_contents($root.'/app/Services/Chat/ChatService.php');
        $request = (string) file_get_contents($root.'/app/Http/Requests/Chat/ChatUsersIndexRequest.php');
        $page = (string) file_get_contents($root.'/app/Http/Controllers/Chat/ChatPageController.php');
        $api = (string) file_get_contents($root.'/app/Http/Controllers/Chat/ChatApiController.php');
        $membership = $this->docFile('student-team-membership.html');

        $this->assertStringContainsString('id="contactsTeamFilter"', $blade);
        $this->assertStringContainsString('class="form-select mb-2"', $blade);
        $this->assertStringContainsString('Все группы', $blade);
        $this->assertStringContainsString('value="none">Без группы', $blade);
        $this->assertStringContainsString('id="contactsTeamError"', $blade);
        $this->assertStringContainsString('data-error-for="team_id"', $blade);
        $this->assertStringContainsString('<div class="modal-dialog">', $blade);
        $this->assertStringNotContainsString('modal-xl', $blade);
        $this->assertStringNotContainsString('select2', strtolower($blade));
        $this->assertStringNotContainsString('multiple', $blade);

        $this->assertStringContainsString("params.set('team_id', teamId)", $js);
        $this->assertStringContainsString("getElementById('contactsTeamFilter').value = ''", $js);
        $this->assertStringContainsString('showContactsTeamError', $js);
        $this->assertStringContainsString("fieldError(res.data, 'team_id')", $js);
        $this->assertStringContainsString('}, 250)', $js);

        $this->assertStringContainsString('withSystemRoleUser()', $service);
        $this->assertStringContainsString('filterByStudentTeam($partnerId, $teamFilter)', $service);

        $this->assertStringContainsString("'team_id' => 'группа'", $request);
        $this->assertStringContainsString('Выберите группу из списка.', $request);

        $this->assertStringContainsString("->orderBy('order_by')", $page);
        $this->assertStringContainsString("->orderBy('title')", $page);
        $this->assertStringContainsString('contactTeams', $page);
        $this->assertStringContainsString('chatUnreadCount', $page);
        $this->assertStringContainsString('unreadTotal', $page);
        $this->assertStringContainsString('unreadPrivateTotal', $page);
        $this->assertStringContainsString('unreadGroupTotal', $page);
        $this->assertStringContainsString('chatPrivateUnreadCount', $page);
        $this->assertStringContainsString('chatGroupUnreadCount', $page);

        $this->assertStringContainsString('return response()->json($users);', $api);

        $this->assertStringContainsString('?team_id=', $membership);
        $this->assertStringContainsString('chat-contacts-team-filter-index', $membership);
        $this->assertStringContainsString('не групповые чаты', $membership);
        $this->assertStringContainsString('chat-groups-index', $membership);
        $this->assertStringContainsString('chat-team-groups-index', $membership);
    }

    public function test_doc_index_announces_chat_groups_without_contradicting_live_ux(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="chat-groups-index"', $html);
        $start = strpos($html, 'id="chat-groups-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="cabinet-attach-team-in-app-body-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('openCreateGroupBtn', $chunk);
        $this->assertStringContainsString('openCreateGroupMobileBtn', $chunk);
        $this->assertStringContainsString('js-open-create-group', $chunk);
        $this->assertStringContainsString('d-none d-lg-inline-block', $chunk);
        $this->assertStringContainsString('createGroupNameModal', $chunk);
        $this->assertStringContainsString('createGroupMembersModal', $chunk);
        $this->assertStringContainsString('createGroupMembersForm', $chunk);
        $this->assertStringContainsString('100dvh', $chunk);
        $this->assertStringContainsString('StoreChatGroupThreadRequest', $chunk);
        $this->assertStringContainsString('errors.title', $chunk);
        $this->assertStringContainsString('errors.user_ids', $chunk);
        $this->assertStringContainsString('threads.is_group', $chunk);
        $this->assertStringContainsString('peer_id: null', $chunk);
        $this->assertStringContainsString('#f3a12b', $chunk);
        $this->assertStringContainsString('inbox.bump', $chunk);
        $this->assertStringContainsString('не «Диалог»', $chunk);
        $this->assertStringContainsString('не ФИО первого участника', $chunk);
        $this->assertStringContainsString('startDialog', $chunk);
        $this->assertStringContainsString('фильтрует 1-на-1 только если нет', $chunk);
        $this->assertStringContainsString('chat-private-thread-identity-index', $chunk);
        $this->assertStringContainsString('setHeaderPeerClickable', $chunk);
        $this->assertStringContainsString('groupCardModal', $chunk);
        $this->assertStringContainsString('#threadSubtitle', $chunk);
        $this->assertStringContainsString('members_total', $chunk);
        $this->assertStringContainsString('N участник', $chunk);
        $this->assertStringContainsString('can_manage', $chunk);
        $this->assertStringContainsString('ChatGroupMembersFeatureTest', $chunk);
        $this->assertStringContainsString('Не в этом срезе', $chunk);
        $this->assertStringContainsString('max 100', $chunk);
        $this->assertStringContainsString('ChatGroupThreadFeatureTest', $chunk);
        $this->assertStringContainsString('ChatGroupThreadUxFeatureTest', $chunk);
        $this->assertStringContainsString('ChatHeaderSubtitleFeatureTest', $chunk);
        $this->assertStringContainsString('ChatHeaderSubtitleUxFeatureTest', $chunk);
        $this->assertStringContainsString('ChatMobileContactsAlignFeatureTest', $chunk);
        $this->assertStringContainsString('ChatMobileContactsAlignUxFeatureTest', $chunk);
        $this->assertStringContainsString('ChatNonAjaxSafetyNetFeatureTest', $chunk);
        $this->assertStringContainsString('chat-group-list-title-index', $chunk);
        $this->assertStringContainsString('chat-group-members-index', $chunk);
        $this->assertStringContainsString('chat-header-subtitle-index', $chunk);
        $this->assertStringContainsString('/docs/documentation/chat#groups', $chunk);
        $this->assertStringContainsString('не <code>modal-xl</code>', $chunk);
        $this->assertStringContainsString('threads.team_id', $chunk);
        $this->assertStringNotContainsString('их по-прежнему нет', $chunk);
        $this->assertStringNotContainsString('modal-fullscreen', $chunk);
        $this->assertStringNotContainsString('0 участников — soft-delete треда.', $chunk);
    }

    public function test_doc_index_announces_team_group_chats(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="chat-team-groups-index"', $html);
        $start = strpos($html, 'id="chat-team-groups-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="chat-support-identity-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('threads.team_id', $chunk);
        $this->assertStringContainsString('TeamService::store', $chunk);
        $this->assertStringContainsString('TeamUserSyncService', $chunk);
        $this->assertStringContainsString('is_enabled=0', $chunk);
        $this->assertStringContainsString('teams.is_enabled=0', $chunk);
        $this->assertStringContainsString('superadmin', $chunk);
        $this->assertStringContainsString('TeamGroupChatService', $chunk);
        $this->assertStringContainsString('TeamGroupChatUserObserver', $chunk);
        $this->assertStringContainsString('ChatTeamGroupThreadFeatureTest', $chunk);
        $this->assertStringContainsString('ChatTeamGroupThreadFullAccessFeatureTest', $chunk);
        $this->assertStringContainsString('ChatTeamGroupThreadNonAjaxSafetyNetFeatureTest', $chunk);
        $this->assertStringContainsString('ChatTeamGroupThreadAjaxContractFeatureTest', $chunk);
        $this->assertStringContainsString('ChatTeamGroupThreadUxFeatureTest', $chunk);
        $this->assertStringContainsString('/docs/documentation/chat#team-groups', $chunk);
        $this->assertStringContainsString('<b>не</b> выгоняет', $chunk);
        $this->assertStringContainsString('независимы', $chunk);
        $this->assertStringContainsString('chat-group-members-index', $chunk);
        $this->assertStringContainsString('chat-private-thread-identity-index', $chunk);
        $this->assertStringContainsString('chat-contacts-team-filter-index', $chunk);
        $this->assertStringContainsString('X-Requested-With', $chunk);
        $this->assertStringContainsString('errors.title', $chunk);
        $this->assertStringContainsString('peer_id: null', $chunk);
        $this->assertStringContainsString('is_group', $chunk);
        $this->assertStringContainsString('Team::create', $chunk);
        $this->assertStringContainsString('кастомн', $chunk);
        $this->assertStringContainsString('POST /cabinet/teams/attach', $chunk);
        $this->assertStringContainsString('sync-teams', $chunk);
        $this->assertStringContainsString('afterCommit', $chunk);
        $this->assertStringContainsString('JSON 200', $chunk);
        $this->assertStringNotContainsString('PATCH без X-Requested-With → 302', $chunk);
        $this->assertStringNotContainsString('нативный PATCH группы → 302', $chunk);
    }

    public function test_doc_index_announces_chat_support_identity(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="chat-support-identity-index"', $html);
        $start = strpos($html, 'id="chat-support-identity-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="cabinet-attach-team-in-app-body-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('Служба поддержки', $chunk);
        $this->assertStringContainsString('ChatSupportIdentity', $chunk);
        $this->assertStringContainsString('LIMIT 1', $chunk);
        $this->assertStringContainsString('без <code>OR</code>', $chunk);
        $this->assertStringContainsString('partner_id = null', $chunk);
        $this->assertStringContainsString('canonicalUserId', $chunk);
        $this->assertStringContainsString('errors.user_ids', $chunk);
        $this->assertStringContainsString('role_label || role_name', $chunk);
        $this->assertStringContainsString('Нельзя создать диалог с самим собой.', $chunk);
        $this->assertStringContainsString('exclude_thread_id', $chunk);
        $this->assertStringContainsString('Без группы', $chunk);
        $this->assertStringContainsString('Аккаунт', $chunk);
        $this->assertStringContainsString('не сырой JSON', $chunk);
        $this->assertStringContainsString('messages.view', $chunk);
        $this->assertStringContainsString('ChatSupportIdentityFeatureTest', $chunk);
        $this->assertStringContainsString('ChatSupportIdentityFullAccessFeatureTest', $chunk);
        $this->assertStringContainsString('ChatSupportIdentityNonAjaxSafetyNetFeatureTest', $chunk);
        $this->assertStringContainsString('ChatSupportIdentityAjaxContractFeatureTest', $chunk);
        $this->assertStringContainsString('ChatSupportIdentityUxFeatureTest', $chunk);
        $this->assertStringContainsString('/docs/documentation/chat#support-identity', $chunk);
        $this->assertStringContainsString('Канонический', $chunk);
        $this->assertStringNotContainsString('переименовывается учётка', $chunk);
        $this->assertStringNotContainsString('все суперадмины через OR', $chunk);
        $this->assertStringNotContainsString('в чужих глазах', $chunk);
    }

    public function test_doc_index_announces_chat_thread_delete(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="chat-thread-delete-index"', $html);
        $start = strpos($html, 'id="chat-thread-delete-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="chat-mobile-inbox-split-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('messages.threads.delete', $chunk);
        $this->assertStringContainsString('is_visible=0', $chunk);
        $this->assertStringContainsString('Gate::before', $chunk);
        $this->assertStringContainsString('#deleteThreadBtn', $chunk);
        $this->assertStringContainsString('fa-trash', $chunk);
        $this->assertStringContainsString('#confirmDeleteModal', $chunk);
        $this->assertStringContainsString('errors.thread', $chunk);
        $this->assertStringContainsString('DELETE /chat/api/threads/{thread}', $chunk);
        $this->assertStringContainsString('Нельзя удалить чат учебной группы.', $chunk);
        $this->assertStringContainsString('DestroyChatThreadRequest', $chunk);
        $this->assertStringContainsString('ChatService::deleteThread', $chunk);
        $this->assertStringContainsString('ChatThreadDeleteFeatureTest', $chunk);
        $this->assertStringContainsString('ChatThreadDeleteFullAccessFeatureTest', $chunk);
        $this->assertStringContainsString('ChatThreadDeleteNonAjaxSafetyNetFeatureTest', $chunk);
        $this->assertStringContainsString('ChatThreadDeleteAjaxContractFeatureTest', $chunk);
        $this->assertStringContainsString('ChatThreadDeleteUxFeatureTest', $chunk);
        $this->assertStringContainsString('removed: true', $chunk);
        $this->assertStringContainsString('PUT/POST/PATCH 405', $chunk);
        $this->assertStringContainsString('GET того же URL открывает диалог', $chunk);
        $this->assertStringNotContainsString('Hard-delete из UI', $chunk);
    }

    public function test_doc_index_announces_chat_private_thread_identity(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="chat-private-thread-identity-index"', $html);
        $start = strpos($html, 'id="chat-private-thread-identity-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="chat-group-members-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('participants_thread_user_unique', $chunk);
        $this->assertStringContainsString('UNIQUE (thread_id, user_id)', $chunk);
        $this->assertStringContainsString('2026_08_19_160000_unique_participants_thread_user', $chunk);
        $this->assertStringContainsString('findPrivateThread', $chunk);
        $this->assertStringContainsString('Не <code>has(participants, 2)</code>', $chunk);
        $this->assertStringContainsString('threads.is_group=0', $chunk);
        $this->assertStringContainsString('restoreParticipantIfTrashed', $chunk);
        $this->assertStringContainsString('restoreOrCreateParticipant', $chunk);
        $this->assertStringContainsString('updateLiveParticipant', $chunk);
        $this->assertStringContainsString('peer_id: null', $chunk);
        $this->assertStringContainsString('«Диалог»', $chunk);
        $this->assertStringContainsString('startDialog', $chunk);
        $this->assertStringContainsString('!is_group && peer_id', $chunk);
        $this->assertStringContainsString('POST /chat/api/threads', $chunk);
        $this->assertStringContainsString('AJAX: 201 создан / 200 тот же тред', $chunk);
        $this->assertStringContainsString('errors.user_id', $chunk);
        $this->assertStringContainsString('threads.team_id', $chunk);
        $this->assertStringContainsString('is_group=1', $chunk);
        $this->assertStringContainsString('403, не 500 unique', $chunk);
        $this->assertStringContainsString('ChatPrivateThreadIdentityFeatureTest', $chunk);
        $this->assertStringContainsString('ChatPrivateThreadIdentityUxFeatureTest', $chunk);
        $this->assertStringContainsString('/docs/documentation/chat#private-thread-identity', $chunk);
        $this->assertStringContainsString('Не в этом срезе', $chunk);
        $this->assertStringContainsString('само личку не возвращает', $chunk);
        $this->assertStringContainsString('группы по составу', $chunk);
        $this->assertStringNotContainsString('при включении сами возвращаем личку', $chunk);
        $this->assertStringNotContainsString("has(participants, '=', 2)", $chunk);
    }

    public function test_doc_index_announces_group_list_title_without_contradicting_live_ux(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="chat-group-list-title-index"', $html);
        $start = strpos($html, 'id="chat-group-list-title-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="chat-mobile-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('threads.subject', $chunk);
        $this->assertStringContainsString('не «Диалог»', $chunk);
        $this->assertStringContainsString('не ФИО первого участника', $chunk);
        $this->assertStringContainsString('Пустое имя группы — «Группа»', $chunk);
        $this->assertStringContainsString('Пустой личный диалог — «Диалог»', $chunk);
        $this->assertStringContainsString('threadListTitle', $chunk);
        $this->assertStringContainsString('titleForViewer', $chunk);
        $this->assertStringContainsString('text-align: left', $chunk);
        $this->assertStringContainsString('.chat-li-title', $chunk);
        $this->assertStringContainsString('.chat-li-middle', $chunk);
        $this->assertStringContainsString('.chat-header-text', $chunk);
        $this->assertStringContainsString('.chat-header-subtitle', $chunk);
        $this->assertStringContainsString('@media (max-width: 991.98px)', $chunk);
        $this->assertStringContainsString('body.sidebar-mini', $chunk);
        $this->assertStringContainsString('тремя и более', $chunk);
        $this->assertStringContainsString('upsertThread', $chunk);
        $this->assertStringContainsString('startDialog', $chunk);
        $this->assertStringContainsString('!is_group && peer_id', $chunk);
        $this->assertStringContainsString('chat-private-thread-identity-index', $chunk);
        $this->assertStringContainsString('chat-groups-index', $chunk);
        $this->assertStringContainsString('chat-group-members-index', $chunk);
        $this->assertStringContainsString('ChatGroupThreadFeatureTest', $chunk);
        $this->assertStringContainsString('ChatGroupThreadUxFeatureTest', $chunk);
        $this->assertStringContainsString('/docs/documentation/chat#groups', $chunk);
        $this->assertStringNotContainsString('их по-прежнему нет', $chunk);
        $this->assertStringNotContainsString('список 1-на-1', $chunk);
        $this->assertStringNotContainsString('пустое имя группы — «Диалог»', $chunk);
    }

    public function test_doc_index_announces_chat_group_members_without_contradicting_live_ux(): void
    {
        $html = $this->docFile('index.html');

        $this->assertStringContainsString('id="chat-group-members-index"', $html);
        $start = strpos($html, 'id="chat-group-members-index"');
        $this->assertNotFalse($start);
        $end = strpos($html, 'id="chat-group-list-title-index"');
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);
        $chunk = substr($html, $start, $end - $start);

        $this->assertStringContainsString('groupCardModal', $chunk);
        $this->assertStringContainsString('addGroupMembersBtn', $chunk);
        $this->assertStringContainsString('leaveGroupBtn', $chunk);
        $this->assertStringContainsString('can_manage', $chunk);
        $this->assertStringContainsString('admin', $chunk);
        $this->assertStringContainsString('superadmin', $chunk);
        $this->assertStringContainsString('is-hidden', $chunk);
        $this->assertStringContainsString('fa-user-plus', $chunk);
        $this->assertStringContainsString('fa-right-from-bracket', $chunk);
        $this->assertStringContainsString('role_label', $chunk);
        $this->assertStringContainsString('MEMBERS_PAGE_SIZE', $chunk);
        $this->assertStringContainsString('after_user_id', $chunk);
        $this->assertStringContainsString('maybeFillGroupMembers', $chunk);
        $this->assertStringContainsString('maybeLoadMoreMembers', $chunk);
        $this->assertStringContainsString('openPeerCard', $chunk);
        $this->assertStringContainsString('group-member-remove', $chunk);
        $this->assertStringContainsString('opacity: 0', $chunk);
        $this->assertStringContainsString('991.98px', $chunk);
        $this->assertStringContainsString('showConfirmDeleteModal', $chunk);
        $this->assertStringContainsString('confirmDeleteModal', $chunk);
        $this->assertStringContainsString('window.showToast', $chunk);
        $this->assertStringContainsString('Участник удалён.', $chunk);
        $this->assertStringContainsString('Вы покинули группу.', $chunk);
        $this->assertStringContainsString('Участники добавлены.', $chunk);
        $this->assertStringContainsString('removed: true', $chunk);
        $this->assertStringContainsString('applyInboxBump', $chunk);
        $this->assertStringContainsString('threads.team_id', $chunk);
        $this->assertStringContainsString('exclude_thread_id', $chunk);
        $this->assertStringContainsString('StoreChatGroupParticipantsRequest', $chunk);
        $this->assertStringContainsString('showModalQueued', $chunk);
        $this->assertStringContainsString('addGroupMembersModal', $chunk);
        $this->assertStringContainsString('#threadSubtitle', $chunk);
        $this->assertStringContainsString('errors.user_ids', $chunk);
        $this->assertStringContainsString('errors.user', $chunk);
        $this->assertStringContainsString('ChatGroupMembersFeatureTest', $chunk);
        $this->assertStringContainsString('ChatGroupMembersUxFeatureTest', $chunk);
        $this->assertStringContainsString('ChatHeaderSubtitleFeatureTest', $chunk);
        $this->assertStringContainsString('ChatHeaderSubtitleUxFeatureTest', $chunk);
        $this->assertStringContainsString('/doc#chat-header-subtitle-index', $chunk);
        $this->assertStringContainsString('/docs/documentation/chat#groups', $chunk);
        $this->assertStringContainsString('Не в этом срезе', $chunk);
        $this->assertStringContainsString('не <code>modal-xl</code>', $chunk);
        $this->assertStringNotContainsString('modal-fullscreen', $chunk);
        $this->assertStringNotContainsString('0 участников — soft-delete треда.', $chunk);
        $this->assertStringNotContainsString('DataTables', str_replace('не DataTables', '', $chunk));
    }

    public function test_live_code_matches_documented_group_threads(): void
    {
        $root = dirname(__DIR__, 3);
        $js = (string) file_get_contents($root.'/resources/js/chat.js');
        $blade = (string) file_get_contents($root.'/resources/views/chat/index.blade.php');
        $request = (string) file_get_contents($root.'/app/Http/Requests/Chat/StoreChatGroupThreadRequest.php');
        $service = (string) file_get_contents($root.'/app/Services/Chat/ChatService.php');
        $css = (string) file_get_contents($root.'/resources/css/chat.css');
        $docs = $this->docFile('chat.html');

        $this->assertStringContainsString('data-store-group-url', $blade);
        $this->assertStringContainsString('id="openCreateGroupBtn"', $blade);
        $this->assertStringContainsString('id="openCreateGroupMobileBtn"', $blade);
        $this->assertStringContainsString('id="groupThreads"', $blade);
        $this->assertStringNotContainsString('chat-groups-stub', $blade);
        $this->assertStringContainsString('js-open-create-group', $blade);
        $this->assertStringContainsString('id="createGroupNameModal"', $blade);
        $this->assertStringContainsString('id="createGroupMembersModal"', $blade);
        $this->assertStringContainsString('data-error-for="title"', $blade);
        $this->assertStringContainsString('data-error-for="user_ids"', $blade);
        $this->assertStringNotContainsString('modal-xl', $blade);

        $this->assertStringContainsString("querySelectorAll('.js-open-create-group')", $js);
        $this->assertStringContainsString('function openCreateGroupWizard(', $js);
        $this->assertStringContainsString('resetCreateGroupWizard()', $js);
        $this->assertStringContainsString('if (patch.peer_id && !patch.is_group) {', $js);
        $this->assertStringContainsString('function threadListTitle(', $js);
        $this->assertStringContainsString("return t && t.is_group ? 'Группа' : 'Диалог';", $js);
        $this->assertStringContainsString('return !t.is_group && Number(t.peer_id) === Number(userId);', $js);
        $this->assertStringContainsString('chat-group-list-title-index', $docs);
        $this->assertStringContainsString('Поиск по заголовку', $docs);
        $this->assertStringContainsString('не ФИО первого участника', $docs);
        $this->assertStringContainsString('startDialog', $docs);
        $this->assertStringContainsString('@media (max-width: 991.98px)', $docs);
        $this->assertStringContainsString('is_group: e.is_group', $js);
        $this->assertStringContainsString('setHeaderPeerClickable(!!currentPeerId || currentIsGroup)', $js);
        $this->assertStringContainsString('function openGroupCard(', $js);
        $this->assertStringContainsString('function headerPeerActivate(', $js);
        $this->assertStringContainsString('id="groupCardModal"', $blade);
        $this->assertStringContainsString('id="threadSubtitle"', $blade);
        $this->assertStringContainsString('function setThreadSubtitle(', $js);
        $this->assertStringContainsString('id="addGroupMembersModal"', $blade);
        $this->assertStringContainsString('MEMBERS_PAGE_SIZE', $service);
        $this->assertStringContainsString('groupCardModal', $docs);
        $this->assertStringContainsString('chat-group-members-index', $docs);
        $this->assertStringContainsString('ChatGroupMembersFeatureTest', $docs);
        $this->assertStringContainsString('after_user_id', $docs);
        $this->assertStringContainsString('showConfirmDeleteModal', $docs);
        $this->assertStringContainsString('group-member-remove', $docs);
        $this->assertStringContainsString('showModalQueued', $docs);
        $this->assertStringContainsString('function confirmRemoveGroupMember(', $js);
        $this->assertStringContainsString('function confirmLeaveGroup(', $js);
        $this->assertStringContainsString("if (e.removed)", $js);
        $this->assertStringContainsString('.group-member-remove', $css);
        $this->assertStringContainsString('opacity: 0', $css);

        $this->assertStringContainsString("'title' => ['required', 'string', 'min:1', 'max:100']", $request);
        $this->assertStringContainsString("'user_ids' => ['required', 'array', 'min:2', 'max:100']", $request);
        $this->assertStringContainsString("'distinct'", $request);
        $this->assertStringContainsString('Введите название группы.', $request);
        $this->assertStringContainsString('Список участников содержит повторы.', $request);

        $this->assertStringContainsString('createGroupThread', $service);
        $this->assertStringContainsString("'is_group' => true", $service);
        $this->assertStringContainsString('return $count > 2;', $service);
        $this->assertStringNotContainsString("->has('participants', '=', 2)", $service);
        $this->assertStringContainsString("where('threads.is_group', false)", $service);
        $this->assertStringContainsString('restoreParticipantIfTrashed', $service);
        $this->assertStringContainsString('participants_thread_user_unique', $docs);
        $this->assertStringContainsString('группа с двумя живыми не подменяется личкой', $docs);
        $this->assertStringContainsString('ChatPrivateThreadIdentityFeatureTest', $docs);
        $this->assertStringContainsString('ChatPrivateThreadIdentityUxFeatureTest', $docs);

        $this->assertStringContainsString('background: #f3a12b', $css);
        $this->assertStringContainsString('.group-pick-check', $css);
        $this->assertStringContainsString('#createGroupMembersModal .modal-dialog', $css);
        $this->assertStringContainsString('#createGroupMembersModal #createGroupMembersForm', $css);
        $this->assertStringContainsString('max-height: calc(100dvh - 1rem)', $css);
        $this->assertStringContainsString('max-height: calc(100dvh - 16rem)', $css);
        $this->assertStringContainsString('100dvh', $docs);
        $this->assertStringContainsString('createGroupMembersForm', $docs);

        $this->assertStringContainsString('id="groups"', $docs);
        $this->assertStringContainsString('ChatGroupThreadFeatureTest', $docs);
        $this->assertStringContainsString('ChatGroupThreadUxFeatureTest', $docs);
        $this->assertStringContainsString('ChatHeaderSubtitleFeatureTest', $docs);
        $this->assertStringContainsString('ChatHeaderSubtitleUxFeatureTest', $docs);
        $this->assertStringContainsString('ChatMobileContactsAlignFeatureTest', $docs);
        $this->assertStringContainsString('ChatMobileContactsAlignUxFeatureTest', $docs);
        $this->assertStringContainsString('тремя и более', $docs);
        $this->assertStringContainsString('не «Диалог»', $docs);
        $this->assertStringContainsString('threadListTitle', $docs);
        $this->assertStringContainsString('setHeaderPeerClickable', $docs);
        $this->assertStringContainsString('Не в этом срезе', $docs);
        $this->assertStringContainsString('ChatNonAjaxSafetyNetFeatureTest', $docs);
    }

    public function test_live_code_matches_documented_team_group_chats(): void
    {
        $root = dirname(__DIR__, 3);
        $docs = $this->docFile('chat.html');
        $service = (string) file_get_contents($root.'/app/Services/Chat/TeamGroupChatService.php');
        $chat = (string) file_get_contents($root.'/app/Services/Chat/ChatService.php');
        $observer = (string) file_get_contents($root.'/app/Observers/TeamGroupChatUserObserver.php');
        $teamService = (string) file_get_contents($root.'/app/Services/TeamService.php');
        $teamUser = (string) file_get_contents($root.'/app/Services/TeamUserSyncService.php');
        $teamTrainer = (string) file_get_contents($root.'/app/Services/TeamTrainerSyncService.php');
        $thread = (string) file_get_contents($root.'/app/Models/ChatThread.php');
        $provider = (string) file_get_contents($root.'/app/Providers/AppServiceProvider.php');

        $this->assertStringContainsString('id="team-groups"', $docs);
        $this->assertStringContainsString('threads.team_id', $docs);
        $this->assertStringContainsString('TeamGroupChatService', $docs);
        $this->assertStringContainsString('teams.is_enabled=0', $docs);
        $this->assertStringContainsString('Team::create', $docs);
        $this->assertStringContainsString('кастомн', $docs);
        $this->assertStringContainsString('X-Requested-With', $docs);
        $this->assertStringContainsString('peer_id: null', $docs);
        $this->assertStringContainsString('afterCommit', $docs);
        $this->assertStringContainsString('ChatTeamGroupThreadFeatureTest', $docs);
        $this->assertStringContainsString('ChatTeamGroupThreadFullAccessFeatureTest', $docs);
        $this->assertStringContainsString('ChatTeamGroupThreadNonAjaxSafetyNetFeatureTest', $docs);
        $this->assertStringContainsString('ChatTeamGroupThreadAjaxContractFeatureTest', $docs);
        $this->assertStringContainsString('ChatTeamGroupThreadUxFeatureTest', $docs);
        $this->assertStringContainsString('/doc#chat-team-groups-index', $docs);
        $this->assertStringContainsString('id="support-identity"', $docs);
        $this->assertStringContainsString('Служба поддержки', $docs);
        $this->assertStringContainsString('ChatSupportIdentity', $docs);
        $this->assertStringContainsString('ChatSupportIdentityFeatureTest', $docs);
        $this->assertStringContainsString('ChatSupportIdentityFullAccessFeatureTest', $docs);
        $this->assertStringContainsString('ChatSupportIdentityNonAjaxSafetyNetFeatureTest', $docs);
        $this->assertStringContainsString('ChatSupportIdentityAjaxContractFeatureTest', $docs);
        $this->assertStringContainsString('ChatSupportIdentityUxFeatureTest', $docs);

        $this->assertStringContainsString("'team_id'", $thread);
        $this->assertStringContainsString('ensureThreadForTeam', $service);
        $this->assertStringContainsString('addUserToTeamChat', $service);
        $this->assertStringContainsString('syncUserAfterSave', $service);
        $this->assertStringContainsString('canonicalUserId', $service);
        $this->assertStringContainsString('ensureCanonicalSupportInTeamChats', $service);
        $this->assertStringContainsString('ensureThreadForTeam($team)', $teamService);
        $this->assertStringContainsString('addStudentToTeamChats', $teamUser);
        $this->assertStringContainsString('addTrainerToTeamChat', $teamTrainer);
        $this->assertStringContainsString('TeamGroupChatUserObserver', $observer);
        $this->assertStringContainsString('User::observe(TeamGroupChatUserObserver::class)', $provider);
        $this->assertStringContainsString('removeUserFromAllThreads', $chat);
        $this->assertStringContainsString('$isTeamChat', $chat);
        $this->assertStringContainsString('function deleteThread', $chat);
        $this->assertStringContainsString('ChatThreadDeleteFeatureTest', $docs);
        $this->assertStringContainsString('chat.api.threads.destroy', $docs);
    }

    public function test_live_code_uses_vite_page_only_chat_assets(): void
    {
        $root = dirname(__DIR__, 3);
        $blade = (string) file_get_contents($root.'/resources/views/chat/index.blade.php');
        $css = (string) file_get_contents($root.'/resources/css/chat.css');
        $vite = (string) file_get_contents($root.'/vite.config.js');
        $layout = (string) file_get_contents($root.'/resources/views/layouts/admin2.blade.php');
        $docs = $this->docFile('chat.html');

        $this->assertFileExists($root.'/resources/js/chat.js');
        $this->assertFileDoesNotExist($root.'/public/js/chat.js');

        $this->assertStringContainsString("@vite(['resources/css/chat.css'])", $blade);
        $this->assertStringContainsString("@vite(['resources/js/chat.js'])", $blade);
        $this->assertStringNotContainsString("asset('js/chat.js')", $blade);
        $this->assertDoesNotMatchRegularExpression('/<style[\s>]/i', $blade);

        $this->assertStringContainsString("'resources/css/chat.css'", $vite);
        $this->assertStringContainsString("'resources/js/chat.js'", $vite);

        $this->assertStringNotContainsString('resources/css/chat.css', $layout);
        $this->assertStringNotContainsString('resources/js/chat.js', $layout);

        $this->assertStringContainsString('max-width: 991.98px', $css);
        $this->assertStringContainsString('body.chat-immersive .main-header', $css);
        $this->assertStringContainsString('max-height: min(60vh, 520px)', $css);
        $this->assertStringContainsString('max-height: calc(100dvh - 1rem)', $css);
        $this->assertStringContainsString('max-height: calc(100dvh - 16rem)', $css);
        $this->assertStringContainsString('#createGroupMembersModal #createGroupMembersForm', $css);
        $this->assertStringContainsString('#createGroupMembersModal .modal-footer', $css);
        $this->assertStringContainsString('ChatMobileContactsAlignFeatureTest', $docs);
        $this->assertStringContainsString('ChatMobileContactsAlignUxFeatureTest', $docs);

        $this->assertStringContainsString('resources/css/chat.css', $docs);
        $this->assertStringContainsString('resources/js/chat.js', $docs);
        $this->assertStringContainsString('@vite', $docs);
        $this->assertStringNotContainsString('public/js/chat.js', $docs);
        $this->assertStringNotContainsString('без Vite / <code>npm run build</code>', $docs);
    }

    public function test_live_code_matches_documented_mobile_inbox_split(): void
    {
        $root = dirname(__DIR__, 3);
        $docs = $this->docFile('chat.html');
        $index = $this->docFile('index.html');
        $js = (string) file_get_contents($root.'/resources/js/chat.js');
        $blade = (string) file_get_contents($root.'/resources/views/chat/index.blade.php');
        $css = (string) file_get_contents($root.'/resources/css/chat.css');
        $echo = (string) file_get_contents($root.'/resources/views/includes/chat/echo.blade.php');
        $page = (string) file_get_contents($root.'/app/Http/Controllers/Chat/ChatPageController.php');
        $api = (string) file_get_contents($root.'/app/Http/Controllers/Chat/ChatApiController.php');
        $service = (string) file_get_contents($root.'/app/Services/Chat/ChatService.php');

        $this->assertStringContainsString('id="mobile-inbox-split"', $docs);
        $this->assertStringContainsString('/doc#chat-mobile-inbox-split-index', $docs);
        $this->assertStringContainsString('paintSplitNavBadges', $docs);
        $this->assertStringContainsString('unreadPrivateTotal', $docs);
        $this->assertStringContainsString('без <code>unread_private</code>', $docs);
        $this->assertStringContainsString('id="chat-mobile-inbox-split-index"', $index);
        $this->assertStringContainsString('/docs/documentation/chat#mobile-inbox-split', $index);
        $this->assertStringContainsString('общий</b> список', $index);

        $this->assertStringContainsString('unreadPrivateTotal', $page);
        $this->assertStringContainsString('unreadGroupTotal', $page);
        $this->assertStringContainsString('chatPrivateUnreadCount', $page);
        $this->assertStringContainsString('chatGroupUnreadCount', $page);
        $this->assertStringContainsString("function unreadPrivateTotal(", $service);
        $this->assertStringContainsString("function unreadGroupTotal(", $service);

        $this->assertStringContainsString("'threads' => \$this->chat->threadsForUser(\$userId)", $api);
        $this->assertStringContainsString("'unread_total' => \$this->chat->unreadTotal(\$userId)", $api);
        $this->assertStringNotContainsString('unread_private', $api);
        $this->assertStringNotContainsString('unread_group', $api);

        $this->assertStringContainsString('id="groupThreads"', $blade);
        $this->assertStringNotContainsString('chat-groups-stub', $blade);
        $this->assertStringContainsString('js-chat-private-unread-count', $blade);
        $this->assertStringContainsString('js-chat-group-unread-count', $blade);
        $navStart = strpos($blade, 'id="chatMobileNav"');
        $this->assertNotFalse($navStart);
        $nav = substr($blade, $navStart, 2200);
        $this->assertStringNotContainsString('js-chat-unread-count', $nav);

        $groupsStart = strpos($blade, 'id="chatPaneGroups"');
        $this->assertNotFalse($groupsStart);
        $groupsPane = substr($blade, $groupsStart, strpos($blade, 'id="chatPaneAccount"') - $groupsStart);
        $this->assertStringNotContainsString('id="threadSearch"', $groupsPane);
        $this->assertStringContainsString('id="openCreateGroupMobileBtn"', $groupsPane);

        $this->assertStringContainsString('function paintSplitNavBadges(', $js);
        $this->assertStringContainsString("filter(function (t) { return !t.is_group; })", $js);
        $this->assertStringContainsString("filter(function (t) { return !!t.is_group; })", $js);
        $this->assertStringContainsString("'Групп нет'", $js);
        $this->assertStringContainsString("currentIsGroup ? 'groups' : 'messages'", $js);
        $this->assertStringContainsString('renderThreads(applyThreadFilter(threadsCache))', $js);
        $this->assertStringContainsString("querySelectorAll('.js-open-create-group')", $js);

        $this->assertStringContainsString("querySelectorAll('.js-chat-unread-count')", $echo);
        $this->assertStringNotContainsString('js-chat-private-unread-count', $echo);
        $this->assertStringNotContainsString('js-chat-group-unread-count', $echo);

        $this->assertStringContainsString(
            '#chatApp[data-mobile-tab="groups"].is-dialog-open .chat-desktop-row { display: flex; }',
            $css
        );
    }

    public function test_live_code_matches_documented_private_thread_identity(): void
    {
        $root = dirname(__DIR__, 3);
        $docs = $this->docFile('chat.html');
        $index = $this->docFile('index.html');
        $service = (string) file_get_contents($root.'/app/Services/Chat/ChatService.php');
        $js = (string) file_get_contents($root.'/resources/js/chat.js');
        $blade = (string) file_get_contents($root.'/resources/views/chat/index.blade.php');
        $migration = (string) file_get_contents(
            $root.'/database/migrations/2026_08_19_160000_unique_participants_thread_user.php'
        );

        $this->assertStringContainsString('id="private-thread-identity"', $docs);
        $this->assertStringContainsString('/doc#chat-private-thread-identity-index', $docs);
        $this->assertStringContainsString('participants_thread_user_unique', $docs);
        $this->assertStringContainsString('findPrivateThread', $docs);
        $this->assertStringContainsString('restoreParticipantIfTrashed', $docs);
        $this->assertStringContainsString('updateLiveParticipant', $docs);
        $this->assertStringContainsString('whereNotExists', $docs);
        $this->assertStringContainsString('id="chat-private-thread-identity-index"', $index);

        $this->assertStringContainsString('function findPrivateThread(', $service);
        $this->assertStringContainsString("join('participants as chat_p_me'", $service);
        $this->assertStringContainsString("join('participants as chat_p_peer'", $service);
        $this->assertStringContainsString('whereNotExists', $service);
        $this->assertStringContainsString("where('threads.is_group', false)", $service);
        $this->assertStringContainsString('function restoreParticipantIfTrashed(', $service);
        $this->assertStringContainsString('function updateLiveParticipant(', $service);
        $this->assertStringContainsString('restoreParticipantIfTrashed((int) $existing->id, (int) $actor->id)', $service);

        $this->assertStringContainsString('participants_thread_user_unique', $migration);
        $this->assertStringContainsString("['thread_id', 'user_id']", $migration);

        $this->assertStringContainsString('data-store-thread-url', $blade);
        $this->assertStringContainsString("return !t.is_group && Number(t.peer_id) === Number(userId);", $js);
    }

    public function test_live_code_matches_documented_header_subtitle(): void
    {
        $root = dirname(__DIR__, 3);
        $docs = $this->docFile('chat.html');
        $index = $this->docFile('index.html');
        $js = (string) file_get_contents($root.'/resources/js/chat.js');
        $blade = (string) file_get_contents($root.'/resources/views/chat/index.blade.php');
        $css = (string) file_get_contents($root.'/resources/css/chat.css');
        $service = (string) file_get_contents($root.'/app/Services/Chat/ChatService.php');
        $presence = (string) file_get_contents($root.'/app/Services/Chat/UserPresence.php');
        $echo = (string) file_get_contents($root.'/resources/views/includes/in_app_notifications/echo.blade.php');

        $this->assertStringContainsString('id="header-subtitle"', $docs);
        $this->assertStringContainsString('/doc#chat-header-subtitle-index', $docs);
        $this->assertStringContainsString('serializeThreadHeader', $docs);
        $this->assertStringContainsString('dialogStatusLabel', $docs);
        $this->assertStringContainsString('membersCountLabel', $docs);
        $this->assertStringContainsString('не «1 минуту»', $docs);
        $this->assertStringContainsString('H:i j F Y', $docs);
        $this->assertStringContainsString('d.m.Y H:i', $docs);
        $this->assertStringContainsString('visibleGroupMembersCount', $docs);
        $this->assertStringContainsString('fetchGroupMembers', $docs);
        $this->assertStringContainsString('id="chat-header-subtitle-index"', $index);
        $this->assertStringContainsString('/docs/documentation/chat#header-subtitle', $index);

        $this->assertStringContainsString('id="threadSubtitle"', $blade);
        $this->assertStringContainsString('chat-header-subtitle', $blade);
        $this->assertStringContainsString('style="display:none;"', $blade);
        $this->assertStringContainsString('.chat-header-subtitle {', $css);
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
        $cssMediaPos = strpos($css, '@media (max-width: 991.98px)');
        $headerTextPos = strpos($css, '.chat-header-text {');
        $this->assertNotFalse($cssMediaPos);
        $this->assertNotFalse($headerTextPos);
        $this->assertLessThan($cssMediaPos, $headerTextPos);

        $this->assertStringContainsString('function setThreadSubtitle(', $js);
        $this->assertStringContainsString('el.textContent = s;', $js);
        $this->assertStringContainsString("el.style.display = s === '' ? 'none' : '';", $js);
        $this->assertStringContainsString('setThreadSubtitle(res.thread.header_subtitle)', $js);
        $this->assertStringContainsString('setThreadSubtitle(membersCountLabel(thread.members_total))', $js);
        $this->assertStringContainsString('function membersCountLabel(', $js);

        $openStart = strpos($js, 'function openThread(');
        $this->assertNotFalse($openStart);
        $openChunk = substr($js, $openStart, 6000);
        $this->assertStringContainsString('setThreadSubtitle(res.thread.header_subtitle)', $openChunk);
        $this->assertStringNotContainsString('membersCountLabel(res.thread.members_total)', $openChunk);

        $bumpStart = strpos($js, 'function applyInboxBump(');
        $this->assertNotFalse($bumpStart);
        $bumpEnd = strpos($js, 'function subscribeInbox(');
        $this->assertNotFalse($bumpEnd);
        $bump = substr($js, $bumpStart, $bumpEnd - $bumpStart);
        $this->assertStringNotContainsString('setThreadSubtitle', $bump);
        $this->assertStringContainsString('closeCurrentThread', $bump);
        $this->assertStringContainsString('peer_is_online', $bump);

        $this->assertStringContainsString('function serializeThreadHeader(', $service);
        $inboxStart = strpos($service, 'private function serializeThread(');
        $headerStart = strpos($service, 'private function serializeThreadHeader(');
        $this->assertNotFalse($inboxStart);
        $this->assertNotFalse($headerStart);
        $this->assertGreaterThan($inboxStart, $headerStart);
        $inboxChunk = substr($service, $inboxStart, $headerStart - $inboxStart);
        $headerChunk = substr($service, $headerStart, 1600);
        $this->assertStringContainsString("'header_subtitle'", $headerChunk);
        $this->assertStringContainsString("'peer_presence_label'", $headerChunk);
        $this->assertStringContainsString("'members_total'", $headerChunk);
        $this->assertStringContainsString('function membersCountLabel(int $n)', $service);
        $this->assertStringContainsString('function visibleGroupMembersCount(', $service);
        $this->assertStringNotContainsString("'header_subtitle'", $inboxChunk);
        $this->assertStringNotContainsString("'peer_presence_label'", $inboxChunk);
        $this->assertStringNotContainsString("'members_total'", $inboxChunk);

        $this->assertStringContainsString('public const ONLINE_WITHIN_SECONDS = 120;', $presence);
        $this->assertStringContainsString('function dialogStatusLabel(', $presence);
        $this->assertStringContainsString('if ($minutes < 2) {', $presence);
        $this->assertStringContainsString('$minutes = 2;', $presence);
        $this->assertStringContainsString("translatedFormat('H:i j F Y')", $presence);
        $this->assertStringContainsString("locale('ru')", $presence);

        $this->assertStringContainsString("format('d.m.Y H:i')", $service);
        $this->assertStringContainsString("'last_seen_label'", $service);

        $this->assertStringContainsString('function ping()', $echo);
        $this->assertStringNotContainsString('threadSubtitle', $echo);
        $this->assertStringNotContainsString('header_subtitle', $echo);
        $this->assertStringNotContainsString('setThreadSubtitle', $echo);
    }

    private function docFile(string $name): string
    {
        $path = dirname(__DIR__, 3).'/docs/documentation/'.$name;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
