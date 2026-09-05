<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\LessonPackage;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Доступ к чекбоксу «Разрешена заморозка» и CRUD шаблонов (scheduleSlots.view / lessonPackages.view).
 *
 * @see LessonPackageFreezePermissionFeatureTest
 * @see LessonPackageAutoAttendanceAccessFeatureTest
 */
final class LessonPackageFreezeAccessFeatureTest extends CrmTestCase
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
            'name' => 'Access freeze',
            'schedule_type' => 'flexible',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price' => '1000.00',
            'freeze_enabled' => 1,
            'freeze_days' => 7,
            'auto_attendance_enabled' => 0,
        ], $overrides);
    }

    private function createPackage(array $overrides = []): LessonPackage
    {
        return LessonPackage::query()->create(array_merge([
            'partner_id' => $this->partner->id,
            'name' => 'Guest freeze',
            'schedule_type' => 'flexible',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 100000,
            'freeze_enabled' => 1,
            'freeze_days' => 14,
            'auto_attendance_enabled' => 0,
            'is_active' => 1,
        ], $overrides));
    }

    public function test_guest_cannot_open_packages_pages_or_mutate_freeze(): void
    {
        auth()->logout();

        $package = $this->createPackage();

        $this->get(route('admin.lesson-packages.index'))->assertRedirect(route('login'));
        $this->get(route('admin.directories.lesson-packages.index'))->assertRedirect(route('login'));
        $this->post(route('admin.lesson-packages.store'), $this->storePayload())->assertRedirect(route('login'));
        $this->put(
            route('admin.lesson-packages.update', ['lessonPackage' => $package->id]),
            $this->storePayload()
        )->assertRedirect(route('login'));
        $this->get(route('admin.lesson-packages.show', ['lessonPackage' => $package->id]))
            ->assertRedirect(route('login'));
        $this->delete(route('admin.lesson-packages.destroy', ['lessonPackage' => $package->id]))
            ->assertRedirect(route('login'));
    }

    public function test_guest_json_requests_are_denied_and_not_server_error(): void
    {
        auth()->logout();

        $package = $this->createPackage(['name' => 'Guest json freeze']);

        foreach ([
            ['GET', route('admin.lesson-packages.index')],
            ['GET', route('admin.directories.lesson-packages.index')],
            ['POST', route('admin.lesson-packages.store'), $this->storePayload()],
            ['GET', route('admin.lesson-packages.show', ['lessonPackage' => $package->id])],
            ['PUT', route('admin.lesson-packages.update', ['lessonPackage' => $package->id]), $this->storePayload()],
            ['DELETE', route('admin.lesson-packages.destroy', ['lessonPackage' => $package->id])],
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

    public function test_authenticated_user_without_lesson_packages_view_gets_403(): void
    {
        $actor = $this->createUserWithoutPermission('lessonPackages.view', $this->partner);
        $this->actingAs($actor);

        $package = $this->createPackage(['name' => 'Forbidden freeze']);

        $this->get(route('admin.lesson-packages.index'))->assertForbidden();
        $this->get(route('admin.directories.lesson-packages.index'))->assertForbidden();
        $this->postJson(route('admin.lesson-packages.store'), $this->storePayload())->assertForbidden();
        $this->getJson(route('admin.lesson-packages.show', ['lessonPackage' => $package->id]))->assertForbidden();
        $this->putJson(
            route('admin.lesson-packages.update', ['lessonPackage' => $package->id]),
            $this->storePayload()
        )->assertForbidden();
        $this->deleteJson(route('admin.lesson-packages.destroy', ['lessonPackage' => $package->id]))
            ->assertForbidden();
        $this->getJson(route('admin.lesson-packages.data'))->assertForbidden();
    }

    public function test_manager_with_schedule_slots_view_can_open_modals_and_save_freeze(): void
    {
        $this->grantPermission('lessonPackages.view');
        $this->grantPermission('scheduleSlots.view');

        foreach ([
            route('admin.lesson-packages.index'),
            route('admin.directories.lesson-packages.index'),
        ] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('id="create_freeze_enabled"', false)
                ->assertSee('id="edit_freeze_enabled"', false)
                ->assertSee('Разрешена заморозка', false)
                ->assertSee('id="colLessonPackageFreeze"', false);
        }

        $this->postJson(route('admin.lesson-packages.store'), $this->storePayload())
            ->assertOk()
            ->assertJson(['success' => true]);

        $package = LessonPackage::query()
            ->where('partner_id', $this->partner->id)
            ->where('name', 'Access freeze')
            ->firstOrFail();
        $this->assertTrue((bool) $package->freeze_enabled);
        $this->assertSame(7, (int) $package->freeze_days);

        $this->getJson(route('admin.lesson-packages.show', ['lessonPackage' => $package->id]))
            ->assertOk()
            ->assertJsonPath('lesson_package.freeze_enabled', true)
            ->assertJsonPath('lesson_package.freeze_days', 7);

        $this->putJson(
            route('admin.lesson-packages.update', ['lessonPackage' => $package->id]),
            $this->storePayload(['freeze_enabled' => 0, 'freeze_days' => ''])
        )
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertFalse((bool) $package->fresh()->freeze_enabled);
        $this->assertSame(0, (int) $package->fresh()->freeze_days);
    }

    public function test_manager_without_schedule_slots_view_still_opens_pages_but_freeze_inputs_are_hidden(): void
    {
        $this->grantPermission('lessonPackages.view');

        foreach ([
            route('admin.lesson-packages.index'),
            route('admin.directories.lesson-packages.index'),
        ] as $url) {
            $page = $this->get($url);
            $page->assertOk();
            $this->assertNotSame('', trim((string) $page->getContent()));
            $page->assertDontSee('id="create_freeze_enabled"', false)
                ->assertDontSee('id="edit_freeze_enabled"', false)
                ->assertDontSee('id="create_freeze_days"', false)
                ->assertSee('id="create_lessons_count"', false)
                ->assertSee('id="colLessonPackageFreeze"', false)
                ->assertSee('Заморозка', false);
        }
    }

    public function test_data_table_returns_freeze_label_without_schedule_slots_view(): void
    {
        $this->grantPermission('lessonPackages.view');

        $on = $this->createPackage(['name' => 'Freeze on row']);
        $off = $this->createPackage([
            'name' => 'Freeze off row',
            'freeze_enabled' => 0,
            'freeze_days' => 0,
        ]);

        $json = $this->getJson(route('admin.lesson-packages.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 100,
        ]))
            ->assertOk()
            ->json();

        $onRow = collect($json['data'] ?? [])->firstWhere('id', $on->id);
        $offRow = collect($json['data'] ?? [])->firstWhere('id', $off->id);
        $this->assertIsArray($onRow);
        $this->assertIsArray($offRow);
        $this->assertSame('14', $onRow['freeze_label']);
        $this->assertTrue((bool) $onRow['freeze_enabled']);
        $this->assertSame(14, (int) $onRow['freeze_days']);
        $this->assertSame('нет', $offRow['freeze_label']);
        $this->assertFalse((bool) $offRow['freeze_enabled']);
    }

    public function test_superadmin_sees_freeze_checkbox_without_explicit_schedule_slots_permission(): void
    {
        $this->asSuperadmin()->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->get(route('admin.lesson-packages.index'))
            ->assertOk()
            ->assertSee('id="create_freeze_enabled"', false)
            ->assertSee('id="edit_freeze_enabled"', false)
            ->assertSee('Разрешена заморозка', false);
    }

    public function test_update_of_foreign_partner_package_returns_not_found(): void
    {
        $this->grantPermission('lessonPackages.view');
        $this->grantPermission('scheduleSlots.view');

        $foreign = LessonPackage::query()->create([
            'partner_id' => $this->foreignPartner->id,
            'name' => 'Чужая заморозка',
            'schedule_type' => 'flexible',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 100000,
            'freeze_enabled' => 1,
            'freeze_days' => 10,
            'auto_attendance_enabled' => 0,
            'is_active' => 1,
        ]);

        $this->putJson(
            route('admin.lesson-packages.update', ['lessonPackage' => $foreign->id]),
            $this->storePayload(['freeze_enabled' => 0, 'freeze_days' => ''])
        )->assertNotFound();

        $this->assertTrue((bool) $foreign->fresh()->freeze_enabled);
        $this->assertSame(10, (int) $foreign->fresh()->freeze_days);
    }
}
