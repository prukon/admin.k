<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\LessonPackage;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Доступ к полю «Срок действия (дни)» и CRUD шаблонов (scheduleSlots.view / lessonPackages.view).
 *
 * @see LessonPackageDurationPermissionFeatureTest
 * @see LessonPackageAutoAttendanceAccessFeatureTest
 */
final class LessonPackageDurationAccessFeatureTest extends CrmTestCase
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
            'name' => 'Access duration',
            'schedule_type' => 'flexible',
            'duration_days' => 14,
            'lessons_count' => 8,
            'price' => '1000.00',
            'freeze_enabled' => 0,
            'auto_attendance_enabled' => 0,
        ], $overrides);
    }

    public function test_guest_cannot_open_packages_pages_or_mutate_duration(): void
    {
        auth()->logout();

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Guest duration',
            'schedule_type' => 'flexible',
            'duration_days' => 45,
            'lessons_count' => 8,
            'price_cents' => 100000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'auto_attendance_enabled' => 0,
            'is_active' => 1,
        ]);

        $this->get(route('admin.lesson-packages.index'))->assertRedirect(route('login'));
        $this->get(route('admin.directories.lesson-packages.index'))->assertRedirect(route('login'));
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
            'name' => 'Guest json duration',
            'schedule_type' => 'flexible',
            'duration_days' => 45,
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

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Forbidden duration',
            'schedule_type' => 'flexible',
            'duration_days' => 45,
            'lessons_count' => 8,
            'price_cents' => 100000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'auto_attendance_enabled' => 0,
            'is_active' => 1,
        ]);

        $this->get(route('admin.lesson-packages.index'))->assertForbidden();
        $this->get(route('admin.directories.lesson-packages.index'))->assertForbidden();
        $this->postJson(route('admin.lesson-packages.store'), $this->storePayload())->assertForbidden();
        $this->getJson(route('admin.lesson-packages.show', ['lessonPackage' => $package->id]))->assertForbidden();
        $this->putJson(
            route('admin.lesson-packages.update', ['lessonPackage' => $package->id]),
            $this->storePayload()
        )->assertForbidden();
    }

    public function test_manager_with_schedule_slots_view_can_open_modals_and_save_custom_duration(): void
    {
        $this->grantPermission('lessonPackages.view');
        $this->grantPermission('scheduleSlots.view');

        foreach ([
            route('admin.lesson-packages.index'),
            route('admin.directories.lesson-packages.index'),
        ] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('id="create_duration_days"', false)
                ->assertSee('id="edit_duration_days"', false)
                ->assertSee('Срок действия (дни) *', false);
        }

        $this->postJson(route('admin.lesson-packages.store'), $this->storePayload())
            ->assertOk()
            ->assertJson(['success' => true]);

        $package = LessonPackage::query()
            ->where('partner_id', $this->partner->id)
            ->where('name', 'Access duration')
            ->firstOrFail();
        $this->assertSame(14, (int) $package->duration_days);

        $this->getJson(route('admin.lesson-packages.show', ['lessonPackage' => $package->id]))
            ->assertOk()
            ->assertJsonPath('lesson_package.duration_days', 14);

        $this->putJson(
            route('admin.lesson-packages.update', ['lessonPackage' => $package->id]),
            $this->storePayload(['duration_days' => 21])
        )
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame(21, (int) $package->fresh()->duration_days);
    }

    public function test_manager_without_schedule_slots_view_still_opens_pages_but_duration_inputs_are_hidden(): void
    {
        $this->grantPermission('lessonPackages.view');

        foreach ([
            route('admin.lesson-packages.index'),
            route('admin.directories.lesson-packages.index'),
        ] as $url) {
            $page = $this->get($url);
            $page->assertOk();
            $this->assertNotSame('', trim((string) $page->getContent()));
            $page->assertDontSee('id="create_duration_days"', false)
                ->assertDontSee('id="edit_duration_days"', false)
                ->assertSee('id="create_lessons_count"', false)
                ->assertSee('Срок действия (дни)', false);
        }
    }

    public function test_superadmin_sees_duration_field_without_explicit_schedule_slots_permission(): void
    {
        $this->asSuperadmin()->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->get(route('admin.lesson-packages.index'))
            ->assertOk()
            ->assertSee('id="create_duration_days"', false)
            ->assertSee('id="edit_duration_days"', false);
    }

    public function test_update_of_foreign_partner_package_returns_not_found(): void
    {
        $this->grantPermission('lessonPackages.view');
        $this->grantPermission('scheduleSlots.view');

        $foreign = LessonPackage::query()->create([
            'partner_id' => $this->foreignPartner->id,
            'name' => 'Чужой срок',
            'schedule_type' => 'flexible',
            'duration_days' => 60,
            'lessons_count' => 8,
            'price_cents' => 100000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'auto_attendance_enabled' => 0,
            'is_active' => 1,
        ]);

        $this->putJson(
            route('admin.lesson-packages.update', ['lessonPackage' => $foreign->id]),
            $this->storePayload(['duration_days' => 10])
        )->assertNotFound();

        $this->assertSame(60, (int) $foreign->fresh()->duration_days);
    }
}
