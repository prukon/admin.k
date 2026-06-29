<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

use App\Models\Team;
use App\Models\User;
use App\Models\UserCustomPayment;
use App\Services\TeamUserSyncService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Доп. платежи: team_id, teams-for-user, store, контроль доступа.
 */
final class CustomPaymentsTeamAccessFeatureTest extends CrmTestCase
{
    private Team $team;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Группа доп. платежа',
        ]);

        $this->student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);

        app(TeamUserSyncService::class)->attachTeamForStudent($this->student, (int) $this->team->id);
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>, headers?: array<string, string>}>
     */
    private function teamAwareCustomPaymentEndpoints(): array
    {
        return [
            [
                'method' => 'GET',
                'url' => route('admin.settingPrices.customPayments.teams-for-user', [
                    'user_id' => $this->student->id,
                ]),
            ],
            [
                'method' => 'POST',
                'url' => route('admin.settingPrices.customPayments.store'),
                'data' => [
                    'user_id' => $this->student->id,
                    'team_id' => $this->team->id,
                    'amount' => 250,
                    'note' => 'Team access test',
                ],
            ],
        ];
    }

    private function grantCustomPaymentsAccess(User $actor): void
    {
        foreach (['setPrices.view', 'setPrices.customPayments.view'] as $permission) {
            DB::table('permission_role')->insertOrIgnore([
                'partner_id' => $this->partner->id,
                'role_id' => $actor->role_id,
                'permission_id' => $this->permissionId($permission),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * @return array<string, string>
     */
    private function ajaxHeaders(): array
    {
        return ['X-Requested-With' => 'XMLHttpRequest'];
    }

    public function test_guest_cannot_access_team_aware_custom_payment_endpoints(): void
    {
        Auth::logout();

        foreach ($this->teamAwareCustomPaymentEndpoints() as $item) {
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
            $this->assertNotSame(500, $response->getStatusCode());
        }
    }

    public function test_user_without_custom_payments_view_gets_403_on_team_endpoints(): void
    {
        $actor = $this->createUserWithoutPermission('setPrices.customPayments.view', $this->partner);
        $this->grantCustomPaymentsAccess($actor);
        DB::table('permission_role')
            ->where('partner_id', $this->partner->id)
            ->where('role_id', $actor->role_id)
            ->where('permission_id', $this->permissionId('setPrices.customPayments.view'))
            ->delete();

        $this->actingAs($actor);

        foreach ($this->teamAwareCustomPaymentEndpoints() as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                array_merge($item['headers'] ?? ['HTTP_ACCEPT' => 'application/json'], $this->ajaxHeaders())
            );

            $this->assertSame(
                403,
                $response->getStatusCode(),
                "Без setPrices.customPayments.view: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_teams_for_user_returns_student_teams_contract(): void
    {
        $this->grantCustomPaymentsAccess($this->user);
        $this->actingAs($this->user);

        $this->getJson(route('admin.settingPrices.customPayments.teams-for-user', [
            'user_id' => $this->student->id,
        ]))
            ->assertOk()
            ->assertJsonStructure(['results' => [['id', 'text']]])
            ->assertJsonPath('results.0.id', (int) $this->team->id)
            ->assertJsonPath('results.0.text', 'Группа доп. платежа');
    }

    public function test_teams_for_user_returns_empty_for_invalid_or_foreign_user(): void
    {
        $this->grantCustomPaymentsAccess($this->user);
        $this->actingAs($this->user);

        $this->getJson(route('admin.settingPrices.customPayments.teams-for-user'))
            ->assertOk()
            ->assertExactJson(['results' => []]);

        $this->getJson(route('admin.settingPrices.customPayments.teams-for-user', [
            'user_id' => $this->foreignUser->id,
        ]))
            ->assertOk()
            ->assertExactJson(['results' => []]);
    }

    public function test_store_custom_payment_ajax_creates_record_with_team_id(): void
    {
        $this->grantCustomPaymentsAccess($this->user);
        $this->actingAs($this->user);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('admin.settingPrices.customPayments.store'), [
                'user_id' => $this->student->id,
                'team_id' => $this->team->id,
                'amount' => 350,
                'note' => 'AJAX store',
            ])
            ->assertOk()
            ->assertJsonStructure(['success', 'custom_payment' => ['id', 'team_id', 'user_id', 'amount']])
            ->assertJsonPath('success', true)
            ->assertJsonPath('custom_payment.team_id', (int) $this->team->id);

        $this->assertDatabaseHas('user_custom_payment', [
            'partner_id' => $this->partner->id,
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'amount' => '350.00',
        ]);
    }

    public function test_store_custom_payment_non_ajax_returns_json_contract_not_empty_200(): void
    {
        $this->grantCustomPaymentsAccess($this->user);
        $this->actingAs($this->user);

        $response = $this->post(route('admin.settingPrices.customPayments.store'), [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'amount' => 400,
            'note' => 'Non-AJAX JSON store',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure(['success', 'custom_payment']);

        $this->assertDatabaseHas('user_custom_payment', [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'amount' => '400.00',
        ]);
    }

    public function test_store_custom_payment_validation_errors_on_team_id(): void
    {
        $this->grantCustomPaymentsAccess($this->user);
        $this->actingAs($this->user);

        $foreignTeam = Team::factory()->create(['partner_id' => $this->foreignPartner->id]);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('admin.settingPrices.customPayments.store'), [
                'user_id' => $this->student->id,
                'amount' => 100,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['team_id']);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('admin.settingPrices.customPayments.store'), [
                'user_id' => $this->student->id,
                'team_id' => $foreignTeam->id,
                'amount' => 100,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['team_id']);

        $this->assertSame(0, UserCustomPayment::query()->count());
    }
}
