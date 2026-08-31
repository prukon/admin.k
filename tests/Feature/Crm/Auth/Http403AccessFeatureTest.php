<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Auth;

use Illuminate\Support\Facades\Auth;

/**
 * P1: /broadcasting/auth — гость 403 (не редирект на логин и не 500);
 * свой канал — 200; чужой — 403; CRM-право messages.view не требуется.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class Http403AccessFeatureTest extends SessionAuthTestCase
{
    public function test_guest_broadcasting_auth_is_forbidden_not_login_redirect(): void
    {
        $this->actingAsGuest();

        $html = $this->post($this->broadcastingAuthUrl(), $this->broadcastingAuthPayload());
        $this->assertNotServerError($html, 'POST broadcasting HTML гость');
        $html->assertForbidden();
        $this->assertFalse($html->isRedirect(), 'гость Echo не должен уходить на /login');
        $this->assertGuest();

        $json = $this->postJson(
            $this->broadcastingAuthUrl(),
            $this->broadcastingAuthPayload(),
            $this->ajaxHeaders()
        );
        $this->assertNotServerError($json, 'POST broadcasting JSON гость');
        $json->assertForbidden();
        $this->assertFalse($json->isRedirect());
        $this->assertNotSame(401, $json->getStatusCode(), 'Broadcast::routes без auth middleware: 403, не 401');
    }

    public function test_authenticated_user_can_subscribe_to_own_inbox(): void
    {
        $this->enableBroadcastAuthDriver();
        $this->asAdmin();

        $response = $this->actingAs($this->user)->post(
            $this->broadcastingAuthUrl(),
            $this->broadcastingAuthPayload((int) $this->user->id)
        );
        $this->assertNotServerError($response, 'свой inbox admin');
        $response->assertOk();
        $this->assertNotSame('', trim((string) $response->getContent()));
    }

    public function test_authenticated_user_cannot_subscribe_to_foreign_inbox(): void
    {
        $this->asAdmin();

        $response = $this->actingAs($this->user)->post(
            $this->broadcastingAuthUrl(),
            $this->broadcastingAuthPayload((int) $this->foreignUser->id)
        );
        $this->assertNotServerError($response, 'чужой inbox');
        $response->assertForbidden();
        $this->assertNotSame(200, $response->getStatusCode());
    }

    public function test_user_without_messages_view_can_still_auth_own_inbox(): void
    {
        $this->enableBroadcastAuthDriver();
        $denied = $this->createUserWithoutPermission('messages.view', $this->partner);
        $this->actingAs($denied);

        $ok = $this->post(
            $this->broadcastingAuthUrl(),
            $this->broadcastingAuthPayload((int) $denied->id)
        );
        $this->assertNotServerError($ok, 'свой inbox без messages.view');
        $ok->assertOk();

        $deniedForeign = $this->post(
            $this->broadcastingAuthUrl(),
            $this->broadcastingAuthPayload((int) $this->foreignUser->id)
        );
        $this->assertNotServerError($deniedForeign, 'чужой inbox без messages.view');
        $deniedForeign->assertForbidden();
    }

    public function test_trainer_and_student_get_own_inbox_and_403_on_foreign(): void
    {
        $this->enableBroadcastAuthDriver();

        foreach (['trainer', 'user'] as $roleName) {
            $actor = $this->createUserWithRole($roleName, $this->partner);
            $this->actingAs($actor);

            $own = $this->post(
                $this->broadcastingAuthUrl(),
                $this->broadcastingAuthPayload((int) $actor->id)
            );
            $this->assertNotServerError($own, $roleName.' свой inbox');
            $own->assertOk();

            $foreign = $this->post(
                $this->broadcastingAuthUrl(),
                $this->broadcastingAuthPayload((int) $this->foreignUser->id)
            );
            $this->assertNotServerError($foreign, $roleName.' чужой inbox');
            $foreign->assertForbidden();
        }
    }

    public function test_superadmin_is_not_used_as_without_permission_stand_in(): void
    {
        $this->enableBroadcastAuthDriver();
        $this->asSuperadmin();

        $this->actingAs($this->user)
            ->post($this->broadcastingAuthUrl(), $this->broadcastingAuthPayload((int) $this->user->id))
            ->assertOk();

        Auth::logout();
        $this->post($this->broadcastingAuthUrl(), $this->broadcastingAuthPayload())
            ->assertForbidden();
    }
}
