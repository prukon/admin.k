<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Chat;

/**
 * UX-баги мобильной оболочки /chat: шапка кабинета, нижнее меню как в Telegram,
 * дата в пузыре, скролл внутри переписки, зум.
 *
 * Серверный 200 недостаточен — проверяем CSS/JS/HTML, которые ломали экран.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ChatMobileLayoutUxFeatureTest extends ChatTestCase
{
    public function test_cabinet_header_stays_visible_on_mobile_chat(): void
    {
        $css = $this->chatCss();
        $media = $this->mobileMedia($css);

        $this->assertStringContainsString('body.chat-immersive .main-header', $media);
        $this->assertStringContainsString('flex: 0 0 auto', $this->headerRule($media));
        $this->assertStringNotContainsString(
            "body.chat-immersive .main-header,\n    body.chat-immersive .main-sidebar,\n    body.chat-immersive .main-footer",
            $media,
            'Шапка не должна скрываться вместе с сайдбаром и футером'
        );
        $this->assertStringContainsString('body.chat-immersive .main-footer', $media);
        $this->assertStringContainsString(
            'body.chat-immersive .main-footer,',
            $media
        );

        $html = $this->get(route('chat.index'))->assertOk()->getContent();
        $this->assertStringContainsString('data-widget="pushmenu"', $html);
        $this->assertStringContainsString('Выйти', $html);
        $this->assertStringContainsString('fa-bell', $html);
    }

    public function test_opening_a_group_from_chats_tab_still_shows_the_dialog_column(): void
    {
        $media = $this->mobileMedia($this->chatCss());

        $this->assertStringContainsString(
            '#chatApp:not([data-mobile-tab="messages"]) .chat-desktop-row { display: none; }',
            $media
        );
        $this->assertStringContainsString(
            '#chatApp[data-mobile-tab="groups"].is-dialog-open .chat-desktop-row { display: flex; }',
            $media
        );
        $this->assertStringContainsString(
            '#chatApp[data-mobile-tab="groups"].is-dialog-open .chat-list-col { display: none; }',
            $media
        );
        $this->assertStringContainsString(
            '#chatApp[data-mobile-tab="groups"].is-dialog-open #chatPaneGroups { display: none; }',
            $media
        );
        $this->assertStringContainsString('#groupThreads { flex: 1 1 0%; min-height: 0; overflow: auto; }', $media);
    }

    public function test_opening_a_dialog_hides_bottom_tabs_like_telegram(): void
    {
        $css = $this->chatCss();
        $this->assertStringContainsString(
            '#chatApp.is-dialog-open .chat-mobile-nav { display: none; }',
            $this->mobileMedia($css)
        );

        $js = (string) file_get_contents(resource_path('js/chat.js'));
        $this->assertStringContainsString("classList.add('is-dialog-open')", $js);
        $this->assertStringContainsString("classList.remove('is-dialog-open')", $js);

        $html = $this->get(route('chat.index'))->assertOk()->getContent();
        $this->assertStringContainsString('id="chatMobileNav"', $html);
        $this->assertStringContainsString('id="chatMobileBack"', $html);
    }

    public function test_create_group_members_modal_keeps_footer_on_screen(): void
    {
        $media = $this->mobileMedia($this->chatCss());

        $this->assertStringContainsString('#createGroupMembersModal .modal-dialog {', $media);
        $this->assertStringContainsString('max-height: calc(100dvh - 1rem)', $media);
        $this->assertStringContainsString('#createGroupMembersModal #createGroupMembersForm {', $media);
        $this->assertStringContainsString('#createGroupMembersModal .modal-footer {', $media);
        $this->assertStringContainsString('flex: 0 0 auto', $media);
        $this->assertStringContainsString('#createGroupMembersModal .contact-list {', $media);
        $this->assertStringContainsString('max-height: calc(100dvh - 16rem)', $media);
        $this->assertStringContainsString("#createGroupMembersModal {\n        overflow: hidden;", $media);
        $this->assertStringNotContainsString(
            "#createGroupMembersModal .contact-list {\n        max-height: none;",
            $media,
            'Без max-height список вылезает за модалку: форма ломает flex'
        );
        $this->assertStringNotContainsString('modal-fullscreen', $media);
        $this->assertStringNotContainsString('modal-xl', $media);

        $html = $this->get(route('chat.index'))->assertOk()->getContent();
        $start = strpos($html, 'id="createGroupMembersModal"');
        $this->assertNotFalse($start);
        $modal = substr($html, $start, 1800);
        $this->assertStringContainsString('class="modal-dialog"', $modal);
        $this->assertStringNotContainsString('modal-fullscreen', $modal);
        $this->assertStringNotContainsString('modal-xl', $modal);
    }

    public function test_contact_and_parent_names_align_left_against_sidebar_mini(): void
    {
        $css = $this->chatCss();
        $mediaPos = strpos($css, '@media (max-width: 991.98px)');
        $this->assertNotFalse($mediaPos);
        $this->assertLessThan($mediaPos, strpos($css, '.contact-name {'));
        $this->assertLessThan($mediaPos, strpos($css, '.contact-parent {'));
        $this->assertMatchesRegularExpression('/\.contact-name\s*\{[^}]*text-align:\s*left/', $css);
        $this->assertMatchesRegularExpression('/\.contact-parent\s*\{[^}]*text-align:\s*left/', $css);
        $this->assertMatchesRegularExpression('/\.contact-main\s*\{[^}]*text-align:\s*left/', $css);
    }

    public function test_one_character_message_keeps_timestamp_on_one_line(): void
    {
        $css = $this->chatCss();

        $this->assertStringContainsString(
            "white-space: nowrap; word-break: normal; flex-shrink: 0;\n}",
            $css
        );
        $this->assertStringContainsString(
            '.msg-meta .time { white-space: nowrap; word-break: keep-all; flex-shrink: 0; }',
            $css
        );
        $this->assertStringContainsString('min-width: 6.5rem', $css);

        $js = (string) file_get_contents(resource_path('js/chat.js'));
        $this->assertStringContainsString('class="msg-meta"', $js);
        $this->assertStringContainsString("class=\"time\"", $js);
    }

    public function test_message_pane_scrolls_inside_instead_of_pushing_tabs_to_the_middle(): void
    {
        $media = $this->mobileMedia($this->chatCss());

        $this->assertStringContainsString('grid-template-rows: minmax(0, 1fr) auto', $media);
        $this->assertStringContainsString('#messagesBox { flex: 1 1 auto; min-height: 0; overflow: auto; }', $media);
        $this->assertStringContainsString('.chat-composer { flex: 0 0 auto; margin-top: auto; }', $media);
        $this->assertStringContainsString('height: 0 !important; flex: 1 1 auto', $media);
        $this->assertStringContainsString('overscroll-behavior: contain', $this->chatCss());
    }

    public function test_chat_page_disables_phone_zoom_and_other_cabinet_pages_do_not(): void
    {
        $chat = $this->get(route('chat.index'))->assertOk()->getContent();
        $this->assertStringContainsString('maximum-scale=1, user-scalable=no', $chat);
        $this->assertStringContainsString('html { touch-action: pan-x pan-y; }', $this->chatCss());

        $js = (string) file_get_contents(resource_path('js/chat.js'));
        $this->assertStringContainsString("addEventListener('gesturestart', preventPageZoom", $js);
        $this->assertStringContainsString("addEventListener('gesturechange', preventPageZoom", $js);

        $cabinet = $this->get(route('dashboard'))->assertOk()->getContent();
        $this->assertStringNotContainsString('user-scalable=no', $cabinet);
    }

    private function chatCss(): string
    {
        $path = resource_path('css/chat.css');
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    private function mobileMedia(string $css): string
    {
        $pos = strpos($css, '@media (max-width: 991.98px)');
        $this->assertNotFalse($pos);

        return substr($css, $pos);
    }

    private function headerRule(string $media): string
    {
        $pos = strpos($media, 'body.chat-immersive .main-header');
        $this->assertNotFalse($pos);

        return substr($media, $pos, 220);
    }
}
