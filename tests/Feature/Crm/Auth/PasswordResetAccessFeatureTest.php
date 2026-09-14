<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Auth;

use Illuminate\Support\Facades\Notification;

/**
 * P1: /password/reset — гость видит форму (не /login, не 401/403);
 * CRM-права не нужны; залогиненный не 500.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class PasswordResetAccessFeatureTest extends PasswordResetTestCase
{
    public function test_guest_opens_reset_form_instead_of_login_redirect(): void
    {
        $this->actingAsGuest();

        $response = $this->get($this->passwordRequestUrl());
        $this->assertNotServerError($response, 'GET /password/reset гость');
        $response->assertOk();
        $this->assertFalse($response->isRedirect(), 'гость не уходит на /login');
        $this->assertNotSame(401, $response->getStatusCode());
        $this->assertNotSame(403, $response->getStatusCode());
        $this->assertNotSame('', trim((string) $response->getContent()));
        $response->assertSee('id="forgot-password-form"', false);
        $response->assertSee('id="forgot-password-submit"', false);
        $response->assertSee('Сброс пароля', false);
        $response->assertDontSee('403 — Доступ запрещён', false);
    }

    public function test_authenticated_user_can_open_reset_form_without_500(): void
    {
        $this->asAdmin();

        $html = $this->actingAs($this->user)->get($this->passwordRequestUrl());
        $this->assertNotServerError($html, 'GET /password/reset HTML залогиненный');
        $html->assertOk();
        $html->assertSee('id="forgot-password-form"', false);

        $json = $this->actingAs($this->user)->getJson($this->passwordRequestUrl(), $this->ajaxHeaders());
        $this->assertNotServerError($json, 'GET /password/reset JSON залогиненный');
        $json->assertOk();
    }

    public function test_signed_in_user_without_crm_permission_is_not_forbidden(): void
    {
        $actor = $this->createUserWithoutPermission('groups.view', $this->partner);
        Notification::fake();

        $get = $this->actingAs($actor)->get($this->passwordRequestUrl());
        $this->assertNotServerError($get, 'GET /password/reset без groups.view');
        $get->assertOk();
        $this->assertNotSame(403, $get->getStatusCode());
        $get->assertDontSee('403 — Доступ запрещён', false);
        $get->assertSee('id="forgot-password-form"', false);

        $post = $this->actingAs($actor)->from($this->passwordRequestUrl())->post($this->passwordEmailUrl(), [
            'email' => (string) $actor->email,
        ]);
        $this->assertNotServerError($post, 'POST /password/email без groups.view залогиненный');
        $this->assertNotSame(403, $post->getStatusCode());
        $post->assertRedirect($this->passwordRequestUrl());
        $post->assertSessionHas('status', $this->sentStatusMessage());
    }

    public function test_admin_without_crm_permission_can_request_reset_link(): void
    {
        $actor = $this->createUserWithoutPermission('groups.view', $this->partner);
        $email = (string) $actor->email;

        $this->actingAsGuest();
        Notification::fake();

        $response = $this->from($this->passwordRequestUrl())->post($this->passwordEmailUrl(), [
            'email' => $email,
        ]);
        $this->assertNotServerError($response, 'POST /password/email без groups.view');
        $response->assertRedirect($this->passwordRequestUrl());
        $response->assertSessionHas('status', $this->sentStatusMessage());
    }

    public function test_trainer_and_student_can_request_reset_link(): void
    {
        foreach (['trainer', 'user'] as $roleName) {
            $actor = $this->createUserWithRole($roleName, $this->partner);
            $this->actingAsGuest();
            Notification::fake();

            $response = $this->from($this->passwordRequestUrl())->post($this->passwordEmailUrl(), [
                'email' => (string) $actor->email,
            ]);
            $this->assertNotServerError($response, 'POST /password/email '.$roleName);
            $response->assertRedirect($this->passwordRequestUrl());
            $response->assertSessionHas('status');
        }
    }
}
