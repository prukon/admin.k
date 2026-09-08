<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Chat;

/**
 * P1: нативный GET карточки без X-Requested-With — JSON 200/403, не пустая HTML-страница.
 * POST/PATCH/PUT/DELETE — 405, не сырой 200.
 *
 * GET не создаёт запись: safety-net здесь «не белый экран / не пустой 200», не 302.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class ChatPeerCardCrossPartnerNonAjaxSafetyNetFeatureTest extends ChatTestCase
{
    public function test_native_get_same_school_card_returns_json_not_empty_page(): void
    {
        $peer = $this->makePeer('НативСвоя_');

        $response = $this->from(route('chat.index'))
            ->get(route('chat.api.users.show', $peer));

        $this->assertNotSame(500, $response->getStatusCode());
        $response
            ->assertOk()
            ->assertJsonPath('id', (int) $peer->id)
            ->assertJsonPath('partner_name', $this->partner->title);
        $this->assertStringContainsString(
            'application/json',
            (string) $response->headers->get('content-type')
        );
        $this->assertNotSame('', trim((string) $response->getContent()));
        $this->assertStringNotContainsString('<html', strtolower((string) $response->getContent()));
    }

    public function test_native_get_foreign_card_returns_json_403_with_errors_user_not_empty_page(): void
    {
        $response = $this->from(route('chat.index'))
            ->get(route('chat.api.users.show', $this->foreignUser));

        $this->assertNotSame(500, $response->getStatusCode());
        $this->assertNotSame(200, $response->getStatusCode(), 'Нативный GET чужой школы не пустой 200');
        $this->assertChatPeerCardForbidden($response);
        $this->assertStringContainsString(
            'application/json',
            (string) $response->headers->get('content-type')
        );
        $this->assertStringNotContainsString(
            'This action is unauthorized.',
            (string) $response->getContent()
        );
        $this->assertStringNotContainsString('<html', strtolower((string) $response->getContent()));
    }

    public function test_native_get_superadmin_cross_school_with_live_thread_returns_json_profile(): void
    {
        $homePeer = $this->makePeer('НативSaHome_');
        $this->asSuperadmin();
        $this->withSession([
            'current_partner' => (int) $this->foreignPartner->id,
            '2fa:passed' => true,
        ]);
        $this->createThreadForUsers([$this->user->id, $homePeer->id], 'NativeSaSwitch');

        $response = $this->from(route('chat.index'))
            ->get(route('chat.api.users.show', $homePeer));

        $this->assertNotSame(500, $response->getStatusCode());
        $response
            ->assertOk()
            ->assertJsonPath('id', (int) $homePeer->id)
            ->assertJsonPath('partner_name', $this->partner->title);
        $this->assertStringContainsString(
            'application/json',
            (string) $response->headers->get('content-type')
        );
    }

    public function test_native_mutations_on_peer_card_are_405_not_empty_200(): void
    {
        $peer = $this->makePeer('НативМутация_');

        foreach (['POST', 'PATCH', 'PUT', 'DELETE'] as $method) {
            $response = $this->from(route('chat.index'))
                ->call($method, route('chat.api.users.show', $peer));

            $this->assertNotSame(500, $response->getStatusCode(), $method.' не 500');
            $this->assertNotSame(200, $response->getStatusCode(), $method.' не пустой 200');
            $this->assertSame(405, $response->getStatusCode(), $method.' должен быть 405');
        }
    }
}
