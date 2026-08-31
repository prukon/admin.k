<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Auth;

use Illuminate\Support\Facades\Auth;

/**
 * P1: /login — гость видит форму; залогиненный уходит в кабинет;
 * CRM-права на вход не нужны; чужие методы не 500.
 *
 * @see \Tests\Feature\Crm\Teams\TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class LoginRememberAccessFeatureTest extends SessionAuthTestCase
{
    public function test_guest_can_open_login_form(): void
    {
        $this->actingAsGuest();

        $response = $this->get($this->loginUrl());
        $this->assertNotServerError($response, 'GET /login гость');
        $response->assertOk();
        $this->assertNotSame('', trim((string) $response->getContent()));
        $response->assertSee('id="remember"', false);
        $response->assertSee('Авторизация', false);
    }

    public function test_authenticated_user_is_redirected_away_from_login(): void
    {
        $this->asAdmin();

        $html = $this->actingAs($this->user)->get($this->loginUrl());
        $this->assertNotServerError($html, 'GET /login HTML залогиненный');
        $this->assertNotEmptyOk($html, 'GET /login HTML залогиненный');
        $html->assertRedirect('/cabinet');

        $json = $this->actingAs($this->user)->getJson($this->loginUrl(), $this->ajaxHeaders());
        $this->assertNotServerError($json, 'GET /login JSON залогиненный');
        $this->assertNotEmptyOk($json, 'GET /login JSON залогиненный');
        $json->assertRedirect('/cabinet');
    }

    public function test_admin_without_crm_permission_can_still_sign_in(): void
    {
        $actor = $this->createUserWithoutPermission('groups.view', $this->partner);
        $actor->password = 'login-no-crm-perm';
        $actor->save();
        Auth::logout();

        $response = $this->from($this->loginUrl())->post($this->loginUrl(), [
            'email' => (string) $actor->email,
            'password' => 'login-no-crm-perm',
            'remember' => 'on',
        ]);
        $this->assertNotServerError($response, 'POST /login без groups.view');
        $response->assertRedirect('/cabinet');
        $this->assertAuthenticatedAs($actor);
    }

    public function test_trainer_and_student_can_sign_in_without_extra_permission(): void
    {
        foreach (['trainer', 'user'] as $roleName) {
            $actor = $this->createUserWithRole($roleName, $this->partner, [
                'password' => 'role-login-secret',
            ]);
            Auth::logout();

            $response = $this->from($this->loginUrl())->post($this->loginUrl(), [
                'email' => (string) $actor->email,
                'password' => 'role-login-secret',
            ]);
            $this->assertNotServerError($response, 'POST /login '.$roleName);
            $response->assertRedirect('/cabinet');
            $this->assertAuthenticatedAs($actor);
        }
    }

    public function test_guest_logout_is_not_empty_200(): void
    {
        $this->actingAsGuest();

        $html = $this->post(route('logout'));
        $this->assertDeniedWithoutServerError($html, 'POST /logout HTML гость');

        $json = $this->postJson(route('logout'), [], $this->ajaxHeaders());
        $this->assertNotServerError($json, 'POST /logout JSON гость');
        $this->assertNotSame(200, $json->getStatusCode(), 'POST /logout JSON гость не пустой 200');
    }
}
