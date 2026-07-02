<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\LessonPackage;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Контроль доступа к поверхности шаблонов абонементов (auto_attendance_enabled).
 */
final class LessonPackageAutoAttendanceAccessFeatureTest extends CrmTestCase
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
    private function storePayload(): array
    {
        return [
            'name' => 'Access auto',
            'schedule_type' => 'flexible',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price' => '1000.00',
            'freeze_enabled' => 0,
            'auto_attendance_enabled' => 1,
        ];
    }

    public function test_guest_get_index_redirects_to_login(): void
    {
        auth()->logout();

        $this->get(route('admin.lesson-packages.index'))
            ->assertRedirect(route('login'));
    }

    public function test_guest_post_store_redirects_to_login(): void
    {
        auth()->logout();

        $this->post(route('admin.lesson-packages.store'), $this->storePayload())
            ->assertRedirect(route('login'));
    }

    public function test_guest_put_update_redirects_to_login(): void
    {
        auth()->logout();

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Guest update',
            'schedule_type' => 'flexible',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 100000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'auto_attendance_enabled' => 0,
            'is_active' => 1,
        ]);

        $this->put(route('admin.lesson-packages.update', ['lessonPackage' => $package->id]), $this->storePayload())
            ->assertRedirect(route('login'));
    }

    public function test_guest_get_show_redirects_to_login(): void
    {
        auth()->logout();

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Guest show',
            'schedule_type' => 'flexible',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 100000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'auto_attendance_enabled' => 0,
            'is_active' => 1,
        ]);

        $this->get(route('admin.lesson-packages.show', ['lessonPackage' => $package->id]))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_without_permission_gets_403(): void
    {
        $actor = $this->createUserWithoutPermission('lessonPackages.view', $this->partner);
        $this->actingAs($actor);

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Forbidden',
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
        $this->postJson(route('admin.lesson-packages.store'), $this->storePayload())->assertForbidden();
        $this->getJson(route('admin.lesson-packages.show', ['lessonPackage' => $package->id]))->assertForbidden();
        $this->putJson(route('admin.lesson-packages.update', ['lessonPackage' => $package->id]), $this->storePayload())
            ->assertForbidden();
    }

    public function test_authenticated_user_with_permission_gets_expected_statuses(): void
    {
        $this->grantPermission('lessonPackages.view');

        $this->get(route('admin.lesson-packages.index'))
            ->assertOk()
            ->assertSee('Автосписание', false);

        $this->postJson(route('admin.lesson-packages.store'), $this->storePayload())
            ->assertOk()
            ->assertJson(['success' => true]);

        $package = LessonPackage::query()
            ->where('partner_id', $this->partner->id)
            ->where('name', 'Access auto')
            ->firstOrFail();

        $this->getJson(route('admin.lesson-packages.show', ['lessonPackage' => $package->id]))
            ->assertOk()
            ->assertJsonPath('lesson_package.auto_attendance_enabled', true);

        $this->putJson(route('admin.lesson-packages.update', ['lessonPackage' => $package->id]), array_merge(
            $this->storePayload(),
            ['auto_attendance_enabled' => 0]
        ))
            ->assertOk()
            ->assertJson(['success' => true]);
    }
}
