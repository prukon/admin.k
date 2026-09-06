<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Chat;

/**
 * P1: AJAX GET карточки (Accept JSON + X-Requested-With) — JSON, errors.user, не английский 403.
 *
 * UX-баг до фикса: 403 без errors.user, message = «This action is unauthorized.» —
 * модалка #peerCardError показывала английский текст вместо профиля.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ChatPeerCardCrossPartnerAjaxContractFeatureTest extends ChatTestCase
{
    public function test_ajax_forbidden_card_returns_russian_errors_user_not_english_unauthorized(): void
    {
        $response = $this->getJson(
            route('chat.api.users.show', $this->foreignUser),
            $this->ajaxHeaders()
        );

        $this->assertChatPeerCardForbidden($response);
        $this->assertStringNotContainsString(
            'This action is unauthorized.',
            (string) $response->getContent()
        );
        $this->assertIsArray($response->json('errors.user'));
        $this->assertSame(
            'Нет доступа к карточке этого пользователя.',
            $response->json('errors.user.0')
        );
        $this->assertSame(
            $response->json('message'),
            $response->json('errors.user.0'),
            'message и errors.user должны совпадать, чтобы модалка не показала leftover английский'
        );
    }

    public function test_ajax_shared_thread_does_not_open_foreign_card_for_regular_user(): void
    {
        $this->createThreadForUsers([$this->user->id, $this->foreignUser->id], 'AjaxMixed');

        $response = $this->getJson(
            route('chat.api.users.show', $this->foreignUser),
            $this->ajaxHeaders()
        );

        $this->assertChatPeerCardForbidden($response);
        $this->assertStringNotContainsString('This action is unauthorized.', (string) $response->getContent());
    }

    public function test_ajax_superadmin_with_live_thread_returns_card_payload(): void
    {
        $this->asSuperadmin();
        $this->withSession([
            'current_partner' => (int) $this->foreignPartner->id,
            '2fa:passed' => true,
        ]);
        $homePeer = $this->makePeer('AjaxSaHome_', [
            'lastname' => 'Петров',
            'name' => 'Пётр',
        ]);
        $this->createThreadForUsers([$this->user->id, $homePeer->id], 'AjaxSaSwitch');

        $this->getJson(route('chat.api.users.show', $homePeer), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('id', (int) $homePeer->id)
            ->assertJsonPath('full_name', $homePeer->full_name)
            ->assertJsonPath('partner_name', $this->foreignPartner->title)
            ->assertJsonMissingPath('errors.user')
            ->assertJsonStructure([
                'id',
                'avatar',
                'full_name',
                'phone',
                'parent_full_name',
                'parent_phone',
                'is_online',
                'last_seen_label',
                'team_title',
                'partner_name',
            ]);
    }

    public function test_ajax_same_school_card_is_200_without_forcing_a_shared_thread(): void
    {
        $peer = $this->makePeer('AjaxSameSchool_');

        $this->getJson(route('chat.api.users.show', $peer), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('id', (int) $peer->id)
            ->assertJsonMissingPath('errors');
    }

    /**
     * @return array<string, string>
     */
    private function ajaxHeaders(): array
    {
        return ['X-Requested-With' => 'XMLHttpRequest'];
    }
}
