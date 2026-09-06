<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\LessonPackage;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Доступ к экранам, где тип schedule_type=flexible показывается как «Предоплата».
 *
 * @see LessonPackageFlexibleUiLabelMarkupFeatureTest
 */
final class LessonPackageFlexibleUiLabelAccessFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
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
     * @return array<string, mixed>
     */
    private function storePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Access flexible label',
            'schedule_type' => 'flexible',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price' => '1000.00',
            'freeze_enabled' => 0,
            'auto_attendance_enabled' => 0,
        ], $overrides);
    }

    public function test_guest_cannot_open_pages_that_show_prepay_type_label(): void
    {
        auth()->logout();

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Guest flexible',
            'schedule_type' => 'flexible',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 100000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'auto_attendance_enabled' => 0,
            'is_active' => 1,
        ]);

        $this->get(route('admin.lesson-packages.index'))->assertRedirect(route('login'));
        $this->get(route('admin.directories.lesson-packages.index'))->assertRedirect(route('login'));
        $this->get(route('admin.lesson-packages.school-schedule'))->assertRedirect(route('login'));
        $this->get(route('admin.lesson-packages.assignments'))->assertRedirect(route('login'));
        $this->get(route('admin.settingPrices.paymentNotifications'))->assertRedirect(route('login'));
        $this->post(route('admin.lesson-packages.store'), $this->storePayload())->assertRedirect(route('login'));
        $this->put(
            route('admin.lesson-packages.update', ['lessonPackage' => $package->id]),
            $this->storePayload()
        )->assertRedirect(route('login'));
        $this->get(route('admin.lesson-packages.show', ['lessonPackage' => $package->id]))
            ->assertRedirect(route('login'));
    }

    public function test_guest_json_requests_are_denied_and_not_server_error(): void
    {
        auth()->logout();

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Guest json flexible',
            'schedule_type' => 'flexible',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 100000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'auto_attendance_enabled' => 0,
            'is_active' => 1,
        ]);

        foreach ([
            ['GET', route('admin.lesson-packages.index')],
            ['GET', route('admin.directories.lesson-packages.index')],
            ['GET', route('admin.lesson-packages.school-schedule')],
            ['GET', route('admin.lesson-packages.assignments')],
            ['GET', route('admin.settingPrices.paymentNotifications')],
            ['GET', route('admin.settingPrices.paymentNotifications.rules.index')],
            ['POST', route('admin.lesson-packages.store'), $this->storePayload()],
            ['GET', route('admin.lesson-packages.show', ['lessonPackage' => $package->id])],
            ['PUT', route('admin.lesson-packages.update', ['lessonPackage' => $package->id]), $this->storePayload()],
            ['GET', route('admin.lesson-packages.data')],
        ] as $item) {
            $response = $this->json($item[0], $item[1], $item[2] ?? []);
            $this->assertContains(
                $response->getStatusCode(),
                [302, 401, 403, 419],
                "Гость JSON {$item[0]} {$item[1]} → {$response->getStatusCode()}"
            );
            $this->assertNotSame(500, $response->getStatusCode());
            $this->assertNotSame(200, $response->getStatusCode());
        }
    }

    public function test_manager_without_lesson_packages_view_gets_403(): void
    {
        $actor = $this->createUserWithoutPermission('lessonPackages.view', $this->partner);
        $this->actingAs($actor);

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Forbidden flexible',
            'schedule_type' => 'flexible',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 100000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'auto_attendance_enabled' => 0,
            'is_active' => 1,
        ]);

        $this->get(route('admin.lesson-packages.index'))->assertForbidden();
        $this->get(route('admin.directories.lesson-packages.index'))->assertForbidden();
        $this->get(route('admin.lesson-packages.school-schedule'))->assertForbidden();
        $this->postJson(route('admin.lesson-packages.store'), $this->storePayload())->assertForbidden();
        $this->getJson(route('admin.lesson-packages.data'))->assertForbidden();
        $this->getJson(route('admin.lesson-packages.show', ['lessonPackage' => $package->id]))->assertForbidden();
        $this->putJson(
            route('admin.lesson-packages.update', ['lessonPackage' => $package->id]),
            $this->storePayload()
        )->assertForbidden();
    }

    public function test_manager_with_lesson_packages_view_but_without_assignments_permission_gets_403_on_assignments(): void
    {
        $this->grantPermission('lessonPackages.view');

        $this->get(route('admin.lesson-packages.assignments'))->assertForbidden();
        $this->getJson(route('admin.lesson-packages.assignments.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]))->assertForbidden();
    }

    public function test_manager_without_payment_notifications_permission_gets_403(): void
    {
        $actor = $this->createUserWithoutPermission('setPrices.paymentNotifications.manage', $this->partner);
        $this->actingAs($actor)->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->get(route('admin.settingPrices.paymentNotifications'))->assertForbidden();
        $this->getJson(route('admin.settingPrices.paymentNotifications.rules.index'))->assertForbidden();
    }

    public function test_manager_with_needed_permissions_can_open_pages_and_save_flexible_type(): void
    {
        $this->grantPermission('lessonPackages.view');
        $this->grantPermission('setPrices.packageAssignments.view');
        $this->grantPermission('setPrices.view');
        $this->grantPermission('setPrices.paymentNotifications.manage');
        $this->grantLessonPackageTypePermissions($this->user, ['flexible']);

        foreach ([
            route('admin.lesson-packages.index'),
            route('admin.directories.lesson-packages.index'),
        ] as $url) {
            $page = $this->get($url);
            $page->assertOk();
            $this->assertNotSame('', trim((string) $page->getContent()));
            $this->assertNotSame(500, $page->getStatusCode());
        }

        $this->get(route('admin.lesson-packages.school-schedule'))->assertOk();
        $this->get(route('admin.lesson-packages.assignments'))->assertOk();
        $this->get(route('admin.settingPrices.paymentNotifications'))->assertOk();

        $this->postJson(route('admin.lesson-packages.store'), $this->storePayload())
            ->assertOk()
            ->assertJson(['success' => true]);

        $package = LessonPackage::query()
            ->where('partner_id', $this->partner->id)
            ->where('name', 'Access flexible label')
            ->firstOrFail();
        $this->assertSame('flexible', (string) $package->schedule_type);

        $this->getJson(route('admin.lesson-packages.show', ['lessonPackage' => $package->id]))
            ->assertOk()
            ->assertJsonPath('lesson_package.schedule_type', 'flexible');

        $data = $this->getJson(route('admin.lesson-packages.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
            'name' => 'Access flexible label',
        ]));
        $data->assertOk();
        $this->assertNotSame('', trim((string) $data->getContent()));
        $this->assertNotSame(500, $data->getStatusCode());
    }

    public function test_update_of_foreign_partner_package_returns_not_found(): void
    {
        $this->grantPermission('lessonPackages.view');

        $foreign = LessonPackage::query()->create([
            'partner_id' => $this->foreignPartner->id,
            'name' => 'Чужой flexible',
            'schedule_type' => 'flexible',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 100000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'auto_attendance_enabled' => 0,
            'is_active' => 1,
        ]);

        $this->putJson(
            route('admin.lesson-packages.update', ['lessonPackage' => $foreign->id]),
            $this->storePayload(['name' => 'Не должен обновиться'])
        )->assertNotFound();

        $this->assertSame('flexible', (string) $foreign->fresh()->schedule_type);
        $this->assertSame('Чужой flexible', (string) $foreign->fresh()->name);
    }
}
