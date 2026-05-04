<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\LessonPackage;
use App\Models\UserLessonPackage;
use App\Models\UserLessonPackageFreeze;
use App\Models\UserLessonPackageTimeSlot;
use App\Services\Payments\UserLessonPackageFeePaymentResolver;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Feature\Crm\CrmTestCase;

final class LessonPackageAssignmentsManageFeatureTest extends CrmTestCase
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
        $permId = $this->permissionId($permissionName);

        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $this->user->role_id,
            'permission_id' => $permId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createAssignment(float $fee = 100.0, bool $isPaid = false, ?float $lessonsRemaining = null): UserLessonPackage
    {
        $package = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Управление назначением',
            'schedule_type' => 'no_schedule',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => 10000,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        $remaining = $lessonsRemaining ?? (float) $package->lessons_count;

        return UserLessonPackage::query()->create([
            'user_id' => $this->user->id,
            'lesson_package_id' => $package->id,
            'starts_at' => null,
            'ends_at' => null,
            'lessons_total' => (int) $package->lessons_count,
            'lessons_remaining' => (int) $remaining,
            'fee_amount' => number_format($fee, 2, '.', ''),
            'is_paid' => $isPaid,
            'created_by' => $this->user->id,
        ]);
    }

    public function test_show_assignment_json_ok(): void
    {
        $this->grantPermission('lessonPackages.view');
        $ulp = $this->createAssignment();

        $this->getJson(route('admin.lesson-packages.assignments.show', ['assignment' => $ulp->id]))
            ->assertOk()
            ->assertJsonPath('assignment.id', (int) $ulp->id)
            ->assertJsonPath('assignment.fee_editable', true);
    }

    public function test_show_assignment_not_found_for_foreign_partner(): void
    {
        $this->grantPermission('lessonPackages.view');
        $ulp = $this->createAssignment();

        $permId = $this->permissionId('lessonPackages.view');
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->foreignPartner->id,
            'role_id' => $this->foreignUser->role_id,
            'permission_id' => $permId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->asForeignUser();

        $this->getJson(route('admin.lesson-packages.assignments.show', ['assignment' => $ulp->id]))
            ->assertNotFound();
    }

    public function test_update_fee_success_when_unpaid(): void
    {
        $this->grantPermission('lessonPackages.view');
        $ulp = $this->createAssignment(100.0);

        $this->putJson(route('admin.lesson-packages.assignments.update', ['assignment' => $ulp->id]), [
            'fee_amount' => '250.50',
        ])
            ->assertOk()
            ->assertJsonPath('assignment.fee_amount', '250.50');

        $this->assertSame('250.50', (string) UserLessonPackage::query()->whereKey($ulp->id)->value('fee_amount'));
    }

    public function test_update_fee_forbidden_when_gateway_paid(): void
    {
        $this->grantPermission('lessonPackages.view');
        $ulp = $this->createAssignment(100.0, true);

        $this->putJson(route('admin.lesson-packages.assignments.update', ['assignment' => $ulp->id]), [
            'fee_amount' => '200.00',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['fee_amount']);
    }

    public function test_manual_paid_blocks_online_resolver(): void
    {
        $ulp = $this->createAssignment(150.0);
        $ulp->forceFill([
            'is_manual_paid' => true,
            'manual_paid_by' => $this->user->id,
            'manual_paid_at' => now(),
            'manual_paid_note' => 'Оплата наличными у секретаря',
        ]);
        $ulp->save();

        try {
            app(UserLessonPackageFeePaymentResolver::class)->resolveOrAbort(
                (int) $this->user->id,
                (int) $this->partner->id,
                (int) $ulp->id,
            );
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
    }

    public function test_set_manual_paid_requires_permission(): void
    {
        $this->grantPermission('lessonPackages.view');
        $ulp = $this->createAssignment();

        $this->putJson(route('admin.lesson-packages.assignments.update', ['assignment' => $ulp->id]), [
            'fee_amount' => '100.00',
            'payment_status' => 'paid',
            'payment_comment' => 'Тестовый комментарий для ручной отметки',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['payment_status']);
    }

    public function test_set_manual_paid_success_with_permission(): void
    {
        $this->grantPermission('lessonPackages.view');
        $this->grantPermission('lessonPackages.manualPaid.manage');
        $ulp = $this->createAssignment();

        $this->putJson(route('admin.lesson-packages.assignments.update', ['assignment' => $ulp->id]), [
            'fee_amount' => '100.00',
            'payment_status' => 'paid',
            'payment_comment' => 'Тестовый комментарий для ручной отметки',
        ])
            ->assertOk()
            ->assertJsonPath('assignment.effective_is_paid', true);

        $ulp->refresh();
        $this->assertTrue((bool) $ulp->is_manual_paid);
        $this->assertSame('Тестовый комментарий для ручной отметки', (string) $ulp->manual_paid_note);
    }

    public function test_update_fee_and_mark_unpaid_in_one_request(): void
    {
        $this->grantPermission('lessonPackages.view');
        $this->grantPermission('lessonPackages.manualPaid.manage');
        $ulp = $this->createAssignment(100.0, true);

        $this->putJson(route('admin.lesson-packages.assignments.update', ['assignment' => $ulp->id]), [
            'fee_amount' => '150.00',
            'payment_status' => 'unpaid',
            'payment_comment' => 'Отмена оплаты, сумма пересчитана',
        ])
            ->assertOk()
            ->assertJsonPath('assignment.fee_amount', '150.00')
            ->assertJsonPath('assignment.effective_is_paid', false);

        $ulp->refresh();
        $this->assertSame('150.00', (string) $ulp->fee_amount);
        $this->assertFalse($ulp->effective_is_paid);
    }

    public function test_delete_success_when_full_balance(): void
    {
        $this->grantPermission('lessonPackages.view');
        $ulp = $this->createAssignment();

        UserLessonPackageTimeSlot::query()->create([
            'user_lesson_package_id' => $ulp->id,
            'weekday' => 1,
            'time_start' => '18:00',
            'time_end' => '19:00',
        ]);

        UserLessonPackageFreeze::query()->create([
            'user_lesson_package_id' => $ulp->id,
            'date' => '2026-04-07',
            'team_schedule_slot_id' => null,
            'user_lesson_package_time_slot_id' => null,
            'created_by' => $this->user->id,
            'reason' => 'test',
        ]);

        $this->deleteJson(route('admin.lesson-packages.assignments.destroy', ['assignment' => $ulp->id]))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('user_lesson_packages', ['id' => $ulp->id]);
        $this->assertSame(0, UserLessonPackageTimeSlot::query()->where('user_lesson_package_id', $ulp->id)->count());
        $this->assertSame(0, UserLessonPackageFreeze::query()->where('user_lesson_package_id', $ulp->id)->count());
    }

    public function test_delete_rejects_when_lessons_consumed(): void
    {
        $this->grantPermission('lessonPackages.view');
        $ulp = $this->createAssignment(100.0, false, 7.0);

        $this->deleteJson(route('admin.lesson-packages.assignments.destroy', ['assignment' => $ulp->id]))
            ->assertStatus(422)
            ->assertJson(['success' => false]);

        $this->assertDatabaseHas('user_lesson_packages', ['id' => $ulp->id]);
    }
}
