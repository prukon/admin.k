<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Reports;

use App\Models\Location;
use App\Models\Payment;
use App\Models\User;
use App\Models\UserTableSetting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Отчёт «По админам»: группировка по живым админам объекта, право reports.ltv.admins.view.
 */
final class LtvAdminsReportFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    public function test_page_requires_reports_ltv_admins_view(): void
    {
        $denied = $this->createUserWithoutPermission('reports.ltv.admins.view', $this->partner);
        $this->actingAs($denied);

        $this->get(route('reports.ltv.admins'))->assertForbidden();
        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('reports.ltv.admins.data', ['draw' => 1]))
            ->assertForbidden();
        $this->get(route('reports.ltv.admins.total'))->assertForbidden();
        $this->getJson(route('reports.ltv.admins.users.search', ['q' => '']))->assertForbidden();
        $this->getJson(route('reports.ltv.admins.teams.search', ['q' => '']))->assertForbidden();
        $this->getJson(route('reports.ltv.admins.trainers.search', ['q' => '']))->assertForbidden();
    }

    public function test_guest_cannot_open_page(): void
    {
        Auth::logout();

        $this->get(route('reports.ltv.admins'))->assertRedirect();
        $this->getJson(route('reports.ltv.admins.total'))->assertStatus(401);
    }

    public function test_user_with_only_this_permission_sees_tab_and_menu(): void
    {
        $user = $this->userWithOnlyAdminsPermission();
        $this->actingAs($user);

        $html = $this->get(route('reports.ltv.admins'))->assertOk()->getContent();
        $this->assertStringContainsString('По админам', $html);
        $this->assertStringContainsString('id="ltv-admins-table"', $html);
        $this->assertStringNotContainsString('Платежи по ученикам', $html);
        $this->assertStringContainsString('Отчеты', $html);
        $this->assertStringContainsString(route('reports.ltv.admins'), $html);
        $this->assertStringContainsString('/admin/reports/ltv/admins/users-search', $html);
    }

    public function test_tab_is_after_locations_and_hidden_without_permission(): void
    {
        $this->asAdmin();
        $without = $this->get(route('reports.ltv.locations'))->assertOk()->getContent();
        $this->assertStringNotContainsString('>По админам</a>', $without);

        $this->grantAdminsPermission($this->user);
        $html = $this->get(route('reports.ltv.admins'))->assertOk()->getContent();
        $locationsPos = strpos($html, 'Платежи по объектам');
        $adminsPos = strpos($html, '>По админам</a>');
        $this->assertNotFalse($locationsPos);
        $this->assertNotFalse($adminsPos);
        $this->assertTrue($locationsPos < $adminsPos);
        $this->assertStringContainsString('<th>Админ</th>', $html);
        $this->assertStringContainsString('<th>Объекты</th>', $html);
        $this->assertStringContainsString('<th>Ученики</th>', $html);
        $this->assertStringContainsString('<th>ФИО</th>', $html);
        $this->assertStringContainsString('<th>Группа</th>', $html);
        $this->assertStringNotContainsString('<th>Статус</th>', $html);
        $this->assertStringContainsString('var ltvAdminsSumOrderIndex = 5;', $html);
    }

    public function test_non_ajax_data_returns_404(): void
    {
        $this->asAdmin();
        $this->grantAdminsPermission($this->user);

        $this->get(route('reports.ltv.admins.data', ['draw' => 1]))->assertNotFound();
    }

    public function test_payment_is_in_each_admin_row_and_header_counts_it_once(): void
    {
        $this->asAdmin();
        $this->grantAdminsPermission($this->user);

        $shared = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Зал-Альфа',
            'is_enabled' => 1,
        ]);
        $lonely = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Зал-Бета',
            'is_enabled' => 1,
        ]);
        $orphaned = Location::factory()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Зал-Гамма',
            'is_enabled' => 1,
        ]);

        $anna = $this->makeAdmin('Северная', 'Анна');
        $boris = $this->makeAdmin('Южный', 'Борис');
        $gone = $this->makeAdmin('Удалённый', 'Пётр');
        $gone->delete();

        $this->attachAdmin($shared->id, $anna->id);
        $this->attachAdmin($shared->id, $boris->id);
        $this->attachAdmin($orphaned->id, $gone->id);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'Иванов',
            'name' => 'Пётр',
            'is_enabled' => 1,
        ]);

        Payment::factory()->create([
            'user_id' => $student->id,
            'partner_id' => $this->partner->id,
            'location_id' => $shared->id,
            'summ_cents' => 10000,
            'operation_date' => '2026-09-01 10:00:00',
        ]);
        Payment::factory()->create([
            'user_id' => $student->id,
            'partner_id' => $this->partner->id,
            'location_id' => $lonely->id,
            'summ_cents' => 5000,
            'operation_date' => '2026-09-02 10:00:00',
        ]);
        Payment::factory()->create([
            'user_id' => $student->id,
            'partner_id' => $this->partner->id,
            'location_id' => null,
            'summ_cents' => 3000,
            'operation_date' => '2026-09-03 10:00:00',
        ]);
        Payment::factory()->create([
            'user_id' => $student->id,
            'partner_id' => $this->partner->id,
            'location_id' => $orphaned->id,
            'summ_cents' => 4000,
            'operation_date' => '2026-09-04 10:00:00',
        ]);

        $byName = [];
        foreach ($this->rows() as $row) {
            $byName[(string) $row['admin_name']] = $row;
        }

        $this->assertArrayHasKey('Северная Анна', $byName);
        $this->assertArrayHasKey('Южный Борис', $byName);
        $this->assertArrayNotHasKey('Удалённый Пётр', $byName);
        $this->assertSame(1, (int) $byName['Северная Анна']['payment_count']);
        $this->assertEquals(100.0, (float) $byName['Северная Анна']['total_price']);
        $this->assertEquals(100.0, (float) $byName['Северная Анна']['avg_check']);
        $this->assertSame(['Зал-Альфа'], $byName['Северная Анна']['location_names_items']);
        $this->assertSame(['Иванов Пётр'], $byName['Северная Анна']['user_names_items']);
        $this->assertSame((int) $anna->id, (int) $byName['Северная Анна']['admin_user_id']);
        $this->assertSame(1, (int) $byName['Южный Борис']['payment_count']);
        $this->assertEquals(100.0, (float) $byName['Южный Борис']['total_price']);

        $this->assertArrayHasKey('Без админа', $byName);
        $this->assertSame(0, (int) $byName['Без админа']['admin_user_id']);
        $this->assertSame(3, (int) $byName['Без админа']['payment_count']);
        $this->assertEquals(120.0, (float) $byName['Без админа']['total_price']);
        $this->assertEquals(40.0, (float) $byName['Без админа']['avg_check']);
        $this->assertEqualsCanonicalizing(
            ['Без объекта', 'Зал-Бета', 'Зал-Гамма'],
            $byName['Без админа']['location_names_items']
        );
        $this->assertTrue(
            $byName['Без админа']['avg_attendance'] === null
            || $byName['Без админа']['avg_attendance'] === ''
        );

        $this->get(route('reports.ltv.admins.total', ['period' => 'all']))
            ->assertOk()
            ->assertJson([
                'total_formatted' => '220',
                'total_raw' => 220.0,
            ]);

        $annaPayments = $this->detailRows((int) $anna->id);
        $this->assertCount(1, $annaPayments);
        $this->assertEquals(100.0, (float) $annaPayments[0]['summ']);

        $nonePayments = $this->detailRows(0);
        $sums = array_map(static fn ($row) => (float) $row['summ'], $nonePayments);
        sort($sums);
        $this->assertSame([30.0, 40.0, 50.0], $sums);
    }

    public function test_filters_are_stored_for_the_user(): void
    {
        $this->asAdmin();
        $this->grantAdminsPermission($this->user);

        $this->postJson(route('reports.ltv.admins.filters.save'), [
            'payment_provider' => 'robokassa',
            'status' => 'inactive',
            'mode' => 'subscription',
        ])->assertOk()->assertExactJson(['success' => true]);

        $row = UserTableSetting::query()
            ->where('user_id', $this->user->id)
            ->where('table_key', 'reports_ltv_admins')
            ->first();
        $this->assertNotNull($row);
        $this->assertSame('robokassa', $row->filters['payment_provider'] ?? null);
        $this->assertSame('inactive', $row->filters['status'] ?? null);
        $this->assertSame('subscription', $row->filters['mode'] ?? null);

        $html = $this->get(route('reports.ltv.admins'))->assertOk()->getContent();
        $this->assertStringContainsString('value="robokassa" selected', $html);
        $this->assertStringContainsString('value="inactive" selected', $html);
        $this->assertMatchesRegularExpression(
            '/js-ltv-admins-group-mode-btn active[^>]*data-mode="subscription"/',
            $html
        );
        $this->assertStringContainsString('class="collapse show mb-2 mb-md-3" id="ltvAdminsReportFiltersCollapse"', $html);
    }

    private function grantAdminsPermission(User $user): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $user->role_id,
            'permission_id' => $this->permissionId('reports.ltv.admins.view'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $user->unsetRelation('role');
    }

    private function userWithOnlyAdminsPermission(): User
    {
        $now = now();
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'test_ltv_admins_'.Str::lower(Str::random(6)),
            'label' => 'Только по админам',
            'is_sistem' => 0,
            'order_by' => 0,
            'is_visible' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('permission_role')->insert([
            'partner_id' => $this->partner->id,
            'role_id' => $roleId,
            'permission_id' => $this->permissionId('reports.ltv.admins.view'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $roleId,
            'is_enabled' => 1,
        ]);
    }

    private function makeAdmin(string $lastname, string $name): User
    {
        return User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('admin'),
            'is_enabled' => 1,
            'lastname' => $lastname,
            'name' => $name,
        ]);
    }

    private function attachAdmin(int $locationId, int $userId): void
    {
        DB::table('location_admin_user')->insert([
            'partner_id' => $this->partner->id,
            'location_id' => $locationId,
            'user_id' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(): array
    {
        return $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('reports.ltv.admins.data', [
                'draw' => 1,
                'start' => 0,
                'length' => 50,
                'period' => 'all',
            ]))
            ->assertOk()
            ->json('data') ?? [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function detailRows(int $adminUserId): array
    {
        return $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('reports.ltv.admins.payments', [
                'admin' => $adminUserId,
                'draw' => 1,
                'start' => 0,
                'length' => 20,
                'period' => 'all',
            ]))
            ->assertOk()
            ->json('data') ?? [];
    }
}
