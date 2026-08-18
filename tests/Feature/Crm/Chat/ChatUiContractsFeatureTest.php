<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Chat;

/**
 * P1: разметка страницы, сайдбар @can, дефолты формы, JS-контракт (не только 200 OK).
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
        $this->assertStringContainsString('Выберите диалог', $page);
        $this->assertStringContainsString('id="threadAvatar"', $page);
        $this->assertStringContainsString('style="display:none;"', $page);
        $this->assertStringContainsString('id="msgInput"', $page);
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
        $this->assertStringNotContainsString('Создать группу', $html);

        $modalStart = strpos($html, 'id="contactsModal"');
        $this->assertNotFalse($modalStart);
        $modal = substr($html, $modalStart, 1500);
        $this->assertStringNotContainsString('modal-xl', $modal);
        $this->assertStringNotContainsString('modal-fullscreen', $modal);
        $this->assertStringNotContainsString('modal-dialog-scrollable', $modal);
        $this->assertStringContainsString('class="modal-dialog"', $modal);
        $this->assertStringContainsString('max-height: min(60vh, 520px)', $html);
        $this->assertStringContainsString('id="contactsSearch"', $modal);
        $this->assertStringContainsString('id="contactsError"', $modal);
        $this->assertStringContainsString('id="msgBodyError"', $page);
        $this->assertStringContainsString('id="threadPeerHit"', $page);
        $this->assertStringContainsString('chat-header-peer is-idle', $page);
        $this->assertStringContainsString('id="peerCardModal"', $html);
        $this->assertStringContainsString('id="peerCardError"', $html);
        $this->assertStringContainsString('id="peerCardBody"', $html);
        $peerModalStart = strpos($html, 'id="peerCardModal"');
        $this->assertNotFalse($peerModalStart);
        $peerModal = substr($html, $peerModalStart, 900);
        $this->assertStringNotContainsString('modal-xl', $peerModal);
        $this->assertStringNotContainsString('modal-fullscreen', $peerModal);
        $this->assertStringContainsString('class="modal-dialog"', $peerModal);
        $this->assertStringContainsString('js/chat.js', $html);
        $this->assertStringContainsString('chat-online-dot', $html);
        $this->assertStringContainsString('contact-online-dot', $html);
        $this->assertStringContainsString('contact-parent', $html);
        $this->assertStringContainsString('contact-main', $html);
        $this->assertStringContainsString('contact-team', $html);
        $this->assertStringContainsString('contact-role', $html);
        $this->assertStringContainsString('align-items: flex-start', $html);
        $this->assertStringContainsString('chat-li-unread', $html);
        $this->assertStringContainsString('#f3a12b', $html);
        $this->assertStringContainsString('chat-li-preview.is-draft', $html);

        $blade = (string) file_get_contents(resource_path('views/chat/index.blade.php'));
        $this->assertDoesNotMatchRegularExpression(
            '/<script(?![^>]*\bsrc\b)[^>]*>/i',
            $blade,
            'chat/index.blade.php не должен содержать inline <script> с AJAX-submit'
        );
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
        $js = (string) file_get_contents(public_path('js/chat.js'));

        $openThreadPos = strpos($js, 'function openThread(');
        $this->assertNotFalse($openThreadPos);
        $openThreadChunk = substr($js, $openThreadPos, 2800);
        $this->assertStringContainsString('persistLeavingDraft(threadId)', $openThreadChunk);
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
        $this->assertStringContainsString('setHeaderPeerClickable(!!currentPeerId)', $openThreadChunk);
        $this->assertStringContainsString("av.style.display = ''", $openThreadChunk);
        $this->assertStringContainsString('subscribeThread(currentThreadId)', $openThreadChunk);
        $this->assertStringContainsString('setUnreadBadge(res.unread_total)', $openThreadChunk);
        $this->assertStringContainsString('if (String(currentThreadId) === String(threadId))', $openThreadChunk);
        $this->assertStringContainsString("showMsgError('Не удалось открыть диалог.')", $openThreadChunk);

        $this->assertSame(
            1,
            substr_count($js, 'setComposerEnabled(true)'),
            'Композер включается только после открытия диалога'
        );

        $openContactsPos = strpos($js, "getElementById('openContactsBtn')");
        $this->assertNotFalse($openContactsPos);
        $openContactsChunk = substr($js, $openContactsPos, 450);
        $this->assertStringContainsString("contactsSearch').value = ''", $openContactsChunk);
        $this->assertStringContainsString("loadContacts('')", $openContactsChunk);
        $this->assertStringContainsString('contactsModal().show()', $openContactsChunk);
        $this->assertStringNotContainsString('setComposerEnabled(true)', $openContactsChunk);
        $this->assertStringNotContainsString('threadSearch', $openContactsChunk);

        $startDialogPos = strpos($js, 'function startDialog(');
        $this->assertNotFalse($startDialogPos);
        $startDialogChunk = substr($js, $startDialogPos, 1600);
        $this->assertStringContainsString('Number(t.peer_id) === Number(userId)', $startDialogChunk);
        $this->assertStringContainsString('openThread(existing.id)', $startDialogChunk);
        $this->assertStringContainsString("JSON.stringify({ user_id: userId })", $startDialogChunk);
        $this->assertStringContainsString("fieldError(res.data, 'user_id')", $startDialogChunk);
        $this->assertStringContainsString('if (startDialogBusy)', $startDialogChunk);
        $this->assertStringContainsString('startDialogBusy = true', $startDialogChunk);
        $this->assertStringContainsString('startDialogBusy = false', $startDialogChunk);
        $this->assertStringContainsString('Number(t.peer_id) !== Number(patch.peer_id)', $js);
    }

    public function test_javascript_submit_prevents_default_and_maps_body_field_errors(): void
    {
        $js = (string) file_get_contents(public_path('js/chat.js'));

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
        $this->assertStringContainsString("socketState() === 'connected'", $js);
        $this->assertStringContainsString('markThreadRead(currentThreadId)', $js);
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
        $this->assertStringContainsString('peerCardModal', $js);
        $this->assertStringContainsString('last_seen_label', $js);
        $this->assertStringContainsString("href=\"' + escapeHtml(href)", $js);
        $this->assertStringContainsString("urls.users + '/' + encodeURIComponent", $js);
        $this->assertStringContainsString('function persistLeavingDraft(', $js);
        $this->assertStringContainsString('function scheduleDraftSave(', $js);
        $this->assertStringContainsString("threadUrl(id, '/draft')", $js);
        $this->assertStringContainsString("addEventListener('input', scheduleDraftSave)", $js);

        $renderThreadsPos = strpos($js, 'function renderThreads(');
        $this->assertNotFalse($renderThreadsPos);
        $renderThreadsChunk = substr($js, $renderThreadsPos, strpos($js, 'function upsertThread(') - $renderThreadsPos);
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

        $openPeerPos = strpos($js, 'function openPeerCard(');
        $this->assertNotFalse($openPeerPos);
        $openPeerChunk = substr($js, $openPeerPos, strpos($js, 'function renderContacts(') - $openPeerPos);
        $this->assertStringContainsString('if (!currentPeerId)', $openPeerChunk);
        $this->assertStringContainsString('function dashText(', $js);
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
        $this->assertStringContainsString("socketState() === 'connected'", $blade);
        $this->assertStringContainsString('onChatPage ? 1000 : 12000', $blade);
        $this->assertStringContainsString('}, 1000);', $blade);
        $this->assertStringContainsString("connection.bind('connected'", $blade);
    }

    public function test_chat_page_renders_echo_handoff_so_open_dialog_owns_the_badge(): void
    {
        $html = $this->get(route('chat.index'))->assertOk()->getContent();
        $this->assertStringContainsString('KidsCrmChatOnInboxBump', $html);
        $this->assertStringContainsString("channel.listen('.inbox.bump'", $html);
        $this->assertStringContainsString('js/chat.js', $html);
        $this->assertStringContainsString('id="chatApp"', $html);
    }

    public function test_reverb_overlay_is_only_for_superadmin_and_stays_on_top(): void
    {
        $userHtml = $this->get(route('chat.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('id="js-reverb-status"', $userHtml);

        $superadmin = $this->createUserWithRole('superadmin');
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
}
