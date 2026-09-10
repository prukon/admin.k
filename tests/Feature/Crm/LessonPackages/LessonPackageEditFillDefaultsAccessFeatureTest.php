<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\LessonPackage;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Доступ к show/data, из которых модалка «Изменить» и таблица берут занятий/срок.
 *
 * @see LessonPackageEditFillDefaultsAjaxContractFeatureTest
 * @see docs/documentation/lesson-packages.html
 */
final class LessonPackageEditFillDefaultsAccessFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->grantLessonPackageTypePermissions();
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

    private function makeFlexibleTwelve(): LessonPackage
    {
        return LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Гибкий 12 занятий',
            'schedule_type' => 'flexible',
            'duration_days' => 120,
            'lessons_count' => 12,
            'price_cents' => 1680000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'auto_attendance_enabled' => 0,
            'is_active' => 1,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function updatePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Гибкий 12 занятий',
            'schedule_type' => 'flexible',
            'duration_days' => 120,
            'lessons_count' => 12,
            'price' => '16800.00',
            'freeze_enabled' => 0,
            'auto_attendance_enabled' => 0,
        ], $overrides);
    }

    public function test_guest_is_redirected_from_packages_pages_and_edit_payload_endpoints(): void
    {
        auth()->logout();

        $package = $this->makeFlexibleTwelve();

        $this->get(route('admin.lesson-packages.index'))->assertRedirect(route('login'));
        $this->get(route('admin.directories.lesson-packages.index'))->assertRedirect(route('login'));
        $this->get(route('admin.lesson-packages.show', ['lessonPackage' => $package->id]))
            ->assertRedirect(route('login'));
        $this->get(route('admin.lesson-packages.data'))->assertRedirect(route('login'));
        $this->put(
            route('admin.lesson-packages.update', ['lessonPackage' => $package->id]),
            $this->updatePayload(['name' => 'Guest overwrite'])
        )->assertRedirect(route('login'));

        $this->assertSame(12, (int) $package->fresh()->lessons_count);
        $this->assertSame(120, (int) $package->fresh()->duration_days);
    }

    public function test_guest_json_requests_are_denied_and_not_server_error(): void
    {
        auth()->logout();

        $package = $this->makeFlexibleTwelve();

        foreach ([
            ['GET', route('admin.lesson-packages.index')],
            ['GET', route('admin.directories.lesson-packages.index')],
            ['GET', route('admin.lesson-packages.show', ['lessonPackage' => $package->id])],
            ['GET', route('admin.lesson-packages.data')],
            ['PUT', route('admin.lesson-packages.update', ['lessonPackage' => $package->id]), $this->updatePayload()],
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
        $this->actingAs($actor)->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $package = $this->makeFlexibleTwelve();

        $this->get(route('admin.lesson-packages.index'))->assertForbidden();
        $this->get(route('admin.directories.lesson-packages.index'))->assertForbidden();
        $this->getJson(route('admin.lesson-packages.show', ['lessonPackage' => $package->id]))->assertForbidden();
        $this->getJson(route('admin.lesson-packages.data'))->assertForbidden();
        $this->putJson(
            route('admin.lesson-packages.update', ['lessonPackage' => $package->id]),
            $this->updatePayload(['lessons_count' => 8])
        )->assertForbidden();

        $this->assertSame(12, (int) $package->fresh()->lessons_count);
    }

    public function test_manager_with_permission_can_open_pages_and_read_twelve_lessons_from_show_and_table(): void
    {
        $this->grantPermission('lessonPackages.view');
        $this->grantPermission('scheduleSlots.view');

        $package = $this->makeFlexibleTwelve();

        foreach ([
            route('admin.lesson-packages.index'),
            route('admin.directories.lesson-packages.index'),
        ] as $url) {
            $page = $this->get($url);
            $page->assertOk();
            $this->assertNotSame('', trim((string) $page->getContent()));
            $page->assertSee('id="edit_lessons_count"', false)
                ->assertSee('id="lessonPackageEditModal"', false);
        }

        $this->getJson(route('admin.lesson-packages.show', ['lessonPackage' => $package->id]), [
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('lesson_package.lessons_count', 12)
            ->assertJsonPath('lesson_package.duration_days', 120);

        $table = $this->getJson(route('admin.lesson-packages.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 100,
            'name' => 'Гибкий 12 занятий',
        ]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->json();

        $row = collect($table['data'] ?? [])->firstWhere('id', $package->id);
        $this->assertIsArray($row);
        $this->assertSame(12, (int) $row['lessons_count']);
        $this->assertSame(120, (int) $row['duration_days']);
    }

    public function test_show_of_foreign_partner_package_returns_not_found(): void
    {
        $this->grantPermission('lessonPackages.view');

        $foreign = LessonPackage::query()->create([
            'partner_id' => $this->foreignPartner->id,
            'name' => 'Чужой 12',
            'schedule_type' => 'flexible',
            'duration_days' => 120,
            'lessons_count' => 12,
            'price_cents' => 100000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'auto_attendance_enabled' => 0,
            'is_active' => 1,
        ]);

        $this->getJson(route('admin.lesson-packages.show', ['lessonPackage' => $foreign->id]))
            ->assertNotFound();
        $this->putJson(
            route('admin.lesson-packages.update', ['lessonPackage' => $foreign->id]),
            $this->updatePayload(['name' => 'Утечка'])
        )->assertNotFound();

        $this->assertSame(12, (int) $foreign->fresh()->lessons_count);
        $this->assertSame('Чужой 12', $foreign->fresh()->name);
    }
}
