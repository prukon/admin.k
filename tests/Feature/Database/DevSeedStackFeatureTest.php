<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Models\Partner;
use App\Models\PaymentIntent;
use App\Models\PaymentSystem;
use App\Models\Payable;
use App\Models\Role;
use App\Models\Team;
use App\Models\TinkoffPayment;
use App\Models\TinkoffPayout;
use App\Models\User;
use App\Models\UserPrice;
use Database\Seeders\DevAdminRoleBasePermissionsSeeder;
use Database\Seeders\DevDistrictsSeeder;
use Database\Seeders\DevPartnerLegalEntitiesSeeder;
use Database\Seeders\DevPartnersSeeder;
use Database\Seeders\DevPaymentSystemsSeeder;
use Database\Seeders\DevPricesSeeder;
use Database\Seeders\DevSportTypesSeeder;
use Database\Seeders\DevTeamsSeeder;
use Database\Seeders\DevTbankHistorySeeder;
use Database\Seeders\DevTinkoffCommissionRulesSeeder;
use Database\Seeders\DevUsersSeeder;
use Database\Seeders\LessonOccurrenceStatusesSeeder;
use Database\Seeders\PermissionGroupsSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolesSeeder;
use Database\Seeders\SocialNetworksSeeder;
use Database\Seeders\WeekdaysSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * Dev-only сидеры: guard по SEED_DEV_DATA и целостность фикстур нового функционала.
 */
final class DevSeedStackFeatureTest extends TestCase
{
    use RefreshDatabase;

    private const ALLOWED_TEST_DATABASE = 'prukon_test.kidcrm.testing';

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSafeTestingEnvironment();
        $this->resetPartnersAutoIncrement();
    }

    public function test_dev_seeders_skip_when_seed_dev_data_disabled(): void
    {
        $this->disableDevSeedFlag();
        $this->seedBaseReferences();

        $this->seed(DevPartnersSeeder::class);

        $this->assertSame(0, Partner::query()->count());
        $this->assertSame(0, DB::table('partner_legal_entities')->count());
    }

    public function test_core_dev_seed_chain_populates_legal_entities_sport_types_districts_and_teams(): void
    {
        $this->enableDevSeedFlag();
        $this->seedBaseReferences();
        $this->seedCoreDevChain();

        $this->assertSame(3, Partner::query()->count());
        $this->assertSame(0, Partner::query()->whereNotNull('tinkoff_partner_id')->count());

        $this->assertSame(5, DB::table('partner_legal_entities')->count());
        $this->assertSame(3, DB::table('partner_legal_entities')->where('is_default', true)->count());

        $this->assertSame(9, DB::table('sport_types')->count());
        $this->assertSame(9, DB::table('districts')->count());

        $teamsWithoutLegalEntity = Team::query()->whereNull('legal_entity_id')->count();
        $this->assertSame(0, $teamsWithoutLegalEntity, 'Все команды должны иметь legal_entity_id');

        $teamsWithoutSportType = Team::query()->whereNull('sport_type_id')->count();
        $this->assertSame(0, $teamsWithoutSportType, 'Все команды должны иметь sport_type_id');
    }

    public function test_dev_tinkoff_commission_rules_seeder_creates_nine_rules_with_auto_payout_on_partner_one_card(): void
    {
        $this->enableDevSeedFlag();
        $this->seedBaseReferences();
        $this->seed(DevPartnersSeeder::class);
        $this->seed(DevTinkoffCommissionRulesSeeder::class);

        $this->assertSame(9, DB::table('tinkoff_commission_rules')->count());

        $cardRule = DB::table('tinkoff_commission_rules')
            ->where('partner_id', 1)
            ->where('method', 'card')
            ->first();

        $this->assertNotNull($cardRule);
        $this->assertTrue((bool) $cardRule->auto_payout_enabled);
        $this->assertSame(2, (int) $cardRule->auto_payout_delay_hours);
    }

    public function test_dev_payment_systems_seeder_is_idempotent_and_seeds_robokassa_and_global_tbank(): void
    {
        $this->enableDevSeedFlag();
        $this->seedBaseReferences();
        $this->seed(DevPartnersSeeder::class);

        $this->seed(DevPaymentSystemsSeeder::class);
        $this->seed(DevPaymentSystemsSeeder::class);

        $this->assertSame(2, PaymentSystem::query()->where('name', 'robokassa')->count());
        $this->assertSame(1, PaymentSystem::query()->where('name', 'tbank')->whereNull('partner_id')->count());

        $globalTbank = PaymentSystem::query()
            ->whereNull('partner_id')
            ->where('name', 'tbank')
            ->first();

        $this->assertNotNull($globalTbank);
        $this->assertTrue((bool) $globalTbank->test_mode);
    }

    public function test_dev_admin_role_base_permissions_includes_legal_entities_and_sport_types_view(): void
    {
        $this->enableDevSeedFlag();
        $this->seedBaseReferences();
        $this->seed(DevPartnersSeeder::class);
        $this->seed(DevAdminRoleBasePermissionsSeeder::class);

        $adminRoleId = Role::query()->where('name', 'admin')->value('id');
        $this->assertNotNull($adminRoleId);

        $permissionIds = DB::table('permissions')
            ->whereIn('name', ['legal_entities.view', 'legal_entities.manage', 'sport_types.view'])
            ->pluck('id', 'name');

        foreach (Partner::query()->pluck('id') as $partnerId) {
            foreach (['legal_entities.view', 'legal_entities.manage', 'sport_types.view'] as $permName) {
                $this->assertTrue(
                    DB::table('permission_role')
                        ->where('partner_id', $partnerId)
                        ->where('role_id', $adminRoleId)
                        ->where('permission_id', $permissionIds[$permName])
                        ->exists(),
                    "Admin role missing {$permName} for partner {$partnerId}"
                );
            }
        }

        $smRegisterPermId = DB::table('permissions')->where('name', 'legal_entities.sm_register')->value('id');
        if ($smRegisterPermId !== null) {
            $this->assertFalse(
                DB::table('permission_role')
                    ->where('role_id', $adminRoleId)
                    ->where('permission_id', $smRegisterPermId)
                    ->exists(),
                'Admin base permissions must not include legal_entities.sm_register'
            );
        }
    }

    public function test_dev_tbank_history_seeder_attaches_legal_entity_id_and_avoids_initiated_payouts(): void
    {
        $this->enableDevSeedFlag();
        $this->seedBaseReferences();
        $this->seed(DevPartnersSeeder::class);
        $this->seed(DevPartnerLegalEntitiesSeeder::class);

        $partner = Partner::query()->findOrFail(1);
        $user = User::factory()->create(['partner_id' => $partner->id]);
        $payable = Payable::factory()->create([
            'partner_id' => $partner->id,
            'user_id' => $user->id,
        ]);

        PaymentIntent::factory()->create([
            'partner_id' => $partner->id,
            'user_id' => $user->id,
            'payable_id' => $payable->id,
            'provider' => 'tbank',
            'status' => 'paid',
            'tbank_order_id' => 'dev-seed-test-order-' . uniqid('', true),
            'out_sum' => 1500.00,
            'payment_method' => 'card',
        ]);

        $this->seed(DevTbankHistorySeeder::class);
        $this->seed(DevTbankHistorySeeder::class);

        $payments = TinkoffPayment::query()->get();
        $this->assertNotEmpty($payments);

        foreach ($payments as $payment) {
            $this->assertNotNull($payment->legal_entity_id, 'TinkoffPayment must have legal_entity_id');
        }

        $this->assertSame(
            0,
            TinkoffPayout::query()->where('status', 'INITIATED')->count(),
            'Dev fixtures must not create INITIATED payouts'
        );
    }

    public function test_database_seeder_dev_block_populates_key_fixtures(): void
    {
        $this->enableDevSeedFlag();
        $this->seedBaseReferences();

        $devSeeders = [
            DevPartnersSeeder::class,
            DevPartnerLegalEntitiesSeeder::class,
            DevSportTypesSeeder::class,
            DevDistrictsSeeder::class,
            DevAdminRoleBasePermissionsSeeder::class,
            DevTinkoffCommissionRulesSeeder::class,
            DevPaymentSystemsSeeder::class,
        ];

        foreach ($devSeeders as $seederClass) {
            $this->seed($seederClass);
        }

        $this->assertGreaterThanOrEqual(3, Partner::query()->count());
        $this->assertGreaterThan(0, DB::table('partner_legal_entities')->count());
        $this->assertSame(9, DB::table('tinkoff_commission_rules')->count());
        $this->assertGreaterThan(0, PaymentSystem::query()->where('name', 'tbank')->whereNull('partner_id')->count());
    }

    public function test_dev_users_and_prices_seeders_respect_unique_and_multi_team(): void
    {
        $this->enableDevSeedFlag();
        $this->seedBaseReferences();
        $this->seedCoreDevChain();
        $this->seed(DevUsersSeeder::class);
        $this->seed(DevPricesSeeder::class);

        $multiTeamStudents = DB::table('team_user')
            ->select('user_id', DB::raw('COUNT(*) as teams_count'))
            ->groupBy('user_id')
            ->having('teams_count', '>=', 2)
            ->count();

        $this->assertGreaterThan(
            0,
            $multiTeamStudents,
            'DevUsersSeeder must attach 2+ teams for a share of students'
        );

        $pricesCount = UserPrice::query()->count();
        $this->assertGreaterThan(0, $pricesCount);
        $this->assertLessThanOrEqual(48 + 12, $pricesCount, 'Testing caps: paid≤48 + unpaid≤12');

        $duplicateKeys = DB::table('users_prices')
            ->select('user_id', 'team_id', 'new_month', DB::raw('COUNT(*) as c'))
            ->groupBy('user_id', 'team_id', 'new_month')
            ->having('c', '>', 1)
            ->count();

        $this->assertSame(0, $duplicateKeys, 'users_prices must be unique on (user_id, team_id, new_month)');

        $pricesOutsideMembership = DB::table('users_prices as up')
            ->leftJoin('team_user as tu', function ($join) {
                $join->on('tu.user_id', '=', 'up.user_id')
                    ->on('tu.team_id', '=', 'up.team_id');
            })
            ->whereNull('tu.user_id')
            ->count();

        $this->assertSame(
            0,
            $pricesOutsideMembership,
            'Every users_prices.team_id must match team_user membership'
        );

        $paidWithMetaTeam = Payable::query()
            ->where('type', 'monthly_fee')
            ->where('status', 'paid')
            ->get()
            ->filter(fn (Payable $p) => (int) ($p->meta['team_id'] ?? 0) > 0)
            ->count();

        $this->assertSame(
            Payable::query()->where('type', 'monthly_fee')->where('status', 'paid')->count(),
            $paidWithMetaTeam,
            'Each paid monthly payable from DevPricesSeeder must store meta.team_id'
        );

        $crossPartnerPivot = DB::table('team_user as tu')
            ->join('users as u', 'u.id', '=', 'tu.user_id')
            ->whereColumn('tu.partner_id', '<>', 'u.partner_id')
            ->count();

        $this->assertSame(
            0,
            $crossPartnerPivot,
            'team_user.partner_id must match users.partner_id after DevUsersSeeder'
        );
    }

    public function test_payable_factory_paid_monthly_respects_explicit_meta_team_id(): void
    {
        $this->enableDevSeedFlag();
        $this->seedBaseReferences();
        $this->seedCoreDevChain();

        $partner = Partner::query()->findOrFail(1);
        $teams = Team::query()->where('partner_id', $partner->id)->orderBy('id')->take(2)->get();
        $this->assertGreaterThanOrEqual(2, $teams->count());

        $user = User::factory()->create([
            'partner_id' => $partner->id,
            'team_id' => $teams[0]->id,
        ]);

        $sync = app(\App\Services\TeamUserSyncService::class);
        $sync->syncTeamsForStudent($user, [(int) $teams[0]->id, (int) $teams[1]->id]);

        $month = now()->startOfMonth()->format('Y-m-01');

        Payable::factory()
            ->paidMonthlyWithAllRelations()
            ->create([
                'partner_id' => $partner->id,
                'user_id' => $user->id,
                'month' => $month,
                'amount' => 3100,
                'meta' => ['team_id' => (int) $teams[1]->id],
            ]);

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $user->id,
            'team_id' => (int) $teams[1]->id,
            'new_month' => $month,
            'is_paid' => 1,
            'price' => 3100,
        ]);

        $payable = Payable::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame((int) $teams[1]->id, (int) ($payable->meta['team_id'] ?? 0));
    }

    private function seedBaseReferences(): void
    {
        $this->seed([
            WeekdaysSeeder::class,
            RolesSeeder::class,
            PermissionGroupsSeeder::class,
            PermissionSeeder::class,
            SocialNetworksSeeder::class,
            LessonOccurrenceStatusesSeeder::class,
        ]);
    }

    private function seedCoreDevChain(): void
    {
        $this->seed([
            DevPartnersSeeder::class,
            DevPartnerLegalEntitiesSeeder::class,
            DevSportTypesSeeder::class,
            DevDistrictsSeeder::class,
            DevTeamsSeeder::class,
        ]);
    }

    private function enableDevSeedFlag(): void
    {
        putenv('SEED_DEV_DATA=true');
        $_ENV['SEED_DEV_DATA'] = 'true';
        $_SERVER['SEED_DEV_DATA'] = 'true';
    }

    private function disableDevSeedFlag(): void
    {
        putenv('SEED_DEV_DATA=false');
        $_ENV['SEED_DEV_DATA'] = 'false';
        $_SERVER['SEED_DEV_DATA'] = 'false';
    }

    /**
     * После rollback транзакции MySQL AUTO_INCREMENT у partners не сбрасывается — dev-сидеры ждут id 1–3.
     */
    private function resetPartnersAutoIncrement(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('partners')) {
            return;
        }

        if (DB::table('partners')->count() === 0) {
            DB::statement('ALTER TABLE partners AUTO_INCREMENT = 1');
        }
    }

    private function assertSafeTestingEnvironment(): void
    {
        if (! app()->environment('testing')) {
            throw new RuntimeException('Dev seed tests require testing environment.');
        }

        $dbName = DB::connection()->getDatabaseName();
        if ($dbName !== self::ALLOWED_TEST_DATABASE) {
            throw new RuntimeException('Dev seed tests require DB: ' . self::ALLOWED_TEST_DATABASE . ", got: {$dbName}");
        }
    }
}
