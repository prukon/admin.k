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
 * Доп. платежи: update/delete, AJAX-контракт, non-AJAX safety-net (JSON), контроль доступа.
 *
 * Store/update/delete отвечают JSON (в т.ч. без X-Requested-With) — см. docs setting-prices-custom-payments.
 *
 * @see CustomPaymentsTeamAccessFeatureTest
 * @see TeamControllerTest::test_store_non_ajax_redirects_and_creates_team
 */
final class CustomPaymentsCrudAccessFeatureTest extends CrmTestCase
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
            'title' => 'Группа CRUD доп. платежа',
            'deleted_at' => null,
        ]);

        $this->student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
            'is_enabled' => true,
            'lastname' => 'Студентов',
            'name' => 'Иван',
        ]);

        app(TeamUserSyncService::class)->attachTeamForStudent($this->student, (int) $this->team->id);
    }

    /**
     * @return array<string, string>
     */
    private function ajaxHeaders(): array
    {
        return [
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ];
    }

    private function grantCustomPaymentsView(User $actor): void
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

    private function grantManualPaidManage(User $actor): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $actor->role_id,
            'permission_id' => $this->permissionId('setPrices.manualPaid.manage'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function grantFullCustomPaymentsAccess(User $actor): void
    {
        $this->grantCustomPaymentsView($actor);
        $this->grantManualPaidManage($actor);
    }

    private function createPayment(array $overrides = []): UserCustomPayment
    {
        $fields = array_merge([
            'amount' => '500.00',
        ], $overrides);

        if (array_key_exists('amount', $fields)) {
            $fields['amount_cents'] = (int) round((float) $fields['amount'] * 100);
            unset($fields['amount']);
        }

        return UserCustomPayment::query()->create(array_merge([
            'partner_id' => $this->partner->id,
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'note' => 'Исходное описание',
            'is_paid' => false,
            'is_manual_paid' => null,
            'manual_paid_note' => null,
        ], $fields));
    }

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>}>
     */
    private function mutationEndpoints(UserCustomPayment $payment): array
    {
        return [
            [
                'method' => 'PUT',
                'url' => route('admin.settingPrices.customPayments.update', ['id' => $payment->id]),
                'data' => [
                    'amount' => 600,
                    'note' => 'Обновление',
                    'is_paid' => false,
                ],
            ],
            [
                'method' => 'DELETE',
                'url' => route('admin.settingPrices.customPayments.destroy', ['id' => $payment->id]),
            ],
            [
                'method' => 'POST',
                'url' => route('setting-prices.custom-payments.manual-paid', ['id' => $payment->id]),
                'data' => [
                    'mode' => 'paid',
                    'comment' => 'Ручная отметка оплаты',
                ],
            ],
        ];
    }

    public function test_guest_cannot_access_custom_payment_mutation_endpoints(): void
    {
        $payment = $this->createPayment();
        Auth::logout();

        foreach ($this->mutationEndpoints($payment) as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                ['HTTP_ACCEPT' => 'application/json']
            );

            $this->assertContains(
                $response->getStatusCode(),
                [302, 401, 403, 419],
                "Гость: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
            $this->assertNotSame(500, $response->getStatusCode());
        }
    }

    public function test_user_without_manual_paid_manage_gets_403_on_update_delete_manual_paid(): void
    {
        $actor = $this->createUserWithoutPermission('setPrices.manualPaid.manage', $this->partner);
        $this->grantCustomPaymentsView($actor);
        $this->actingAs($actor);

        $payment = $this->createPayment();

        foreach ($this->mutationEndpoints($payment) as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                array_merge(['HTTP_ACCEPT' => 'application/json'], $this->ajaxHeaders())
            );

            $this->assertSame(
                403,
                $response->getStatusCode(),
                "Без setPrices.manualPaid.manage: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_index_page_accessible_with_custom_payments_view(): void
    {
        $this->grantCustomPaymentsView($this->user);
        $this->actingAs($this->user);

        $this->get(route('admin.settingPrices.customPayments'))
            ->assertOk()
            ->assertViewIs('admin.SettingPrices.index')
            ->assertViewHas('activeTab', 'custom_payments')
            ->assertSee('Описание', false)
            ->assertDontSee('<th>Период</th>', false);
    }

    public function test_users_search_returns_only_system_role_user(): void
    {
        $this->grantCustomPaymentsView($this->user);
        $this->actingAs($this->user);

        $adminLike = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('admin'),
            'is_enabled' => true,
            'lastname' => 'Админов',
            'name' => 'Пётр',
        ]);

        $response = $this->getJson(route('admin.settingPrices.customPayments.users-search', [
            'q' => 'Студентов',
        ]));

        $response->assertOk()->assertJsonStructure(['results']);
        $ids = collect($response->json('results'))->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->assertContains((int) $this->student->id, $ids);
        $this->assertNotContains((int) $adminLike->id, $ids);

        $adminSearch = $this->getJson(route('admin.settingPrices.customPayments.users-search', [
            'q' => 'Админов',
        ]));
        $adminSearch->assertOk();
        $adminIds = collect($adminSearch->json('results'))->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->assertNotContains((int) $adminLike->id, $adminIds);
    }

    public function test_store_rejects_non_student_user_role(): void
    {
        $this->grantCustomPaymentsView($this->user);
        $this->actingAs($this->user);

        $trainer = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('trainer'),
            'is_enabled' => true,
        ]);
        app(TeamUserSyncService::class)->attachTeamForStudent($trainer, (int) $this->team->id);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('admin.settingPrices.customPayments.store'), [
                'user_id' => $trainer->id,
                'team_id' => $this->team->id,
                'amount' => 100,
                'note' => 'Не ученик',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);

        $this->assertDatabaseMissing('user_custom_payment', [
            'user_id' => $trainer->id,
        ]);
    }

    public function test_update_ajax_updates_amount_and_note_without_status_comment(): void
    {
        $this->grantFullCustomPaymentsAccess($this->user);
        $this->actingAs($this->user);

        $payment = $this->createPayment(['amount' => '500.00', 'note' => 'Было']);

        $this->withHeaders($this->ajaxHeaders())
            ->putJson(route('admin.settingPrices.customPayments.update', ['id' => $payment->id]), [
                'amount' => 750,
                'note' => 'Стало',
                'is_paid' => false,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'custom_payment' => ['id', 'amount', 'note', 'effective_is_paid']])
            ->assertJsonPath('custom_payment.id', (int) $payment->id);

        $payment->refresh();
        $this->assertSame(75000, (int) $payment->amount_cents);
        $this->assertSame('Стало', $payment->note);
        $this->assertFalse($payment->effective_is_paid);
        $this->assertNull($payment->is_manual_paid);
    }

    public function test_update_ajax_status_change_requires_status_comment(): void
    {
        $this->grantFullCustomPaymentsAccess($this->user);
        $this->actingAs($this->user);

        $payment = $this->createPayment();

        $this->withHeaders($this->ajaxHeaders())
            ->putJson(route('admin.settingPrices.customPayments.update', ['id' => $payment->id]), [
                'amount' => 500,
                'note' => 'Без комментария статуса',
                'is_paid' => true,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status_comment']);

        $this->assertFalse($payment->fresh()->effective_is_paid);
    }

    public function test_update_ajax_marks_paid_with_status_comment(): void
    {
        $this->grantFullCustomPaymentsAccess($this->user);
        $this->actingAs($this->user);

        $payment = $this->createPayment();

        $this->withHeaders($this->ajaxHeaders())
            ->putJson(route('admin.settingPrices.customPayments.update', ['id' => $payment->id]), [
                'amount' => 500,
                'note' => 'Описание',
                'is_paid' => true,
                'status_comment' => 'Оплатил наличными',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('custom_payment.effective_is_paid', true);

        $payment->refresh();
        $this->assertTrue($payment->effective_is_paid);
        $this->assertTrue((bool) $payment->is_manual_paid);
        $this->assertSame('Оплатил наличными', $payment->manual_paid_note);
        $this->assertSame((int) $this->user->id, (int) $payment->manual_paid_by);
    }

    public function test_update_ajax_prohibits_amount_change_when_paid(): void
    {
        $this->grantFullCustomPaymentsAccess($this->user);
        $this->actingAs($this->user);

        $payment = $this->createPayment([
            'amount' => '500.00',
            'is_manual_paid' => true,
            'manual_paid_note' => 'Уже оплачено',
            'manual_paid_by' => $this->user->id,
            'manual_paid_at' => now(),
        ]);

        $this->withHeaders($this->ajaxHeaders())
            ->putJson(route('admin.settingPrices.customPayments.update', ['id' => $payment->id]), [
                'amount' => 999,
                'note' => 'Попытка сменить сумму',
                'is_paid' => true,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);

        $this->assertSame(50000, (int) $payment->fresh()->amount_cents);
    }

    public function test_update_ajax_allows_note_when_paid_without_sending_amount(): void
    {
        $this->grantFullCustomPaymentsAccess($this->user);
        $this->actingAs($this->user);

        $payment = $this->createPayment([
            'amount' => '500.00',
            'note' => 'Старое',
            'is_manual_paid' => true,
            'manual_paid_note' => 'Оплачено',
            'manual_paid_by' => $this->user->id,
            'manual_paid_at' => now(),
        ]);

        $this->withHeaders($this->ajaxHeaders())
            ->putJson(route('admin.settingPrices.customPayments.update', ['id' => $payment->id]), [
                'note' => 'Новое описание',
                'is_paid' => true,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $payment->refresh();
        $this->assertSame(50000, (int) $payment->amount_cents);
        $this->assertSame('Новое описание', $payment->note);
    }

    public function test_update_non_ajax_returns_json_contract_and_persists_not_empty_200(): void
    {
        $this->grantFullCustomPaymentsAccess($this->user);
        $this->actingAs($this->user);

        $payment = $this->createPayment(['amount' => '100.00', 'note' => 'Non-AJAX before']);

        $response = $this->put(route('admin.settingPrices.customPayments.update', ['id' => $payment->id]), [
            'amount' => 222,
            'note' => 'Non-AJAX after',
            'is_paid' => false,
        ], ['Accept' => 'application/json']);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure(['success', 'custom_payment']);
        $this->assertNotSame('', (string) $response->getContent());

        $this->assertDatabaseHas('user_custom_payment', [
            'id' => $payment->id,
            'amount_cents' => 22200,
            'note' => 'Non-AJAX after',
        ]);
    }

    public function test_destroy_ajax_deletes_unpaid_payment(): void
    {
        $this->grantFullCustomPaymentsAccess($this->user);
        $this->actingAs($this->user);

        $payment = $this->createPayment();

        $this->withHeaders($this->ajaxHeaders())
            ->deleteJson(route('admin.settingPrices.customPayments.destroy', ['id' => $payment->id]))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('user_custom_payment', ['id' => $payment->id]);
    }

    public function test_destroy_ajax_rejects_paid_payment_with_422(): void
    {
        $this->grantFullCustomPaymentsAccess($this->user);
        $this->actingAs($this->user);

        $payment = $this->createPayment([
            'is_manual_paid' => true,
            'manual_paid_note' => 'Оплачено',
            'manual_paid_by' => $this->user->id,
            'manual_paid_at' => now(),
        ]);

        $this->withHeaders($this->ajaxHeaders())
            ->deleteJson(route('admin.settingPrices.customPayments.destroy', ['id' => $payment->id]))
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('user_custom_payment', ['id' => $payment->id]);
    }

    public function test_destroy_non_ajax_returns_json_and_deletes_unpaid_not_empty_200(): void
    {
        $this->grantFullCustomPaymentsAccess($this->user);
        $this->actingAs($this->user);

        $payment = $this->createPayment(['note' => 'На удаление non-ajax']);

        $response = $this->delete(
            route('admin.settingPrices.customPayments.destroy', ['id' => $payment->id]),
            [],
            ['Accept' => 'application/json']
        );

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertNotSame('', (string) $response->getContent());
        $this->assertDatabaseMissing('user_custom_payment', ['id' => $payment->id]);
    }

    public function test_destroy_foreign_partner_payment_returns_404(): void
    {
        $this->grantFullCustomPaymentsAccess($this->user);
        $this->actingAs($this->user);

        $foreignPayment = UserCustomPayment::query()->create([
            'partner_id' => $this->foreignPartner->id,
            'user_id' => $this->foreignUser->id,
            'team_id' => null,
            'amount_cents' => 1000,
            'is_paid' => false,
        ]);

        $this->withHeaders($this->ajaxHeaders())
            ->deleteJson(route('admin.settingPrices.customPayments.destroy', ['id' => $foreignPayment->id]))
            ->assertStatus(404);

        $this->assertDatabaseHas('user_custom_payment', ['id' => $foreignPayment->id]);
    }

    public function test_data_endpoint_exposes_status_without_period_requirement(): void
    {
        $this->grantCustomPaymentsView($this->user);
        $this->actingAs($this->user);

        $this->createPayment([
            'date_start' => null,
            'date_end' => null,
            'note' => 'Без периода',
            'amount' => '333.00',
        ]);

        $response = $this->getJson(route('admin.settingPrices.customPayments.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]));

        $response->assertOk();
        $row = collect($response->json('data'))->firstWhere('note', 'Без периода');
        $this->assertNotNull($row);
        $this->assertArrayHasKey('status_label', $row);
        $this->assertArrayHasKey('effective_is_paid', $row);
        $this->assertSame('Не оплачено', $row['status_label']);
    }
}
