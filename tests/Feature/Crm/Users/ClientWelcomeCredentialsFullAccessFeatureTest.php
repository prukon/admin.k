<?php

namespace Tests\Feature\Crm\Users;

use App\Models\Role;
use App\Models\SchoolLead;
use App\Models\Team;
use App\Models\User;
use App\Services\PartnerWidgetService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Контроль доступа и smoke endpoint'ов welcome-credentials:
 * resend из /admin/users и создание клиента из лида через admin.user.store.
 */
final class ClientWelcomeCredentialsFullAccessFeatureTest extends CrmTestCase
{
    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);

        app(PartnerWidgetService::class)->ensureForPartner((int) $this->partner->id);

        $this->student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $this->studentRoleId(),
            'email'      => 'welcome-access-' . uniqid('', true) . '@example.com',
        ]);
    }

    public function test_guest_is_denied_on_welcome_credentials_endpoints(): void
    {
        Auth::logout();

        foreach ($this->welcomeCredentialsRoutesPayload() as $item) {
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

    public function test_user_without_users_view_gets_403_on_welcome_credentials_endpoints(): void
    {
        $denied = $this->createUserWithoutPermission('users.view', $this->partner);
        $this->actingAs($denied);

        foreach ($this->welcomeCredentialsRoutesPayload() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']
            );

            $this->assertSame(
                403,
                $response->getStatusCode(),
                "Без users.view: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_user_with_users_view_welcome_credentials_routes_return_expected_status(): void
    {
        $this->asAdmin();
        $this->grantPermission($this->user, 'users.view');
        $this->grantPermission($this->user, 'schoolLeads.view');

        foreach ($this->welcomeCredentialsRoutesPayload() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                $item['headers'] ?? ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']
            );

            $this->assertSame(
                $item['expected'],
                $response->getStatusCode(),
                "С users.view: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_users_page_contains_send_welcome_credentials_button(): void
    {
        $this->asAdmin();
        $this->grantPermission($this->user, 'users.view');

        $this->get(route('admin.user1'))
            ->assertOk()
            ->assertSee('id="send-welcome-credentials-btn"', false)
            ->assertSee('Отправить новый пароль по почте', false);
    }

    public function test_school_leads_page_contains_create_client_workflow_markers(): void
    {
        $this->asAdmin();
        $this->grantPermission($this->user, 'schoolLeads.view');
        $this->grantPermission($this->user, 'users.view');

        $this->get(route('admin.school-leads'))
            ->assertOk()
            ->assertSee('id="createClientBtn"', false)
            ->assertSee('collectCreateClientPayload', false)
            ->assertSee('showCreateClientResultModal', false);
    }

    public function test_send_welcome_credentials_returns_404_for_foreign_partner_student(): void
    {
        $this->asAdmin();
        $this->grantPermission($this->user, 'users.view');

        $foreignStudent = User::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'role_id'    => $this->studentRoleId(),
            'email'      => 'foreign-' . uniqid('', true) . '@example.com',
        ]);

        $this->postJson(
            route('admin.user.send-welcome-credentials', $foreignStudent),
            [],
            ['X-Requested-With' => 'XMLHttpRequest']
        )->assertNotFound();
    }

    public function test_send_welcome_credentials_rejects_non_student_role(): void
    {
        $this->asAdmin();
        $this->grantPermission($this->user, 'users.view');

        $adminRoleId = (int) Role::query()->where('name', 'admin')->value('id');

        $staff = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id'    => $adminRoleId,
            'email'      => 'staff-' . uniqid('', true) . '@example.com',
        ]);

        $this->postJson(
            route('admin.user.send-welcome-credentials', $staff),
            [],
            ['X-Requested-With' => 'XMLHttpRequest']
        )
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Отправка доступна только для учеников.']);
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>, expected: int}>
     */
    private function welcomeCredentialsRoutesPayload(): array
    {
        $lead = SchoolLead::create([
            'partner_id'            => $this->partner->id,
            'name'                  => 'Access smoke',
            'phone'                 => '+7 900 121-21-21',
            'parent_email'          => $this->schoolLeadClientParentEmail(),
            'school_lead_status_id' => $this->schoolLeadSystemStatusId(),
        ]);

        $team = Team::factory()->create(['partner_id' => $this->partner->id]);

        return [
            [
                'method'   => 'GET',
                'url'      => route('admin.user1'),
                'headers'  => ['HTTP_ACCEPT' => 'text/html'],
                'expected' => 200,
            ],
            [
                'method'   => 'GET',
                'url'      => route('admin.school-leads'),
                'headers'  => ['HTTP_ACCEPT' => 'text/html'],
                'expected' => 200,
            ],
            [
                'method'   => 'POST',
                'url'      => route('admin.user.send-welcome-credentials', $this->student),
                'data'     => [],
                'headers'  => [
                    'HTTP_ACCEPT'           => 'application/json',
                    'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
                ],
                'expected' => 200,
            ],
            [
                'method'   => 'POST',
                'url'      => route('admin.user.store'),
                'data'     => [
                    'name'             => 'Из лида',
                    'lastname'         => 'Access',
                    'role_id'          => $this->studentRoleId(),
                    'team_ids'         => [$team->id],
                    'is_enabled'       => 1,
                    'school_lead_id'   => $lead->id,
                    'parent_email'     => $lead->parent_email,
                    'parent_lastname'  => 'Родитель',
                    'parent_firstname' => 'Тест',
                ],
                'headers'  => [
                    'HTTP_ACCEPT'           => 'application/json',
                    'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
                ],
                'expected' => 200,
            ],
        ];
    }

    private function studentRoleId(): int
    {
        return (int) Role::query()->where('name', 'user')->value('id');
    }

    private function grantPermission(User $actor, string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id'    => $this->partner->id,
            'role_id'       => $actor->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }
}
