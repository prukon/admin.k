<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Money;

use App\Models\LessonPackage;
use App\Models\Team;
use App\Models\User;
use App\Models\UserCustomPayment;
use App\Models\UserPrice;
use App\Models\Weekday;
use App\Services\TeamUserSyncService;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * [P2] HTTP-smoke money/*_cents: страница → модалка/форма → AJAX submit →
 * данные видны в следующем GET/JSON без «белого экрана» и без пустого 200.
 *
 * @see LessonPackageAssignmentsHistoryWorkflowFeatureTest
 * @see ScheduleJournalMonthlyUlpContractsFeatureTest::test_monthly_place_workflow_page_modal_submit_visible_without_reload
 * @see docs/documentation/money.html
 */
final class MoneyCentsWorkflowFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->asAdmin();
        $this->withoutVite();
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

    private function grantPermission(string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * [P2] Группы: страница → модалка create (month_price step=0.01) →
     * AJAX store с копейками → /data показывает сумму без reload.
     */
    public function test_teams_month_price_workflow_page_modal_submit_visible_in_data_without_reload(): void
    {
        $unique = 'MoneyWF-'.uniqid('', true);

        $page = $this->get(route('admin.team.index'));
        $page->assertOk();
        $this->assertNotSame('', trim((string) $page->getContent()));
        $page->assertSee('id="month_price"', false)
            ->assertSee('step="0.01"', false)
            ->assertSee('id="edit-month_price"', false)
            ->assertSee('Стоимость по умолчанию', false)
            ->assertSee('data-column-key="month_price"', false);

        $this->postJson(route('admin.team.store'), [
            'title' => $unique,
            'default_duration_minutes' => 60,
            'month_price' => '1999.50',
            'order_by' => 10,
            'is_enabled' => 1,
            'weekdays' => Weekday::query()->limit(1)->pluck('id')->all(),
        ], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonFragment(['message' => 'Группа создана успешно']);

        $teamId = (int) Team::query()
            ->where('partner_id', $this->partner->id)
            ->where('title', $unique)
            ->where('month_price_cents', 199950)
            ->value('id');
        $this->assertGreaterThan(0, $teamId);

        $data = $this->getJson(route('admin.team.data').'?search[value]='.urlencode($unique))
            ->assertOk()
            ->json();

        $this->assertNotSame('', trim(json_encode($data, JSON_UNESCAPED_UNICODE)));
        $row = collect($data['data'] ?? [])->firstWhere('id', $teamId);
        $this->assertNotNull($row, 'После store строка группы должна быть в /data без F5.');
        $this->assertEqualsWithDelta(1999.50, (float) ($row['month_price'] ?? 0), 0.001);

        $edit = $this->getJson(route('admin.team.edit', ['id' => $teamId]))
            ->assertOk()
            ->json();
        $this->assertEqualsWithDelta(1999.50, (float) ($edit['month_price'] ?? 0), 0.001);
    }

    /**
     * [P2] Доп. платежи: страница → маркеры модалки → AJAX store с копейками →
     * data endpoint отдаёт amount в рублях без пустого ответа.
     */
    public function test_custom_payments_workflow_page_modal_submit_visible_in_data_without_reload(): void
    {
        $this->grantPermission('setPrices.customPayments.view');

        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'WF custom money',
            'deleted_at' => null,
        ]);
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
            'is_enabled' => true,
            'lastname' => 'Workflow',
            'name' => 'Копейки'.uniqid(),
        ]);
        app(TeamUserSyncService::class)->attachTeamForStudent($student, (int) $team->id);

        $page = $this->get(route('admin.settingPrices.customPayments'));
        $page->assertOk();
        $this->assertNotSame('', trim((string) $page->getContent()));
        $page->assertSee('id="custom-payment-amount"', false)
            ->assertSee('step="0.01"', false)
            ->assertSee('id="custom-payment-edit-amount"', false);

        $store = $this->postJson(route('admin.settingPrices.customPayments.store'), [
            'user_id' => $student->id,
            'team_id' => $team->id,
            'amount' => '777.77',
            'note' => 'WF копейки '.$student->name,
        ], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('custom_payment.amount', 777.77);

        $paymentId = (int) $store->json('custom_payment.id');
        $this->assertGreaterThan(0, $paymentId);
        $this->assertDatabaseHas('user_custom_payment', [
            'id' => $paymentId,
            'amount_cents' => 77777,
        ]);

        // Ответ store уже содержит entity — UI может обновить таблицу без F5.
        $this->assertNotSame('', trim((string) $store->getContent()));

        $data = $this->getJson(route('admin.settingPrices.customPayments.data'), $this->ajaxHeaders())
            ->assertOk()
            ->json();
        $this->assertNotSame('', trim(json_encode($data, JSON_UNESCAPED_UNICODE)));

        $row = collect($data['data'] ?? [])->firstWhere('id', $paymentId);
        $this->assertNotNull($row, 'После store запись должна быть в custom-payments/data без F5.');
        $this->assertEqualsWithDelta(777.77, (float) ($row['amount'] ?? 0), 0.001);
    }

    /**
     * [P2] Установка цен (по месяцам): страница → AJAX apply с копейками →
     * getTeamPrice показывает price в рублях (данные для правой колонки без reload).
     */
    public function test_setting_prices_monthly_workflow_apply_kopecks_visible_in_get_team_price_without_reload(): void
    {
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'WF monthly money',
            'deleted_at' => null,
        ]);
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
            'is_enabled' => true,
            'lastname' => 'Месяц',
            'name' => 'Копейки',
        ]);
        app(TeamUserSyncService::class)->attachTeamForStudent($student, (int) $team->id);

        UserPrice::query()->create([
            'user_id' => $student->id,
            'team_id' => $team->id,
            'new_month' => '2025-11-01',
            'price_cents' => 0,
            'is_paid' => false,
        ]);

        $package = LessonPackage::factory()->forPartner((int) $this->partner->id)->fixed(4, 30)->create([
            'price_cents' => 123456,
            'is_active' => true,
            'name' => 'WF pkg kopecks',
        ]);

        $page = $this->get(route('admin.settingPrices.indexMenu'));
        $page->assertOk();
        $this->assertNotSame('', trim((string) $page->getContent()));
        $page->assertSee('setting-price-wrap', false)
            ->assertSee('single-select-date', false)
            ->assertSee($team->title, false)
            ->assertSee('setting-prices-team-package-select', false);

        $apply = $this->postJson(route('setPriceAllUsers'), [
            'selectedDate' => 'Ноябрь 2025',
            'teamId' => $team->id,
            'usersPrice' => [[
                'user_id' => $student->id,
                'price' => 1234.56,
                'lesson_package_id' => $package->id,
                'user' => ['name' => $student->name],
            ]],
        ], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('success', true);
        $this->assertNotSame('', trim((string) $apply->getContent()));

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $student->id,
            'team_id' => $team->id,
            'new_month' => '2025-11-01',
            'price_cents' => 123456,
        ]);

        $refresh = $this->postJson(route('getTeamPrice'), [
            'selectedDate' => 'Ноябрь 2025',
            'teamId' => $team->id,
        ], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('success', true)
            ->json();

        $this->assertNotSame('', trim(json_encode($refresh, JSON_UNESCAPED_UNICODE)));
        $found = collect($refresh['usersPrice'] ?? [])->first(
            fn ($u) => (int) ($u['user_id'] ?? 0) === (int) $student->id
        );
        $this->assertNotNull($found, 'getTeamPrice после apply должен отдать строку ученика без F5.');
        $this->assertEqualsWithDelta(1234.56, (float) ($found['price'] ?? 0), 0.001);
    }

    /**
     * [P2] Абонементы: страница шаблонов → store цены с копейками →
     * packages/data (или повторный GET страницы) без пустого ответа.
     */
    public function test_lesson_packages_price_kopecks_workflow_page_submit_visible_without_reload(): void
    {
        $this->grantPermission('lessonPackages.view');
        $this->grantPermission('setPrices.packageAssignments.view');

        $unique = 'PkgMoney-'.uniqid('', true);

        $page = $this->get(route('admin.lesson-packages.index'));
        $page->assertOk();
        $this->assertNotSame('', trim((string) $page->getContent()));
        $page->assertSee('step="0.01"', false)
            ->assertSee('name="create[price]"', false);

        $this->postJson(route('admin.lesson-packages.store'), [
            'name' => $unique,
            'schedule_type' => 'fixed',
            'duration_days' => 30,
            'lessons_count' => 4,
            'price' => '88.88',
            'freeze_enabled' => 0,
        ], $this->ajaxHeaders())
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('lesson_packages', [
            'partner_id' => $this->partner->id,
            'name' => $unique,
            'price_cents' => 8888,
        ]);

        $pageAfter = $this->get(route('admin.lesson-packages.index'));
        $pageAfter->assertOk();
        $this->assertNotSame('', trim((string) $pageAfter->getContent()));
        $pageAfter->assertSee('step="0.01"', false);

        // DataTables packages list
        $data = $this->getJson(route('admin.lesson-packages.data').'?search[value]='.urlencode($unique), $this->ajaxHeaders())
            ->assertOk()
            ->json();
        $this->assertNotSame('', trim(json_encode($data, JSON_UNESCAPED_UNICODE)));
        $row = collect($data['data'] ?? [])->first(
            fn ($r) => str_contains((string) ($r['name'] ?? ''), $unique)
                || str_contains((string) ($r['name_label'] ?? ''), $unique)
        );
        $this->assertNotNull($row, 'Шаблон с копейками должен быть в packages/data без F5.');
    }
}
