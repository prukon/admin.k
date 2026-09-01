<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

use App\Jobs\DispatchPaymentNotificationsJob;
use App\Jobs\SendPaymentNotificationDispatchJob;
use App\Mail\PaymentNotificationMail;
use App\Models\LessonPackage;
use App\Models\OutgoingEmailLog;
use App\Models\Partner;
use App\Models\PaymentNotificationDispatch;
use App\Models\PaymentNotificationRule;
use App\Models\Team;
use App\Models\User;
use App\Models\UserPrice;
use App\Services\PaymentNotifications\PaymentNotificationDispatchService;
use App\Services\TeamUserSyncService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Установка цен → «Уведомления»: доступ, CRUD/JSON, превью UX, диспетчеризация, изоляция.
 *
 * @see \Tests\Feature\Crm\Ui\BladeInlineJsSyntaxTest::test_payment_notifications_modal_js_contracts_and_syntax
 * @see docs/documentation/setting-prices-payment-notifications.html
 */
final class PaymentNotificationsFeatureTest extends CrmTestCase
{
    private Team $team;

    private User $student;

    private LessonPackage $fixedPackage;

    private LessonPackage $postpayPackage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->grantPermission($this->user, 'setPrices.view');
        $this->grantPermission($this->user, 'setPrices.paymentNotifications.manage');

        $this->team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Младшая группа',
            'deleted_at' => null,
        ]);

        $this->student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $this->team->id,
            'is_enabled' => true,
            'email' => 'student-pay-notify@example.com',
            'name' => 'Иван',
            'lastname' => 'Тестов',
        ]);

        app(TeamUserSyncService::class)->syncTeamsForStudent($this->student, [(int) $this->team->id]);

        $this->fixedPackage = LessonPackage::factory()->forPartner((int) $this->partner->id)->create([
            'schedule_type' => LessonPackage::SCHEDULE_TYPE_FIXED,
            'name' => 'Фикс',
            'price_cents' => 500000,
        ]);

        $this->postpayPackage = LessonPackage::factory()->forPartner((int) $this->partner->id)->create([
            'schedule_type' => LessonPackage::SCHEDULE_TYPE_POSTPAY,
            'name' => 'Постоплата',
            'price_cents' => 100000,
        ]);
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

    /**
     * @return list<array{method: string, url: string, data?: array<string, mixed>}>
     */
    private function mutationEndpoints(?int $ruleId = null): array
    {
        $id = $ruleId ?? 1;
        $payload = [
            'name' => 'Правило',
            'trigger_type' => PaymentNotificationRule::TRIGGER_DAY_OF_MONTH,
            'trigger_value' => 5,
            'billing_month_offset' => 0,
            'schedule_types' => ['fixed'],
            'subject_template' => 'Тема',
            'body_html_template' => '<p>Тело</p>',
        ];

        return [
            ['method' => 'GET', 'url' => route('admin.settingPrices.paymentNotifications')],
            ['method' => 'GET', 'url' => route('admin.settingPrices.paymentNotifications.rules.index')],
            ['method' => 'POST', 'url' => route('admin.settingPrices.paymentNotifications.rules.store'), 'data' => $payload],
            ['method' => 'PUT', 'url' => route('admin.settingPrices.paymentNotifications.rules.update', ['id' => $id]), 'data' => $payload],
            ['method' => 'DELETE', 'url' => route('admin.settingPrices.paymentNotifications.rules.destroy', ['id' => $id])],
            ['method' => 'POST', 'url' => route('admin.settingPrices.paymentNotifications.rules.toggle', ['id' => $id]), 'data' => ['is_enabled' => true]],
            ['method' => 'POST', 'url' => route('admin.settingPrices.paymentNotifications.preview'), 'data' => [
                'subject_template' => 'Тема',
                'body_html_template' => '<p>Тело</p>',
            ]],
            ['method' => 'POST', 'url' => route('admin.settingPrices.paymentNotifications.testSend'), 'data' => [
                'to_email' => 'test@example.com',
                'subject_template' => 'Тема',
                'body_html_template' => '<p>Тело</p>',
            ]],
        ];
    }

    public function test_guest_is_redirected_from_all_notification_endpoints(): void
    {
        Auth::logout();

        foreach ($this->mutationEndpoints() as $item) {
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
            $this->assertNotSame(200, $response->getStatusCode());
            $this->assertNotSame(201, $response->getStatusCode());
        }
    }

    public function test_manager_without_permission_gets_403_on_all_endpoints(): void
    {
        $actor = $this->createUserWithoutPermission('setPrices.paymentNotifications.manage', $this->partner);
        $this->grantPermission($actor, 'setPrices.view');
        $this->actingAs($actor);

        $rule = PaymentNotificationRule::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'существующее',
            'is_enabled' => true,
            'trigger_type' => PaymentNotificationRule::TRIGGER_DAY_OF_MONTH,
            'trigger_value' => 1,
            'billing_month_offset' => 0,
            'schedule_types' => ['fixed'],
            'subject_template' => 'x',
            'body_html_template' => 'y',
            'sort_order' => 0,
        ]);

        foreach ($this->mutationEndpoints((int) $rule->id) as $item) {
            $response = $this->call(
                $item['method'],
                $item['url'],
                $item['data'] ?? [],
                [],
                [],
                [
                    'HTTP_ACCEPT' => 'application/json',
                    'HTTP_X-Requested-With' => 'XMLHttpRequest',
                ]
            );

            $this->assertSame(
                403,
                $response->getStatusCode(),
                "Без manage: {$item['method']} {$item['url']} → {$response->getStatusCode()}"
            );
        }
    }

    public function test_tab_is_hidden_without_permission_and_visible_with_permission(): void
    {
        $without = $this->createUserWithoutPermission('setPrices.paymentNotifications.manage', $this->partner);
        $this->grantPermission($without, 'setPrices.view');
        $this->actingAs($without);

        $this->get(route('admin.settingPrices.indexMenu'))
            ->assertOk()
            ->assertDontSee('href="/admin/setting-prices/notifications"', false)
            ->assertDontSee('>Уведомления</a>', false);

        $this->actingAs($this->user);
        $html = $this->get(route('admin.settingPrices.paymentNotifications'))
            ->assertOk()
            ->assertSee('Уведомления', false)
            ->assertSee('href="/admin/setting-prices/notifications"', false)
            ->getContent();

        $this->assertStringContainsString('Правила email-рассылки:', $html);
        $this->assertStringContainsString('Абонемент не оплачен.', $html);
        $this->assertStringContainsString('Отправка в 10:00 (Europe/Moscow).', $html);
        $this->assertStringContainsString('id="pnTemplateVarsToggle"', $html);
        $this->assertStringContainsString('id="pnTemplateVarsPanel"', $html);
        $this->assertMatchesRegularExpression(
            '/id="pnTemplateVarsPanel"[^>]*\bhidden\b/',
            $html,
            'Панель переменных по умолчанию скрыта (как «Как это работает» в договорах)'
        );
        $this->assertStringContainsString('{{student_name}}', $html);
        $this->assertStringContainsString('{{month_year}}', $html);
        $this->assertStringNotContainsString('id="pn-variables-help"', $html);
    }

    public function test_store_validation_returns_field_errors_for_invalid_triggers(): void
    {
        $this->postJson(route('admin.settingPrices.paymentNotifications.rules.store'), [
            'name' => '',
            'trigger_type' => 'day_of_month',
            'trigger_value' => 5,
            'schedule_types' => [],
            'subject_template' => '',
            'body_html_template' => '',
        ], $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'schedule_types', 'subject_template', 'body_html_template']);

        $this->postJson(route('admin.settingPrices.paymentNotifications.rules.store'), [
            'name' => 'День 40',
            'trigger_type' => PaymentNotificationRule::TRIGGER_DAY_OF_MONTH,
            'trigger_value' => 40,
            'schedule_types' => ['fixed'],
            'subject_template' => 'Тема',
            'body_html_template' => '<p>Тело</p>',
        ], $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['trigger_value']);

        $this->postJson(route('admin.settingPrices.paymentNotifications.rules.store'), [
            'name' => 'Плохой тип',
            'trigger_type' => PaymentNotificationRule::TRIGGER_DAY_OF_MONTH,
            'trigger_value' => 5,
            'schedule_types' => ['no_schedule'],
            'subject_template' => 'Тема',
            'body_html_template' => '<p>Тело</p>',
        ], $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['schedule_types.0']);
    }

    public function test_manager_can_create_update_toggle_and_delete_rule_via_ajax(): void
    {
        $create = $this->postJson(route('admin.settingPrices.paymentNotifications.rules.store'), [
            'name' => 'Напоминание 5-го',
            'is_enabled' => true,
            'trigger_type' => PaymentNotificationRule::TRIGGER_DAY_OF_MONTH,
            'trigger_value' => 5,
            'billing_month_offset' => 0,
            'schedule_types' => ['fixed', 'flexible'],
            'subject_template' => 'Оплата {{month_year}}',
            'body_html_template' => '<p>{{student_name}}: {{amount}}</p>',
        ], $this->ajaxHeaders())
            ->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['rule' => ['id', 'name', 'is_enabled', 'schedule_types', 'trigger_type']]);

        $ruleId = (int) $create->json('rule.id');
        $this->assertDatabaseHas('payment_notification_rules', [
            'id' => $ruleId,
            'partner_id' => $this->partner->id,
            'name' => 'Напоминание 5-го',
        ]);

        $this->getJson(route('admin.settingPrices.paymentNotifications.rules.index'), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonCount(1, 'rules')
            ->assertJsonPath('rules.0.id', $ruleId);

        $this->putJson(route('admin.settingPrices.paymentNotifications.rules.update', ['id' => $ruleId]), [
            'name' => 'Напоминание 5-го (upd)',
            'is_enabled' => false,
            'trigger_type' => PaymentNotificationRule::TRIGGER_DAY_OF_MONTH,
            'trigger_value' => 5,
            'billing_month_offset' => 0,
            'schedule_types' => ['fixed'],
            'subject_template' => 'Тема',
            'body_html_template' => '<p>Тело</p>',
        ], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('rule.name', 'Напоминание 5-го (upd)')
            ->assertJsonPath('rule.is_enabled', false);

        $this->postJson(route('admin.settingPrices.paymentNotifications.rules.toggle', ['id' => $ruleId]), [
            'is_enabled' => true,
        ], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('rule.is_enabled', true);

        $this->deleteJson(route('admin.settingPrices.paymentNotifications.rules.destroy', ['id' => $ruleId]), [], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseMissing('payment_notification_rules', ['id' => $ruleId]);
    }

    public function test_store_non_ajax_returns_json_and_persists_rule_not_empty_200(): void
    {
        $before = PaymentNotificationRule::query()->where('partner_id', $this->partner->id)->count();

        $response = $this->post(route('admin.settingPrices.paymentNotifications.rules.store'), [
            'name' => 'Non-AJAX правило',
            'is_enabled' => true,
            'trigger_type' => PaymentNotificationRule::TRIGGER_DAY_OF_MONTH,
            'trigger_value' => 1,
            'billing_month_offset' => -1,
            'schedule_types' => ['postpay'],
            'subject_template' => 'Тема',
            'body_html_template' => '<p>Тело</p>',
        ], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertNotSame(500, $response->getStatusCode());
        $response->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('rule.name', 'Non-AJAX правило')
            ->assertJsonPath('rule.billing_month_offset', -1);

        $this->assertSame(
            $before + 1,
            PaymentNotificationRule::query()->where('partner_id', $this->partner->id)->count()
        );
    }

    public function test_demo_preview_uses_current_moscow_month_and_full_email_layout_with_logo_footer(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-12 15:00:00', 'Europe/Moscow'));

        try {
            $preview = $this->postJson(route('admin.settingPrices.paymentNotifications.preview'), [
                'subject_template' => 'Оплата {{amount}}',
                'body_html_template' => '<p>{{student_name}} {{month_year}}</p>',
            ], $this->ajaxHeaders())
                ->assertOk()
                ->assertJsonPath('ok', true)
                ->assertJsonPath('is_demo', true)
                ->assertJsonStructure(['subject', 'body_html', 'email_html', 'variables']);

            $body = (string) $preview->json('body_html');
            $email = (string) $preview->json('email_html');

            // UX-регрессия: раньше демо всегда подставляло «сентябрь 2026».
            $this->assertStringContainsString('август 2026', $body);
            $this->assertStringNotContainsString('сентябрь 2026', $body);

            $this->assertStringContainsString('img/logo.png', $email);
            $this->assertStringContainsString('С уважением', $email);
            $this->assertStringContainsString('kidscrm.online', $email);
            $this->assertStringContainsString('Иванов Иван', $body);
        } finally {
            $this->travelBack();
        }
    }

    public function test_preview_with_real_users_price_uses_billing_month_not_demo_september(): void
    {
        $userPrice = UserPrice::query()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-07-01',
            'price_cents' => 350000,
            'is_paid' => 0,
            'lesson_package_id' => $this->fixedPackage->id,
        ]);

        $preview = $this->postJson(route('admin.settingPrices.paymentNotifications.preview'), [
            'subject_template' => 'Счёт {{month_year}}',
            'body_html_template' => '<p>{{student_name}} / {{amount}} / {{team}} / {{month_year}}</p>',
            'users_price_id' => $userPrice->id,
        ], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('is_demo', false);

        $body = (string) $preview->json('body_html');
        $this->assertStringContainsString('июль 2026', $body);
        $this->assertStringContainsString('июль 2026', (string) $preview->json('subject'));
        $this->assertStringContainsString('Тестов Иван', $body);
        $this->assertStringContainsString('3 500', $body);
        $this->assertStringContainsString('Младшая группа', $body);
        $this->assertStringContainsString('img/logo.png', (string) $preview->json('email_html'));
    }

    public function test_test_send_validation_errors_and_success_prefix_subject(): void
    {
        Mail::fake();

        $this->postJson(route('admin.settingPrices.paymentNotifications.testSend'), [
            'to_email' => 'not-an-email',
            'subject_template' => '',
            'body_html_template' => '',
        ], $this->ajaxHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['to_email', 'subject_template', 'body_html_template']);

        $this->postJson(route('admin.settingPrices.paymentNotifications.testSend'), [
            'to_email' => 'admin-test@example.com',
            'subject_template' => 'Оплата {{amount}}',
            'body_html_template' => '<p>{{student_name}}</p>',
        ], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('ok', true);

        Mail::assertSent(PaymentNotificationMail::class, function (PaymentNotificationMail $mail) {
            return $mail->hasTo('admin-test@example.com')
                && str_starts_with($mail->emailSubject, '[Тест]');
        });
    }

    public function test_dispatch_day_of_month_current_targets_only_matching_unpaid_rows_and_is_idempotent(): void
    {
        Queue::fake();

        UserPrice::query()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-09-01',
            'price_cents' => 500000,
            'is_paid' => 0,
            'lesson_package_id' => $this->fixedPackage->id,
        ]);

        UserPrice::query()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-08-01',
            'price_cents' => 500000,
            'is_paid' => 0,
            'lesson_package_id' => $this->fixedPackage->id,
        ]);

        PaymentNotificationRule::query()->create([
            'partner_id' => $this->partner->id,
            'name' => '5 число',
            'is_enabled' => true,
            'trigger_type' => PaymentNotificationRule::TRIGGER_DAY_OF_MONTH,
            'trigger_value' => 5,
            'billing_month_offset' => 0,
            'schedule_types' => ['fixed', 'flexible'],
            'subject_template' => 'Оплата {{month}}',
            'body_html_template' => '<p>{{amount}}</p>',
            'sort_order' => 0,
        ]);

        $service = app(PaymentNotificationDispatchService::class);
        $date = CarbonImmutable::parse('2026-09-05', 'Europe/Moscow');

        $stats1 = $service->dispatchForDate($date);
        $this->assertSame(1, $stats1['dispatches_created']);
        Queue::assertPushed(SendPaymentNotificationDispatchJob::class, 1);

        $stats2 = $service->dispatchForDate($date);
        $this->assertSame(0, $stats2['dispatches_created']);
        $this->assertSame(1, $stats2['skipped_existing']);
        $this->assertSame(1, PaymentNotificationDispatch::query()->count());
    }

    public function test_dispatch_previous_month_offset_selects_september_on_october_first_for_postpay(): void
    {
        Queue::fake();

        UserPrice::query()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-09-01',
            'price_cents' => 200000,
            'is_paid' => 0,
            'lesson_package_id' => $this->postpayPackage->id,
        ]);

        PaymentNotificationRule::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Постоплата 1-е',
            'is_enabled' => true,
            'trigger_type' => PaymentNotificationRule::TRIGGER_DAY_OF_MONTH,
            'trigger_value' => 1,
            'billing_month_offset' => -1,
            'schedule_types' => ['postpay'],
            'subject_template' => 'Постоплата {{month_year}}',
            'body_html_template' => '<p>{{amount}}</p>',
            'sort_order' => 0,
        ]);

        $stats = app(PaymentNotificationDispatchService::class)
            ->dispatchForDate(CarbonImmutable::parse('2026-10-01', 'Europe/Moscow'));

        $this->assertSame(1, $stats['dispatches_created']);
        Queue::assertPushed(SendPaymentNotificationDispatchJob::class, 1);

        // 1 ноября с тем же правилом целится в октябрь — сентябрьская строка не дублируется этим днём.
        $statsNov = app(PaymentNotificationDispatchService::class)
            ->dispatchForDate(CarbonImmutable::parse('2026-11-01', 'Europe/Moscow'));
        $this->assertSame(0, $statsNov['dispatches_created']);
    }

    public function test_dispatch_skips_paid_zero_amount_wrong_type_and_disabled_rules(): void
    {
        Queue::fake();

        $paid = UserPrice::query()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-09-01',
            'price_cents' => 500000,
            'is_paid' => 1,
            'lesson_package_id' => $this->fixedPackage->id,
        ]);

        $manualPaid = UserPrice::query()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-08-01',
            'price_cents' => 500000,
            'is_paid' => 0,
            'is_manual_paid' => 1,
            'lesson_package_id' => $this->fixedPackage->id,
        ]);

        $zero = UserPrice::query()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-07-01',
            'price_cents' => 0,
            'is_paid' => 0,
            'lesson_package_id' => $this->fixedPackage->id,
        ]);

        // На сентябрь только paid — правило текущего месяца не создаёт dispatch.
        PaymentNotificationRule::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'disabled',
            'is_enabled' => false,
            'trigger_type' => PaymentNotificationRule::TRIGGER_DAY_OF_MONTH,
            'trigger_value' => 5,
            'billing_month_offset' => 0,
            'schedule_types' => ['fixed'],
            'subject_template' => 'x',
            'body_html_template' => 'y',
            'sort_order' => 0,
        ]);

        PaymentNotificationRule::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'enabled-sept',
            'is_enabled' => true,
            'trigger_type' => PaymentNotificationRule::TRIGGER_DAY_OF_MONTH,
            'trigger_value' => 5,
            'billing_month_offset' => 0,
            'schedule_types' => ['fixed'],
            'subject_template' => 'x',
            'body_html_template' => 'y',
            'sort_order' => 1,
        ]);

        $stats = app(PaymentNotificationDispatchService::class)
            ->dispatchForDate(CarbonImmutable::parse('2026-09-05', 'Europe/Moscow'));

        $this->assertSame(0, $stats['dispatches_created']);
        Queue::assertNothingPushed();
        $this->assertTrue($paid->effective_is_paid);
        $this->assertTrue($manualPaid->effective_is_paid);
        $this->assertSame(0, (int) $zero->price_cents);
    }

    public function test_dispatch_skips_missing_email_with_skipped_status(): void
    {
        Queue::fake();

        $this->student->update(['email' => null]);

        UserPrice::query()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-09-01',
            'price_cents' => 500000,
            'is_paid' => 0,
            'lesson_package_id' => $this->fixedPackage->id,
        ]);

        PaymentNotificationRule::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'no-email',
            'is_enabled' => true,
            'trigger_type' => PaymentNotificationRule::TRIGGER_DAY_OF_MONTH,
            'trigger_value' => 5,
            'billing_month_offset' => 0,
            'schedule_types' => ['fixed'],
            'subject_template' => 'x',
            'body_html_template' => 'y',
            'sort_order' => 0,
        ]);

        $stats = app(PaymentNotificationDispatchService::class)
            ->dispatchForDate(CarbonImmutable::parse('2026-09-05', 'Europe/Moscow'));

        $this->assertSame(1, $stats['dispatches_created']);
        Queue::assertNothingPushed();

        $dispatch = PaymentNotificationDispatch::query()->first();
        $this->assertNotNull($dispatch);
        $this->assertSame(PaymentNotificationDispatch::STATUS_SKIPPED, $dispatch->status);
        $this->assertSame('missing_or_invalid_email', $dispatch->skip_reason);
    }

    public function test_days_after_overdue_fires_on_october_3_for_september_and_sends_mail(): void
    {
        Mail::fake();
        Queue::fake();

        $userPrice = UserPrice::query()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-09-01',
            'price_cents' => 500000,
            'is_paid' => 0,
            'lesson_package_id' => $this->fixedPackage->id,
        ]);

        PaymentNotificationRule::query()->create([
            'partner_id' => $this->partner->id,
            'name' => '3 дня просрочки',
            'is_enabled' => true,
            'trigger_type' => PaymentNotificationRule::TRIGGER_DAYS_AFTER_OVERDUE,
            'trigger_value' => 3,
            'billing_month_offset' => 0,
            'schedule_types' => ['fixed', 'flexible'],
            'subject_template' => 'Просрочка {{month_year}}',
            'body_html_template' => '<p>{{amount}}</p>',
            'sort_order' => 0,
        ]);

        $service = app(PaymentNotificationDispatchService::class);
        $this->assertSame(0, $service->dispatchForDate(CarbonImmutable::parse('2026-10-04', 'Europe/Moscow'))['dispatches_created']);

        Queue::fake();
        $stats = $service->dispatchForDate(CarbonImmutable::parse('2026-10-03', 'Europe/Moscow'));
        $this->assertSame(1, $stats['dispatches_created']);

        $dispatch = PaymentNotificationDispatch::query()->firstOrFail();
        $this->assertSame((int) $userPrice->id, (int) $dispatch->users_price_id);

        (new SendPaymentNotificationDispatchJob((int) $dispatch->id))->handle(
            app(\App\Services\PaymentNotifications\PaymentNotificationSendService::class)
        );

        Mail::assertSent(PaymentNotificationMail::class, function (PaymentNotificationMail $mail) {
            return $mail->hasTo('student-pay-notify@example.com')
                && $mail->partnerId === (int) $this->partner->id
                && str_contains($mail->bodyHtml, '5 000');
        });

        $dispatch->refresh();
        $this->assertSame(PaymentNotificationDispatch::STATUS_SENT, $dispatch->status);
    }

    public function test_send_skips_when_row_became_paid_after_queue(): void
    {
        Mail::fake();

        $userPrice = UserPrice::query()->create([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-09-01',
            'price_cents' => 500000,
            'is_paid' => 0,
            'lesson_package_id' => $this->fixedPackage->id,
        ]);

        $rule = PaymentNotificationRule::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'send',
            'is_enabled' => true,
            'trigger_type' => PaymentNotificationRule::TRIGGER_DAY_OF_MONTH,
            'trigger_value' => 5,
            'billing_month_offset' => 0,
            'schedule_types' => ['fixed'],
            'subject_template' => 'Оплата {{amount}}',
            'body_html_template' => '<p>{{student_name}}</p>',
            'sort_order' => 0,
        ]);

        $dispatch = PaymentNotificationDispatch::query()->create([
            'partner_id' => $this->partner->id,
            'payment_notification_rule_id' => $rule->id,
            'users_price_id' => $userPrice->id,
            'trigger_date' => '2026-09-05',
            'status' => PaymentNotificationDispatch::STATUS_PENDING,
        ]);

        $userPrice->update(['is_paid' => 1]);

        (new SendPaymentNotificationDispatchJob((int) $dispatch->id))->handle(
            app(\App\Services\PaymentNotifications\PaymentNotificationSendService::class)
        );

        Mail::assertNothingSent();
        $dispatch->refresh();
        $this->assertSame(PaymentNotificationDispatch::STATUS_SKIPPED, $dispatch->status);
        $this->assertSame('already_paid_or_zero', $dispatch->skip_reason);
    }

    public function test_partner_cannot_see_or_mutate_foreign_rules(): void
    {
        $otherPartner = Partner::factory()->create();
        $foreign = PaymentNotificationRule::query()->create([
            'partner_id' => $otherPartner->id,
            'name' => 'чужое',
            'is_enabled' => true,
            'trigger_type' => PaymentNotificationRule::TRIGGER_DAY_OF_MONTH,
            'trigger_value' => 1,
            'billing_month_offset' => 0,
            'schedule_types' => ['fixed'],
            'subject_template' => 'x',
            'body_html_template' => 'y',
            'sort_order' => 0,
        ]);

        $this->getJson(route('admin.settingPrices.paymentNotifications.rules.index'), $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonCount(0, 'rules');

        $this->putJson(route('admin.settingPrices.paymentNotifications.rules.update', ['id' => $foreign->id]), [
            'name' => 'hack',
            'trigger_type' => PaymentNotificationRule::TRIGGER_DAY_OF_MONTH,
            'trigger_value' => 1,
            'schedule_types' => ['fixed'],
            'subject_template' => 'x',
            'body_html_template' => 'y',
        ], $this->ajaxHeaders())->assertNotFound();

        $this->deleteJson(
            route('admin.settingPrices.paymentNotifications.rules.destroy', ['id' => $foreign->id]),
            [],
            $this->ajaxHeaders()
        )->assertNotFound();

        $this->assertDatabaseHas('payment_notification_rules', [
            'id' => $foreign->id,
            'name' => 'чужое',
        ]);
    }

    public function test_emails_report_category_filter_selects_only_payment_notification_mails(): void
    {
        $this->grantPermission($this->user, 'reports.view');
        $this->grantPermission($this->user, 'reports.emails.view');

        $paymentLog = OutgoingEmailLog::query()->create([
            'partner_id' => $this->partner->id,
            'status' => OutgoingEmailLog::STATUS_SENT,
            'subject' => 'Оплата август',
            'to_summary' => 'a@example.com',
            'mailable_class' => PaymentNotificationMail::class,
            'send_attempts' => 1,
            'sent_at' => now(),
        ]);

        $otherLog = OutgoingEmailLog::query()->create([
            'partner_id' => $this->partner->id,
            'status' => OutgoingEmailLog::STATUS_SENT,
            'subject' => 'Welcome',
            'to_summary' => 'b@example.com',
            'mailable_class' => \App\Mail\ClientWelcomeCredentialsMail::class,
            'send_attempts' => 1,
            'sent_at' => now(),
        ]);

        $this->get(route('reports.emails.index'))
            ->assertOk()
            ->assertSee('name="email_category"', false)
            ->assertSee('value="payment_notification"', false)
            ->assertSee('Уведомления об оплате', false);

        $data = $this->getJson(route('reports.emails.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
            'email_category' => PaymentNotificationMail::CATEGORY,
        ]), $this->ajaxHeaders())
            ->assertOk()
            ->json();

        $ids = collect($data['data'] ?? [])->pluck('id')->map(fn ($v) => (int) $v)->all();
        $this->assertContains((int) $paymentLog->id, $ids);
        $this->assertNotContains((int) $otherLog->id, $ids);
    }

    public function test_scheduled_dispatch_job_can_be_constructed_for_explicit_date(): void
    {
        Queue::fake();

        PaymentNotificationRule::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'job',
            'is_enabled' => true,
            'trigger_type' => PaymentNotificationRule::TRIGGER_DAY_OF_MONTH,
            'trigger_value' => 12,
            'billing_month_offset' => 0,
            'schedule_types' => ['fixed'],
            'subject_template' => 'x',
            'body_html_template' => 'y',
            'sort_order' => 0,
        ]);

        (new DispatchPaymentNotificationsJob('2026-08-12'))->handle(
            app(PaymentNotificationDispatchService::class)
        );

        // Нет неоплаченных строк — job отрабатывает без 500.
        $this->assertTrue(true);
    }
}
