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
 * Глобальный поиск DataTables на вкладке доп. платежей:
 * ФИО / группа / примечание. Сумма и id не ищутся (иначе 42S22 на alias).
 */
final class CustomPaymentsDatatableSearchFeatureTest extends CrmTestCase
{
    private Team $teamAlpha;

    private Team $teamBeta;

    private User $studentAlpha;

    private User $studentBeta;

    private UserCustomPayment $paymentAlpha;

    private UserCustomPayment $paymentBeta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        foreach (['setPrices.view', 'setPrices.customPayments.view'] as $permission) {
            DB::table('permission_role')->insertOrIgnore([
                'partner_id' => $this->partner->id,
                'role_id' => $this->user->role_id,
                'permission_id' => $this->permissionId($permission),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->actingAs($this->user);

        $this->teamAlpha = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'ГруппаАльфаCpSearch',
        ]);
        $this->teamBeta = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'ГруппаБетаCpSearch',
        ]);

        $this->studentAlpha = User::factory()->withoutTeam()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
            'is_enabled' => true,
            'lastname' => 'УникаловCp',
            'name' => 'Иван',
        ]);
        $this->studentBeta = User::factory()->withoutTeam()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
            'is_enabled' => true,
            'lastname' => 'ДруговCp',
            'name' => 'Пётр',
        ]);

        app(TeamUserSyncService::class)->attachTeamForStudent($this->studentAlpha, (int) $this->teamAlpha->id);
        app(TeamUserSyncService::class)->attachTeamForStudent($this->studentBeta, (int) $this->teamBeta->id);

        $this->paymentAlpha = UserCustomPayment::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->studentAlpha->id,
            'team_id' => $this->teamAlpha->id,
            'amount_cents' => 12345,
            'note' => 'ПримечаниеАльфаCp',
            'is_paid' => false,
        ]);
        $this->paymentBeta = UserCustomPayment::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $this->studentBeta->id,
            'team_id' => $this->teamBeta->id,
            'amount_cents' => 98765,
            'note' => 'ДругоеОписаниеCp',
            'is_paid' => false,
        ]);
    }

    /**
     * Колонки как в живой таблице до фикса: user_name/amount searchable
     * (Yajra autoFilter давал 42S22 на alias).
     *
     * @return list<array<string, string>>
     */
    private function browserColumns(): array
    {
        $col = static function (string $data, string $name, bool $searchable = true): array {
            return [
                'data' => $data,
                'name' => $name,
                'searchable' => $searchable ? 'true' : 'false',
                'orderable' => 'true',
            ];
        };

        return [
            $col('id', 'id'),
            $col('user_name', 'user_name'),
            $col('team_label', 'team_label'),
            $col('amount', 'amount'),
            $col('note', 'note'),
            $col('status_label', 'status', false),
            $col('actions', 'actions', false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function searchQuery(string $needle): array
    {
        return [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
            'columns' => $this->browserColumns(),
            'search' => ['value' => $needle],
        ];
    }

    /**
     * @return list<int>
     */
    private function idsFromSearch(string $needle): array
    {
        $json = $this->getJson(route('admin.settingPrices.customPayments.data', $this->searchQuery($needle)))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data'])
            ->json();

        return collect($json['data'] ?? [])->pluck('id')->map(fn ($v) => (int) $v)->all();
    }

    public function test_guest_cannot_search_custom_payments_data(): void
    {
        Auth::logout();

        $response = $this->getJson(route('admin.settingPrices.customPayments.data', $this->searchQuery('тест')));

        $this->assertContains($response->getStatusCode(), [302, 401, 403]);
        $this->assertNotSame(500, $response->getStatusCode());
    }

    public function test_user_without_custom_payments_view_cannot_search(): void
    {
        $actor = $this->createUserWithoutPermission('setPrices.customPayments.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->getJson(route('admin.settingPrices.customPayments.data', $this->searchQuery('тест')))
            ->assertForbidden();
    }

    public function test_search_by_lastname_finds_row(): void
    {
        $ids = $this->idsFromSearch('УникаловCp');

        $this->assertContains((int) $this->paymentAlpha->id, $ids);
        $this->assertNotContains((int) $this->paymentBeta->id, $ids);
    }

    public function test_search_by_firstname_finds_row(): void
    {
        $hit = User::factory()->withoutTeam()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
            'is_enabled' => true,
            'lastname' => 'СидоровCpDt',
            'name' => 'УникИмяCpDt',
        ]);
        $miss = User::factory()->withoutTeam()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
            'is_enabled' => true,
            'lastname' => 'СидоровCpDt',
            'name' => 'Пётр',
        ]);
        app(TeamUserSyncService::class)->attachTeamForStudent($hit, (int) $this->teamAlpha->id);
        app(TeamUserSyncService::class)->attachTeamForStudent($miss, (int) $this->teamBeta->id);
        $pHit = UserCustomPayment::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $hit->id,
            'team_id' => $this->teamAlpha->id,
            'amount_cents' => 11000,
            'note' => 'Имя hit',
            'is_paid' => false,
        ]);
        $pMiss = UserCustomPayment::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $miss->id,
            'team_id' => $this->teamBeta->id,
            'amount_cents' => 12000,
            'note' => 'Имя miss',
            'is_paid' => false,
        ]);

        $ids = $this->idsFromSearch('УникИмяCpDt');

        $this->assertContains((int) $pHit->id, $ids);
        $this->assertNotContains((int) $pMiss->id, $ids);
    }

    public function test_search_by_full_name_finds_row(): void
    {
        $hit = User::factory()->withoutTeam()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
            'is_enabled' => true,
            'lastname' => 'ФамилияCpDt',
            'name' => 'ИмяCpDt',
        ]);
        app(TeamUserSyncService::class)->attachTeamForStudent($hit, (int) $this->teamAlpha->id);
        $pHit = UserCustomPayment::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $hit->id,
            'team_id' => $this->teamAlpha->id,
            'amount_cents' => 13000,
            'note' => 'Полное ФИО',
            'is_paid' => false,
        ]);

        $ids = $this->idsFromSearch('ФамилияCpDt ИмяCpDt');

        $this->assertContains((int) $pHit->id, $ids);
        $this->assertNotContains((int) $this->paymentBeta->id, $ids);
    }

    public function test_search_by_team_title_finds_row(): void
    {
        $ids = $this->idsFromSearch('ГруппаАльфаCpSearch');

        $this->assertContains((int) $this->paymentAlpha->id, $ids);
        $this->assertNotContains((int) $this->paymentBeta->id, $ids);
    }

    public function test_search_by_pivot_team_title_finds_row_when_payment_team_differs(): void
    {
        $pivotTeam = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'ГруппаПивотCpSearch',
        ]);
        app(TeamUserSyncService::class)->attachTeamForStudent($this->studentAlpha, (int) $pivotTeam->id);

        $ids = $this->idsFromSearch('ГруппаПивотCpSearch');

        $this->assertContains((int) $this->paymentAlpha->id, $ids);
        $this->assertNotContains((int) $this->paymentBeta->id, $ids);
    }

    public function test_search_by_note_finds_row(): void
    {
        $ids = $this->idsFromSearch('ПримечаниеАльфаCp');

        $this->assertContains((int) $this->paymentAlpha->id, $ids);
        $this->assertNotContains((int) $this->paymentBeta->id, $ids);
    }

    public function test_search_does_not_match_amount_or_id(): void
    {
        $byAmount = $this->idsFromSearch('987.65');
        $this->assertNotContains((int) $this->paymentBeta->id, $byAmount);

        $byId = $this->idsFromSearch((string) $this->paymentBeta->id);
        $this->assertNotContains((int) $this->paymentBeta->id, $byId);
    }

    public function test_search_does_not_match_status_label(): void
    {
        $this->paymentAlpha->is_paid = true;
        $this->paymentAlpha->save();

        $ids = $this->idsFromSearch('Оплачено');

        $this->assertNotContains((int) $this->paymentAlpha->id, $ids);
        $this->assertNotContains((int) $this->paymentBeta->id, $ids);
    }

    public function test_search_does_not_treat_percent_as_like_wildcard(): void
    {
        $ids = $this->idsFromSearch('%');

        $this->assertNotContains((int) $this->paymentAlpha->id, $ids);
        $this->assertNotContains((int) $this->paymentBeta->id, $ids);
    }

    public function test_search_does_not_treat_underscore_as_like_wildcard(): void
    {
        $ids = $this->idsFromSearch('_');

        $this->assertNotContains((int) $this->paymentAlpha->id, $ids);
        $this->assertNotContains((int) $this->paymentBeta->id, $ids);
    }

    public function test_search_narrows_records_filtered_but_keeps_records_total(): void
    {
        $json = $this->getJson(route(
            'admin.settingPrices.customPayments.data',
            $this->searchQuery('УникаловCp')
        ))
            ->assertOk()
            ->json();

        $this->assertGreaterThanOrEqual(2, (int) $json['recordsTotal']);
        $this->assertSame(1, (int) $json['recordsFiltered']);
        $this->assertContains((int) $this->paymentAlpha->id, collect($json['data'])->pluck('id')->map(fn ($v) => (int) $v)->all());
    }

    public function test_empty_search_does_not_hide_rows(): void
    {
        foreach (['', '   '] as $needle) {
            $ids = $this->idsFromSearch($needle);
            $this->assertContains((int) $this->paymentAlpha->id, $ids, "needle=".json_encode($needle));
            $this->assertContains((int) $this->paymentBeta->id, $ids, "needle=".json_encode($needle));
        }
    }

    public function test_search_does_not_show_other_partner_payments(): void
    {
        $foreignTeam = Team::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'title' => 'ЧужаяГруппаCpSearch',
        ]);
        $foreignStudent = User::factory()->withoutTeam()->create([
            'partner_id' => $this->foreignPartner->id,
            'role_id' => $this->roleId('user'),
            'is_enabled' => true,
            'lastname' => 'ЧужойУникаловCp',
            'name' => 'Иван',
        ]);
        app(TeamUserSyncService::class)->attachTeamForStudent($foreignStudent, (int) $foreignTeam->id);
        $foreignPayment = UserCustomPayment::query()->create([
            'partner_id' => $this->foreignPartner->id,
            'user_id' => $foreignStudent->id,
            'team_id' => $foreignTeam->id,
            'amount_cents' => 11111,
            'note' => 'ЧужоеПримечаниеCp',
            'is_paid' => false,
        ]);

        $ids = $this->idsFromSearch('ЧужойУникаловCp');
        $this->assertNotContains((int) $foreignPayment->id, $ids);
    }

    public function test_js_marks_id_and_amount_not_searchable(): void
    {
        $paths = [
            resource_path('js/setting-prices-custom-payments.js'),
            public_path('js/setting-prices-custom-payments.js'),
        ];
        $this->assertSame(
            (string) file_get_contents($paths[0]),
            (string) file_get_contents($paths[1]),
            'public/js должен совпадать с resources/js'
        );

        foreach ($paths as $path) {
            $js = (string) file_get_contents($path);
            $this->assertMatchesRegularExpression("/key:\\s*'id'[\\s\\S]{0,80}searchable:\\s*false/", $js);
            $this->assertMatchesRegularExpression("/key:\\s*'amount'[\\s\\S]{0,80}searchable:\\s*false/", $js);
        }
    }
}
