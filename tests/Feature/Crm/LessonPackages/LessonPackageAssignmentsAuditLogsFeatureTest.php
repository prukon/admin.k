<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Enums\AuditEvent;
use App\Models\LessonPackage;
use App\Models\MyLog;
use App\Models\Partner;
use App\Models\PartnerLegalEntity;
use App\Models\User;
use App\Models\UserLessonPackage;
use App\Models\UserLessonPackagePublicPayLink;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * История назначений абонементов: audit write + logs-data + UI markers.
 *
 * @see LessonPackagesAuditLogsFeatureTest
 */
final class LessonPackageAssignmentsAuditLogsFeatureTest extends CrmTestCase
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

    private function latestLog(AuditEvent $event): ?MyLog
    {
        return MyLog::query()
            ->where('partner_id', $this->partner->id)
            ->where('event', $event->value)
            ->latest('id')
            ->first();
    }

    /**
     * @return array{package: LessonPackage, student: User, assignment: UserLessonPackage}
     */
    private function seedAssignment(
        float $fee = 100.0,
        bool $isPaid = false,
        int $lessonsRemaining = 8,
    ): array {
        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'Тестов',
            'name' => 'Ученик',
            'is_enabled' => 1,
        ]);

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Аудит назначение',
            'schedule_type' => 'no_schedule',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 10000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        $assignment = UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $package->id,
            'starts_at' => null,
            'ends_at' => null,
            'lessons_total' => 8,
            'lessons_remaining' => $lessonsRemaining,
            'fee_amount' => number_format($fee, 2, '.', ''),
            'is_paid' => $isPaid,
            'created_by' => $this->user->id,
        ]);

        return [
            'package' => $package,
            'student' => $student,
            'assignment' => $assignment,
        ];
    }

    private function seedTbankForPartner(): void
    {
        $this->seedGlobalTbank([
            'terminal_key' => 'TERM_ASSIGN_AUDIT',
            'token_password' => 'PWD_ASSIGN_AUDIT',
            'e2c_terminal_key' => 'E2C_TERM',
            'e2c_token_password' => 'E2C_PWD',
        ]);

        PartnerLegalEntity::factory()
            ->for($this->partner)
            ->registered('SHOP-ASSIGN-AUDIT')
            ->create(['is_default' => true]);

        Partner::query()->whereKey($this->partner->id)->update([
            'tinkoff_partner_id' => null,
            'tax_id' => null,
        ]);
        $this->partner->refresh();
    }

    public function test_logs_data_returns_200_with_lesson_packages_view(): void
    {
        $this->grantPermission('lessonPackages.view');

        $this->getJson(route('logs.data.lesson-package-assignment', ['draw' => 1, 'start' => 0, 'length' => 10]))
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);
    }

    public function test_logs_data_returns_403_without_lesson_packages_view(): void
    {
        $actor = $this->createUserWithoutPermission('lessonPackages.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->getJson(route('logs.data.lesson-package-assignment', ['draw' => 1, 'start' => 0, 'length' => 10]))
            ->assertStatus(403);
    }

    public function test_guest_is_denied_on_logs_data(): void
    {
        Auth::logout();

        $response = $this->getJson(route('logs.data.lesson-package-assignment', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]));

        $this->assertContains(
            $response->getStatusCode(),
            [302, 401, 403, 419],
            'Гость на logs-data назначений: '.$response->getStatusCode()
        );
    }

    public function test_assignments_page_renders_history_button(): void
    {
        $this->grantPermission('lessonPackages.view');

        $this->get(route('admin.lesson-packages.assignments'))
            ->assertOk()
            ->assertSee('historyModal', false)
            ->assertSee('История', false)
            ->assertSee('showLogModal', false)
            ->assertSee('lesson-packages\/assignments\/logs-data', false);
    }

    public function test_store_writes_user_lesson_package_created_log(): void
    {
        $this->grantPermission('lessonPackages.view');

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'lastname' => 'Иванов',
            'name' => 'Пётр',
            'is_enabled' => 1,
        ]);

        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Пакет для создания',
            'schedule_type' => 'flexible',
            'duration_days' => 14,
            'lessons_count' => 4,
            'price_cents' => 20000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        $this->post(route('admin.lesson-packages.assignments.store'), [
            '_token' => csrf_token(),
            'user_id' => $student->id,
            'lesson_package_id' => $package->id,
            'fee_amount' => '250.00',
        ])->assertRedirect(route('admin.lesson-packages.assignments'));

        $log = $this->latestLog(AuditEvent::UserLessonPackageCreated);

        $this->assertNotNull($log);
        $this->assertSame(AuditEvent::UserLessonPackageCreated->level(), $log->level);
        $this->assertStringContainsString('Ученик: Иванов Пётр', (string) $log->description);
        $this->assertStringContainsString('Абонемент: Пакет для создания', (string) $log->description);
        $this->assertStringContainsString('Стоимость: 250 ₽', (string) $log->description);
        $this->assertSame((int) $student->id, (int) $log->user_id);
        $this->assertStringContainsString('Иванов Пётр', (string) $log->target_label);
    }

    public function test_update_writes_user_lesson_package_updated_log_with_fee_diff(): void
    {
        $this->grantPermission('lessonPackages.view');
        $ctx = $this->seedAssignment(100.0);

        $this->putJson(
            route('admin.lesson-packages.assignments.update', ['assignment' => $ctx['assignment']->id]),
            ['fee_amount' => '180.00'],
            ['X-Requested-With' => 'XMLHttpRequest']
        )->assertOk();

        $log = $this->latestLog(AuditEvent::UserLessonPackageUpdated);

        $this->assertNotNull($log);
        $this->assertStringContainsString('Стоимость: 100 ₽ → 180 ₽', (string) $log->description);
    }

    public function test_update_without_changes_does_not_write_log(): void
    {
        $this->grantPermission('lessonPackages.view');
        $ctx = $this->seedAssignment(100.0);

        $beforeUpdated = MyLog::query()
            ->where('event', AuditEvent::UserLessonPackageUpdated->value)
            ->count();
        $beforeManual = MyLog::query()
            ->where('event', AuditEvent::UserLessonPackageManualPaid->value)
            ->count();

        $this->putJson(
            route('admin.lesson-packages.assignments.update', ['assignment' => $ctx['assignment']->id]),
            ['fee_amount' => '100.00'],
            ['X-Requested-With' => 'XMLHttpRequest']
        )->assertOk();

        $this->assertSame(
            $beforeUpdated,
            MyLog::query()->where('event', AuditEvent::UserLessonPackageUpdated->value)->count()
        );
        $this->assertSame(
            $beforeManual,
            MyLog::query()->where('event', AuditEvent::UserLessonPackageManualPaid->value)->count()
        );
    }

    public function test_destroy_writes_user_lesson_package_deleted_log(): void
    {
        $this->grantPermission('lessonPackages.view');
        $ctx = $this->seedAssignment();

        $this->deleteJson(
            route('admin.lesson-packages.assignments.destroy', ['assignment' => $ctx['assignment']->id]),
            [],
            ['X-Requested-With' => 'XMLHttpRequest']
        )->assertOk();

        $log = $this->latestLog(AuditEvent::UserLessonPackageDeleted);

        $this->assertNotNull($log);
        $this->assertSame(AuditEvent::UserLessonPackageDeleted->level(), $log->level);
        $this->assertStringContainsString('Назначение абонемента удалено.', (string) $log->description);
        $this->assertStringContainsString('Аудит назначение', (string) $log->description);
    }

    public function test_destroy_blocked_does_not_write_log(): void
    {
        $this->grantPermission('lessonPackages.view');
        $ctx = $this->seedAssignment(lessonsRemaining: 7);

        $beforeCount = MyLog::query()
            ->where('event', AuditEvent::UserLessonPackageDeleted->value)
            ->count();

        $this->deleteJson(
            route('admin.lesson-packages.assignments.destroy', ['assignment' => $ctx['assignment']->id]),
            [],
            ['X-Requested-With' => 'XMLHttpRequest']
        )->assertStatus(422);

        $this->assertSame(
            $beforeCount,
            MyLog::query()->where('event', AuditEvent::UserLessonPackageDeleted->value)->count()
        );
    }

    public function test_manual_paid_writes_user_lesson_package_manual_paid_log(): void
    {
        $this->grantPermission('lessonPackages.view');
        $this->grantPermission('lessonPackages.manualPaid.manage');
        $ctx = $this->seedAssignment();

        $this->postJson(
            route('admin.lesson-packages.assignments.manual-paid', ['assignment' => $ctx['assignment']->id]),
            ['mode' => 'paid', 'comment' => 'Оплата наличными в кассе'],
            ['X-Requested-With' => 'XMLHttpRequest']
        )->assertOk();

        $log = $this->latestLog(AuditEvent::UserLessonPackageManualPaid);

        $this->assertNotNull($log);
        $this->assertStringContainsString('Статус оплаты: Не оплачен → Оплачен.', (string) $log->description);
        $this->assertStringContainsString('Комментарий: Оплата наличными в кассе', (string) $log->description);
    }

    public function test_public_pay_link_writes_issued_log(): void
    {
        $this->grantPermission('lessonPackages.view');
        $this->seedTbankForPartner();
        $ctx = $this->seedAssignment(500.0);

        $this->postJson(
            route('admin.lesson-packages.assignments.public-pay-link', ['assignment' => $ctx['assignment']->id]),
            [],
            ['X-Requested-With' => 'XMLHttpRequest']
        )->assertOk()
            ->assertJsonStructure(['url']);

        $log = $this->latestLog(AuditEvent::UserLessonPackagePublicPayLinkIssued);

        $this->assertNotNull($log);
        $this->assertStringContainsString('ссылка на оплату', mb_strtolower((string) $log->description));
        $this->assertTrue(
            UserLessonPackagePublicPayLink::query()
                ->where('user_lesson_package_id', $ctx['assignment']->id)
                ->exists()
        );
    }

    public function test_fee_change_rotates_public_pay_link_and_writes_log(): void
    {
        $this->grantPermission('lessonPackages.view');
        $this->seedTbankForPartner();
        $ctx = $this->seedAssignment(500.0);

        UserLessonPackagePublicPayLink::query()->create([
            'user_lesson_package_id' => $ctx['assignment']->id,
            'partner_id' => $this->partner->id,
            'token' => str_repeat('b', 64),
            'expires_at' => now()->addDays(7),
        ]);

        $this->putJson(
            route('admin.lesson-packages.assignments.update', ['assignment' => $ctx['assignment']->id]),
            ['fee_amount' => '600.00'],
            ['X-Requested-With' => 'XMLHttpRequest']
        )->assertOk()
            ->assertJsonPath('public_pay_url_rotated', true);

        $log = $this->latestLog(AuditEvent::UserLessonPackagePublicPayLinkIssued);

        $this->assertNotNull($log);
        $this->assertStringContainsString('перевыпущена', (string) $log->description);
    }

    public function test_logs_data_returns_written_assignment_event_in_table(): void
    {
        $this->grantPermission('lessonPackages.view');
        $ctx = $this->seedAssignment(100.0);

        $this->putJson(
            route('admin.lesson-packages.assignments.update', ['assignment' => $ctx['assignment']->id]),
            ['fee_amount' => '150.00'],
            ['X-Requested-With' => 'XMLHttpRequest']
        )->assertOk();

        $descriptions = collect($this->getJson(route('logs.data.lesson-package-assignment', [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
        ]))->json('data'))->pluck('description')->all();

        $this->assertTrue(
            collect($descriptions)->contains(
                fn (string $d): bool => str_contains($d, '100 ₽ → 150 ₽')
            ),
            'Ожидалась запись user_lesson_package.updated в logs-data.'
        );
    }

    public function test_logs_data_excludes_lesson_package_template_events(): void
    {
        $this->grantPermission('lessonPackages.view');

        MyLog::query()->create([
            'partner_id' => $this->partner->id,
            'author_id' => $this->user->id,
            'event' => AuditEvent::LessonPackageCreated->value,
            'level' => AuditEvent::LessonPackageCreated->level()->value,
            'description' => 'Шаблон не должен попасть в историю назначений',
            'type' => null,
            'action' => null,
            'created_at' => now(),
        ]);

        MyLog::query()->create([
            'partner_id' => $this->partner->id,
            'author_id' => $this->user->id,
            'event' => AuditEvent::UserLessonPackageCreated->value,
            'level' => AuditEvent::UserLessonPackageCreated->level()->value,
            'description' => 'Назначение должно быть видно',
            'type' => null,
            'action' => null,
            'created_at' => now(),
        ]);

        $descriptions = collect($this->getJson(route('logs.data.lesson-package-assignment', [
            'draw' => 1,
            'start' => 0,
            'length' => 50,
        ]))->json('data'))->pluck('description')->all();

        $this->assertTrue(
            collect($descriptions)->contains(fn (string $d): bool => str_contains($d, 'Назначение должно быть видно'))
        );
        $this->assertFalse(
            collect($descriptions)->contains(fn (string $d): bool => str_contains($d, 'Шаблон не должен попасть'))
        );
    }
}
