<?php

namespace Tests\Feature\Crm\Permissions;

use App\Models\Partner;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionGroupsSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolesSeeder;
use Database\Seeders\WeekdaysSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\AssertsSafeTestingDatabase;
use Tests\Support\InteractsWithRoleBasePermissionsConfig;
use Tests\TestCase;

/**
 * Проверяет «готовность к деплою» для создания партнёра: как на prod после migrate + reference seeders.
 * Ловит ситуации, когда в config/role_base_permissions.php есть права/роли, а в БД их нет
 * (забыли PermissionSeeder / RolesSeeder или не прогнали на сервере).
 */
class PartnerCreationDeployReadinessTest extends TestCase
{
    use AssertsSafeTestingDatabase;
    use InteractsWithRoleBasePermissionsConfig;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSafeTestingEnvironment();

        config(['cache.default' => 'array']);
        config(['logging.default' => 'errorlog']);
    }

    public function test_roles_seeder_creates_user_admin_and_trainer(): void
    {
        $this->seed(RolesSeeder::class);

        $this->assertGlobalBaseRolesExist();
    }

    public function test_permission_seeder_creates_all_role_base_permissions_from_config(): void
    {
        $this->seed(PermissionGroupsSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->assertNotEmpty(
            $this->basePermissionNamesAll(),
            'role_base_permissions config must define at least one permission'
        );

        $this->assertGlobalBasePermissionsExist();
    }

    public function test_partner_factory_create_succeeds_after_deploy_reference_seeders(): void
    {
        $this->seedDeployReferenceData();

        $partner = Partner::factory()->create();

        $this->assertGreaterThan(0, $partner->id);
        $this->assertDatabaseHas('partners', ['id' => $partner->id]);
    }

    public function test_admin_partner_store_returns_201_after_deploy_reference_seeders(): void
    {
        $this->seedDeployReferenceData();

        $contextPartner = Partner::factory()->create();

        $superadminRoleId = (int) Role::query()->where('name', 'superadmin')->value('id');
        $this->assertGreaterThan(0, $superadminRoleId);

        $actor = User::factory()->create([
            'partner_id' => $contextPartner->id,
            'role_id' => $superadminRoleId,
        ]);

        $email = 'deploy_ready_' . Str::lower(Str::random(8)) . '@example.test';

        $this->actingAs($actor);
        $this->withSession([
            'current_partner' => $contextPartner->id,
            '2fa:passed' => true,
        ]);

        $payload = [
            'business_type' => 'company',
            'title' => 'Партнёр deploy readiness',
            'organization_name' => 'ООО Deploy',
            'tax_id' => '1234567890',
            'kpp' => '123456789',
            'registration_number' => '1234567890123',
            'sms_name' => 'DEPLOYTEST',
            'city' => 'СПб',
            'zip' => '197350',
            'address' => 'Невский пр., 1',
            'phone' => '+79990001122',
            'email' => $email,
            'website' => 'https://example.test',
            'bank_name' => 'Банк',
            'bank_bik' => '123456789',
            'bank_account' => '12345678901234567890',
            'order_by' => 10,
            'is_enabled' => true,
            'ceo' => [
                'lastName' => 'Иванов',
                'firstName' => 'Иван',
                'middleName' => 'Иванович',
                'phone' => '+79991112233',
            ],
        ];

        $res = $this->postJson(route('admin.partner.store'), $payload);

        $res->assertStatus(201)
            ->assertJsonPath('message', 'Партнёр успешно создан');

        $createdId = (int) $res->json('partner.id');
        $this->assertGreaterThan(0, $createdId);
        $this->assertDatabaseHas('partners', [
            'id' => $createdId,
            'email' => $email,
        ]);
    }

    public function test_partner_create_fails_without_roles_seeder(): void
    {
        $this->seed(PermissionGroupsSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Required role');

        Partner::factory()->create();
    }

    public function test_partner_create_fails_without_permission_seeder(): void
    {
        $this->seed(RolesSeeder::class);

        $all = $this->basePermissionNamesAll();
        $this->assertNotEmpty($all);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Missing required permissions for base roles');

        Partner::factory()->create();
    }

    private function seedDeployReferenceData(): void
    {
        $this->seed(WeekdaysSeeder::class);
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionGroupsSeeder::class);
        $this->seed(PermissionSeeder::class);
    }
}
