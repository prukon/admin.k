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
 * UX: setPrices.manualPaid.manage скрывает только селект статуса в модалке редактирования,
 * а не кнопку «Редактировать». Update/delete — customPayments.view.
 *
 * @see CustomPaymentsCrudAccessFeatureTest
 * @see docs/documentation/setting-prices-custom-payments.html
 */
final class CustomPaymentsEditPermissionFeatureTest extends CrmTestCase
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
            'title' => 'Группа права редактирования',
            'deleted_at' => null,
        ]);

        $this->student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
            'is_enabled' => true,
            'lastname' => 'Учеников',
            'name' => 'Пётр',
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
    private function pageAndMutationEndpoints(UserCustomPayment $payment): array
    {
        return [
            [
                'method' => 'GET',
                'url' => route('admin.settingPrices.customPayments'),
            ],
            [
                'method' => 'GET',
                'url' => route('admin.settingPrices.customPayments.data', [
                    'draw' => 1,
                    'start' => 0,
                    'length' => 10,
                ]),
            ],
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

    public function test_guest_is_rejected_on_page_and_edit_endpoints_without_500_or_empty_200(): void
    {
        $payment = $this->createPayment();
        Auth::logout();

        foreach ($this->pageAndMutationEndpoints($payment) as $item) {
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
            if ($response->getStatusCode() === 200) {
                $this->assertNotSame('', (string) $response->getContent());
            }
        }
    }

    public function test_manager_without_custom_payments_view_gets_403_on_page_update_and_delete(): void
    {
        $actor = $this->createUserWithoutPermission('setPrices.customPayments.view', $this->partner);
        $this->grantPermission($actor, 'setPrices.view');
        $this->actingAs($actor);

        $payment = $this->createPayment();

        $this->get(route('admin.settingPrices.customPayments'))->assertForbidden();

        $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('admin.settingPrices.customPayments.data', [
                'draw' => 1,
                'start' => 0,
                'length' => 10,
            ]))
            ->assertForbidden();

        $this->withHeaders($this->ajaxHeaders())
            ->putJson(route('admin.settingPrices.customPayments.update', ['id' => $payment->id]), [
                'amount' => 600,
                'note' => 'Без view',
                'is_paid' => false,
            ])
            ->assertForbidden();

        $this->withHeaders($this->ajaxHeaders())
            ->deleteJson(route('admin.settingPrices.customPayments.destroy', ['id' => $payment->id]))
            ->assertForbidden();

        $this->assertDatabaseHas('user_custom_payment', ['id' => $payment->id]);
    }

    public function test_tab_is_hidden_without_custom_payments_view_and_shown_with_it(): void
    {
        $this->grantPermission($this->user, 'setPrices.view');
        $this->actingAs($this->user);

        $this->get(route('admin.settingPrices.indexMenu'))
            ->assertOk()
            ->assertDontSee('/admin/setting-prices/custom-payments', false);

        $this->grantCustomPaymentsView($this->user);

        $this->get(route('admin.settingPrices.indexMenu'))
            ->assertOk()
            ->assertSee('/admin/setting-prices/custom-payments', false)
            ->assertSee('Дополнительные платежи', false);
    }

    public function test_edit_modal_hides_status_select_without_manual_paid_and_keeps_amount_note_delete(): void
    {
        $this->grantCustomPaymentsView($this->user);
        $this->actingAs($this->user);

        $html = $this->get(route('admin.settingPrices.customPayments'))
            ->assertOk()
            ->assertSee('id="customPaymentEditModal"', false)
            ->assertSee('id="custom-payment-edit-form"', false)
            ->assertSee('id="custom-payment-edit-amount"', false)
            ->assertSee('id="custom-payment-edit-note"', false)
            ->assertSee('id="custom-payment-edit-delete"', false)
            ->assertSee('window.__customPaymentsCanManualPaid = false', false)
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/id="custom-payment-edit-is-paid-wrap"[^>]*style="display:none;"/',
            $html
        );

        $amountPos = strpos($html, 'id="custom-payment-edit-amount"');
        $paidWrapPos = strpos($html, 'id="custom-payment-edit-is-paid-wrap"');
        $commentPos = strpos($html, 'id="custom-payment-edit-status-comment-wrap"');
        $notePos = strpos($html, 'id="custom-payment-edit-note"');
        $this->assertNotFalse($amountPos);
        $this->assertNotFalse($paidWrapPos);
        $this->assertNotFalse($commentPos);
        $this->assertNotFalse($notePos);
        $this->assertLessThan($paidWrapPos, $amountPos);
        $this->assertLessThan($commentPos, $paidWrapPos);
        $this->assertLessThan($notePos, $commentPos);
    }

    public function test_edit_modal_shows_status_select_when_manager_can_mark_paid(): void
    {
        $this->grantCustomPaymentsView($this->user);
        $this->grantManualPaidManage($this->user);
        $this->actingAs($this->user);

        $html = $this->get(route('admin.settingPrices.customPayments'))
            ->assertOk()
            ->assertSee('window.__customPaymentsCanManualPaid = true', false)
            ->getContent();

        $this->assertDoesNotMatchRegularExpression(
            '/id="custom-payment-edit-is-paid-wrap"[^>]*style="display:none;"/',
            $html
        );
        $this->assertStringContainsString('id="custom-payment-edit-is-paid-wrap"', $html);
        $this->assertStringContainsString('for="custom-payment-edit-is-paid">Статус оплаты</label>', $html);
    }

    public function test_manager_without_manual_paid_can_save_amount_and_note_keeping_unpaid_status(): void
    {
        $actor = $this->createUserWithoutPermission('setPrices.manualPaid.manage', $this->partner);
        $this->grantCustomPaymentsView($actor);
        $this->actingAs($actor);

        $payment = $this->createPayment(['amount' => '400.00', 'note' => 'Было']);

        $this->withHeaders($this->ajaxHeaders())
            ->putJson(route('admin.settingPrices.customPayments.update', ['id' => $payment->id]), [
                'amount' => 650.5,
                'note' => 'Стало',
                'is_paid' => false,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('custom_payment.effective_is_paid', false)
            ->assertJsonStructure(['success', 'custom_payment' => ['id', 'amount', 'note', 'effective_is_paid']]);

        $payment->refresh();
        $this->assertSame(65050, (int) $payment->amount_cents);
        $this->assertSame('Стало', $payment->note);
        $this->assertFalse($payment->effective_is_paid);
        $this->assertNull($payment->is_manual_paid);
    }

    public function test_manager_without_manual_paid_gets_403_with_is_paid_field_error_when_marking_paid(): void
    {
        $actor = $this->createUserWithoutPermission('setPrices.manualPaid.manage', $this->partner);
        $this->grantCustomPaymentsView($actor);
        $this->actingAs($actor);

        $payment = $this->createPayment();

        $this->withHeaders($this->ajaxHeaders())
            ->putJson(route('admin.settingPrices.customPayments.update', ['id' => $payment->id]), [
                'amount' => 500,
                'note' => 'Попытка оплатить',
                'is_paid' => true,
                'status_comment' => 'Оплатил наличными',
            ])
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['is_paid'])
            ->assertJsonPath('errors.is_paid.0', 'Нет права менять статус оплаты дополнительного платежа.');

        $this->assertFalse($payment->fresh()->effective_is_paid);
        $this->assertNull($payment->fresh()->is_manual_paid);
        $this->assertSame(50000, (int) $payment->fresh()->amount_cents);
    }

    public function test_manager_without_manual_paid_gets_403_when_unmarking_already_paid_payment(): void
    {
        $actor = $this->createUserWithoutPermission('setPrices.manualPaid.manage', $this->partner);
        $this->grantCustomPaymentsView($actor);
        $this->actingAs($actor);

        $payment = $this->createPayment([
            'is_manual_paid' => true,
            'manual_paid_note' => 'Уже оплачено',
            'manual_paid_by' => $this->user->id,
            'manual_paid_at' => now(),
        ]);

        $this->withHeaders($this->ajaxHeaders())
            ->putJson(route('admin.settingPrices.customPayments.update', ['id' => $payment->id]), [
                'note' => 'Снять оплату',
                'is_paid' => false,
                'status_comment' => 'Ошибочно отметили',
            ])
            ->assertForbidden()
            ->assertJsonValidationErrors(['is_paid']);

        $this->assertTrue($payment->fresh()->effective_is_paid);
    }

    public function test_manager_with_manual_paid_can_mark_paid_and_unmark_with_comment(): void
    {
        $this->grantCustomPaymentsView($this->user);
        $this->grantManualPaidManage($this->user);
        $this->actingAs($this->user);

        $payment = $this->createPayment();

        $this->withHeaders($this->ajaxHeaders())
            ->putJson(route('admin.settingPrices.customPayments.update', ['id' => $payment->id]), [
                'amount' => 500,
                'note' => 'Оплачен вручную',
                'is_paid' => true,
                'status_comment' => 'Оплатил наличными',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('custom_payment.effective_is_paid', true);

        $payment->refresh();
        $this->assertTrue($payment->effective_is_paid);
        $this->assertSame('Оплатил наличными', $payment->manual_paid_note);

        $this->withHeaders($this->ajaxHeaders())
            ->putJson(route('admin.settingPrices.customPayments.update', ['id' => $payment->id]), [
                'note' => 'Оплачен вручную',
                'is_paid' => false,
                'status_comment' => 'Вернули в неоплаченные',
            ])
            ->assertOk()
            ->assertJsonPath('custom_payment.effective_is_paid', false);

        $payment->refresh();
        $this->assertFalse($payment->effective_is_paid);
        $this->assertFalse((bool) $payment->is_manual_paid);
        $this->assertSame('Вернули в неоплаченные', $payment->manual_paid_note);
    }

    public function test_update_validation_puts_errors_under_amount_and_status_comment(): void
    {
        $this->grantCustomPaymentsView($this->user);
        $this->grantManualPaidManage($this->user);
        $this->actingAs($this->user);

        $payment = $this->createPayment();

        $this->withHeaders($this->ajaxHeaders())
            ->putJson(route('admin.settingPrices.customPayments.update', ['id' => $payment->id]), [
                'note' => 'Без суммы',
                'is_paid' => false,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);

        $this->withHeaders($this->ajaxHeaders())
            ->putJson(route('admin.settingPrices.customPayments.update', ['id' => $payment->id]), [
                'amount' => 500,
                'note' => 'Без комментария',
                'is_paid' => true,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status_comment']);

        $this->assertFalse($payment->fresh()->effective_is_paid);
        $this->assertSame(50000, (int) $payment->fresh()->amount_cents);
    }

    public function test_manager_without_manual_paid_can_delete_unpaid_but_not_paid_payment(): void
    {
        $actor = $this->createUserWithoutPermission('setPrices.manualPaid.manage', $this->partner);
        $this->grantCustomPaymentsView($actor);
        $this->actingAs($actor);

        $unpaid = $this->createPayment(['note' => 'На удаление']);
        $paid = $this->createPayment([
            'note' => 'Оплаченный',
            'is_manual_paid' => true,
            'manual_paid_note' => 'Оплачено',
            'manual_paid_by' => $this->user->id,
            'manual_paid_at' => now(),
        ]);

        $this->withHeaders($this->ajaxHeaders())
            ->deleteJson(route('admin.settingPrices.customPayments.destroy', ['id' => $unpaid->id]))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('user_custom_payment', ['id' => $unpaid->id]);

        $this->withHeaders($this->ajaxHeaders())
            ->deleteJson(route('admin.settingPrices.customPayments.destroy', ['id' => $paid->id]))
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['custom_payment']);

        $this->assertDatabaseHas('user_custom_payment', ['id' => $paid->id]);
    }

    public function test_non_ajax_update_without_manual_paid_persists_note_as_json_not_empty_200(): void
    {
        $actor = $this->createUserWithoutPermission('setPrices.manualPaid.manage', $this->partner);
        $this->grantCustomPaymentsView($actor);
        $this->actingAs($actor);

        $payment = $this->createPayment(['note' => 'Non-AJAX before']);

        $response = $this->put(route('admin.settingPrices.customPayments.update', ['id' => $payment->id]), [
            'amount' => 501,
            'note' => 'Non-AJAX after',
            'is_paid' => false,
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertNotSame('', (string) $response->getContent());
        $this->assertDatabaseHas('user_custom_payment', [
            'id' => $payment->id,
            'amount_cents' => 50100,
            'note' => 'Non-AJAX after',
        ]);
    }

    public function test_non_ajax_status_change_without_manual_paid_returns_json_403_not_empty_200(): void
    {
        $actor = $this->createUserWithoutPermission('setPrices.manualPaid.manage', $this->partner);
        $this->grantCustomPaymentsView($actor);
        $this->actingAs($actor);

        $payment = $this->createPayment();

        $response = $this->put(route('admin.settingPrices.customPayments.update', ['id' => $payment->id]), [
            'amount' => 500,
            'note' => 'Non-AJAX статус',
            'is_paid' => true,
            'status_comment' => 'Оплатил наличными',
        ]);

        $response->assertForbidden();
        $response->assertJsonValidationErrors(['is_paid']);
        $this->assertNotSame('', (string) $response->getContent());
        $this->assertFalse($payment->fresh()->effective_is_paid);
    }

    public function test_update_of_foreign_partner_payment_shows_id_error_and_does_not_change_record(): void
    {
        $this->grantCustomPaymentsView($this->user);
        $this->actingAs($this->user);

        $foreign = UserCustomPayment::query()->create([
            'partner_id' => $this->foreignPartner->id,
            'user_id' => $this->foreignUser->id,
            'team_id' => null,
            'amount_cents' => 1000,
            'is_paid' => false,
        ]);

        $this->withHeaders($this->ajaxHeaders())
            ->putJson(route('admin.settingPrices.customPayments.update', ['id' => $foreign->id]), [
                'amount' => 20,
                'note' => 'Чужой',
                'is_paid' => false,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['id']);

        $this->assertDatabaseHas('user_custom_payment', [
            'id' => $foreign->id,
            'amount_cents' => 1000,
        ]);
    }

    /**
     * UX-баг: кнопка «Редактировать» пряталась вместе с селектом статуса.
     * Оба JS-пути (Vite-источник и public/js, который грузит страница).
     */
    public function test_edit_button_is_shown_without_manual_paid_and_status_select_is_gated_in_both_js_paths(): void
    {
        $paths = [
            resource_path('js/setting-prices-custom-payments.js'),
            public_path('js/setting-prices-custom-payments.js'),
        ];

        $vite = (string) file_get_contents($paths[0]);
        $public = (string) file_get_contents($paths[1]);
        $this->assertSame(
            $vite,
            $public,
            'public/js/setting-prices-custom-payments.js должен совпадать с resources/js (страница грузит public/js)'
        );

        foreach ($paths as $path) {
            $js = (string) file_get_contents($path);
            $this->assertCustomPaymentsEditJsContract($js, $path);
        }
    }

    private function grantPermission(User $actor, string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $actor->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function assertCustomPaymentsEditJsContract(string $js, string $path): void
    {
        $actionsStart = strpos($js, "key: 'actions'");
        $this->assertNotFalse($actionsStart, $path);
        $actionsBlock = substr($js, $actionsStart, 700);
        $this->assertStringContainsString('data-custom-payment-action="edit"', $actionsBlock, $path);
        $this->assertStringContainsString('Редактировать', $actionsBlock, $path);
        $this->assertStringNotContainsString(
            '__customPaymentsCanManualPaid',
            $actionsBlock,
            $path.': колонка «Действия» не должна прятать кнопку по manualPaid.manage'
        );
        $this->assertStringNotContainsString(
            "type !== 'display' || !window.__customPaymentsCanManualPaid",
            $js,
            $path
        );

        $openStart = strpos($js, 'function openEditModal(');
        $this->assertNotFalse($openStart, $path);
        $domStart = strpos($js, "document.addEventListener('DOMContentLoaded'");
        $this->assertNotFalse($domStart, $path);
        $openBody = substr($js, $openStart, $domStart - $openStart);
        $this->assertStringContainsString("paidWrap.style.display = canManual ? '' : 'none'", $openBody, $path);
        $this->assertStringContainsString("deleteBtn.style.display = paid ? 'none' : ''", $openBody, $path);
        $this->assertStringContainsString('amountEl.disabled = paid', $openBody, $path);
        $this->assertStringContainsString('custom-payment-edit-is-paid-wrap', $openBody, $path);

        $submitStart = strpos($js, "getElementById('custom-payment-edit-form')");
        $this->assertNotFalse($submitStart, $path);
        $submitBody = substr($js, $submitStart, 1800);
        $this->assertStringContainsString('e.preventDefault()', $submitBody, $path);
        $this->assertStringContainsString('method: \'PUT\'', $submitBody, $path);
        $this->assertStringContainsString("Accept': 'application/json'", $submitBody, $path);
        $this->assertStringContainsString('setEditFieldErrors', $submitBody, $path);
        $this->assertStringContainsString('var isPaid = canManual', $submitBody, $path);
        $this->assertStringContainsString(': initialPaid;', $submitBody, $path);
        $this->assertStringContainsString('if (canManual && isPaid !== initialPaid)', $submitBody, $path);
        $this->assertStringContainsString('dtApi.reload({ keepPage: true })', $js, $path);
    }
}
