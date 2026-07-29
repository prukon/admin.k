<?php

namespace Tests\Feature\Crm\Users;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Crm\CrmTestCase;
use Tests\Feature\Crm\Users\Concerns\GrantsUsersSectionPermissions;

/**
 * Контроль доступа для send_welcome_email при ручном «Добавить»
 * (ученик / тренер / администратор): гость → 302/401, без прав → 403, с правами → ожидаемый статус.
 *
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 * @see ClientWelcomeCredentialsFullAccessFeatureTest
 */
final class SendWelcomeEmailCreateFullAccessFeatureTest extends CrmTestCase
{
    use GrantsUsersSectionPermissions;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);
    }

    public function test_guest_is_denied_on_send_welcome_email_create_endpoints(): void
    {
        Auth::logout();

        foreach ($this->sendWelcomeCreateRoutesPayload() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json']
            );

            $this->assertContains(
                $response->getStatusCode(),
                [302, 401, 403, 419],
                "Гость: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_user_without_users_view_gets_403_on_users_welcome_create(): void
    {
        $actor = $this->createUserWithoutPermission('users.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);

        $this->get(route('admin.user1'))->assertStatus(403);

        $this->postJson(route('admin.user.store'), [
            'name'               => 'X',
            'lastname'           => 'Y',
            'email'              => 'deny-student-' . uniqid('', true) . '@example.test',
            'role_id'            => $this->studentRoleId(),
            'is_enabled'         => 1,
            'send_welcome_email' => 1,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertStatus(403);
    }

    public function test_user_without_trainers_view_gets_403_on_trainers_welcome_create(): void
    {
        $actor = $this->createUserWithoutPermission('trainers.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);

        $this->get(route('admin.trainers.index'))->assertStatus(403);

        $this->postJson(route('admin.trainers.store'), [
            'name'               => 'X',
            'lastname'           => 'Y',
            'email'              => 'deny-trainer-' . uniqid('', true) . '@example.test',
            'is_enabled'         => 1,
            'send_welcome_email' => 1,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertStatus(403);
    }

    public function test_user_without_role_update_gets_403_on_administrators_welcome_create(): void
    {
        $actor = $this->createUserWithoutPermission('users.role.update', $this->partner);
        $this->actingAs($actor);
        $this->grantUsersView($actor);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);

        $this->get(route('admin.administrators.index'))->assertStatus(403);

        $this->postJson(route('admin.administrators.store'), [
            'name'               => 'X',
            'lastname'           => 'Y',
            'email'              => 'deny-admin-' . uniqid('', true) . '@example.test',
            'is_enabled'         => 1,
            'send_welcome_email' => 1,
        ], [
            'X-Requested-With' => 'XMLHttpRequest',
        ])->assertStatus(403);
    }

    public function test_authorized_user_gets_expected_statuses_on_send_welcome_create_endpoints(): void
    {
        $this->asAdmin();
        $this->grantUsersView($this->user);
        $this->grantTrainersView($this->user);
        $this->grantRoleUpdate($this->user);

        foreach ($this->sendWelcomeCreateRoutesPayload() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? [
                    'HTTP_ACCEPT'           => 'application/json',
                    'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
                ]
            );

            $this->assertSame(
                $item['expected'],
                $response->getStatusCode(),
                "С правами: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
            $this->assertNotSame(500, $response->getStatusCode());
        }
    }

    public function test_users_page_contains_send_welcome_email_checkbox_markers(): void
    {
        $this->asAdmin();
        $this->grantUsersView($this->user);

        $this->get(route('admin.user1'))
            ->assertOk()
            ->assertSee('id="create-send-welcome-email"', false)
            ->assertSee('Отправить письмо', false)
            ->assertSee('kids-tooltip-hint', false)
            ->assertSee('На указанный email уйдёт письмо с логином и паролем для входа в личный кабинет. Пароль сформируется автоматически.', false)
            ->assertSee('syncCreateWelcomeCredentialsUi', false)
            ->assertSee('name="send_welcome_email"', false);
    }

    public function test_trainers_page_contains_send_welcome_email_checkbox_markers(): void
    {
        $this->asAdmin();
        $this->grantTrainersView($this->user);

        $this->get(route('admin.trainers.index'))
            ->assertOk()
            ->assertSee('id="trainer-create-send-welcome-email"', false)
            ->assertSee('syncTrainerCreateWelcomeUi', false)
            ->assertSee('Отправить письмо', false)
            ->assertSee('kids-tooltip-hint', false)
            ->assertSee('На указанный email уйдёт письмо с логином и паролем для входа в личный кабинет. Пароль сформируется автоматически.', false);
    }

    public function test_administrators_page_contains_send_welcome_email_checkbox_markers(): void
    {
        $this->asAdmin();
        $this->grantStaffSectionAccess($this->user);

        $this->get(route('admin.administrators.index'))
            ->assertOk()
            ->assertSee('id="role-staff-create-send-welcome-email"', false)
            ->assertSee('syncRoleStaffCreateWelcomeUi', false)
            ->assertSee('Отправить письмо', false);
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>, expected: int}>
     */
    private function sendWelcomeCreateRoutesPayload(): array
    {
        $suffix = uniqid('', true);

        return [
            [
                'method'   => 'GET',
                'url'      => route('admin.user1'),
                'headers'  => ['HTTP_ACCEPT' => 'text/html'],
                'expected' => 200,
            ],
            [
                'method'   => 'GET',
                'url'      => route('admin.trainers.index'),
                'headers'  => ['HTTP_ACCEPT' => 'text/html'],
                'expected' => 200,
            ],
            [
                'method'   => 'GET',
                'url'      => route('admin.administrators.index'),
                'headers'  => ['HTTP_ACCEPT' => 'text/html'],
                'expected' => 200,
            ],
            [
                'method' => 'POST',
                'url'    => route('admin.user.store'),
                'data'   => [
                    'name'               => 'Access',
                    'lastname'           => 'Student',
                    'email'              => "access-student-{$suffix}@example.test",
                    'role_id'            => $this->studentRoleId(),
                    'is_enabled'         => 1,
                    'send_welcome_email' => 1,
                ],
                'headers' => [
                    'HTTP_ACCEPT'           => 'application/json',
                    'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
                ],
                'expected' => 200,
            ],
            [
                'method' => 'POST',
                'url'    => route('admin.trainers.store'),
                'data'   => [
                    'name'               => 'Access',
                    'lastname'           => 'Trainer',
                    'email'              => "access-trainer-{$suffix}@example.test",
                    'is_enabled'         => 1,
                    'send_welcome_email' => 1,
                ],
                'headers' => [
                    'HTTP_ACCEPT'           => 'application/json',
                    'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
                ],
                'expected' => 200,
            ],
            [
                'method' => 'POST',
                'url'    => route('admin.administrators.store'),
                'data'   => [
                    'name'               => 'Access',
                    'lastname'           => 'Admin',
                    'email'              => "access-admin-{$suffix}@example.test",
                    'is_enabled'         => 1,
                    'send_welcome_email' => 1,
                ],
                'headers' => [
                    'HTTP_ACCEPT'           => 'application/json',
                    'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
                ],
                'expected' => 200,
            ],
            [
                'method' => 'POST',
                'url'    => route('admin.user.store'),
                'data'   => [
                    'name'               => 'Fail',
                    'lastname'           => 'NoEmail',
                    'role_id'            => $this->studentRoleId(),
                    'is_enabled'         => 1,
                    'send_welcome_email' => 1,
                ],
                'headers' => [
                    'HTTP_ACCEPT'           => 'application/json',
                    'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
                ],
                'expected' => 422,
            ],
        ];
    }
}
