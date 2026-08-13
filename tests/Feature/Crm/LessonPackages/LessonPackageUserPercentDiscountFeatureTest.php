<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\LessonPackages;

use App\Models\LessonPackage;
use App\Models\Team;
use App\Models\TeamScheduleSlot;
use App\Models\User;
use App\Models\UserLessonPackage;
use App\Services\Pricing\UserPercentDiscount;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Снимок персональной скидки на назначениях и в календаре школы.
 *
 * @see /docs/documentation/lesson-packages.html
 * @see /docs/documentation/admin-users.html#user-percent-discount
 */
final class LessonPackageUserPercentDiscountFeatureTest extends CrmTestCase
{
    private const WEEK_MONDAY = '2026-05-04';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->asAdmin();
        $this->grantPermission('lessonPackages.view');
        $this->grantPermission('setPrices.packageAssignments.view');
    }

    public function test_store_assignment_stamps_snapshot_when_fee_matches_catalog_formula(): void
    {
        $student = $this->studentWithDiscount();
        $package = $this->flexiblePackage(100000);

        $this->post(route('admin.lesson-packages.assignments.store'), [
            '_token' => csrf_token(),
            'user_id' => $student->id,
            'lesson_package_id' => $package->id,
            'fee_amount' => '900.00',
        ])->assertStatus(302);

        $this->assertDatabaseHas('user_lesson_packages', [
            'user_id' => $student->id,
            'lesson_package_id' => $package->id,
            'fee_amount_cents' => 90000,
            'discount_percent' => 10,
            'discount_comment' => 'Льгота',
        ]);
    }

    public function test_store_assignment_clears_snapshot_on_manual_override(): void
    {
        $student = $this->studentWithDiscount();
        $package = $this->flexiblePackage(100000);

        $this->post(route('admin.lesson-packages.assignments.store'), [
            '_token' => csrf_token(),
            'user_id' => $student->id,
            'lesson_package_id' => $package->id,
            'fee_amount' => '850.00',
        ])->assertStatus(302);

        $ulp = UserLessonPackage::query()
            ->where('user_id', $student->id)
            ->where('lesson_package_id', $package->id)
            ->firstOrFail();

        $this->assertSame(85000, (int) $ulp->fee_amount_cents);
        $this->assertNull($ulp->discount_percent);
        $this->assertNull($ulp->discount_comment);
    }

    public function test_assignments_data_includes_discount_tooltip(): void
    {
        $student = $this->studentWithDiscount();
        $package = $this->flexiblePackage(100000);
        $ulp = UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $package->id,
            'starts_at' => null,
            'ends_at' => null,
            'lessons_total' => 8,
            'lessons_remaining' => 8,
            'fee_amount_cents' => 90000,
            'discount_percent' => 10,
            'discount_comment' => 'Льгота',
            'created_by' => $this->user->id,
        ]);

        $rows = $this->getJson(route('admin.lesson-packages.assignments.data', [
            'draw' => 1,
            'start' => 0,
            'length' => 20,
        ]))->assertOk()->json('data');

        $row = collect($rows ?? [])->first(function ($item) use ($ulp) {
            return (int) ($item['id'] ?? 0) === (int) $ulp->id;
        });
        $this->assertNotNull($row);
        $this->assertSame(10, (int) $row['discount_percent']);
        $this->assertSame('Скидка 10%. Льгота', (string) $row['discount_tooltip']);
    }

    public function test_users_search_returns_current_card_discount(): void
    {
        $student = $this->studentWithDiscount();

        $results = $this->getJson(route('admin.lesson-packages.assignments.users-search', [
            'q' => $student->lastname,
            'context' => 'assign',
        ]))->assertOk()->json('results');

        $hit = collect($results)->firstWhere('id', (int) $student->id);
        $this->assertNotNull($hit);
        $this->assertSame(10, (int) $hit['discount_percent']);
        $this->assertSame('Льгота', (string) $hit['discount_comment']);
    }

    public function test_slot_bind_actions_fee_default_applies_current_student_discount(): void
    {
        $student = $this->studentWithDiscount();
        $slot = $this->mondaySlot();
        $template = LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Разовое цена',
            'schedule_type' => 'no_schedule',
            'duration_days' => 1,
            'lessons_count' => 1,
            'price_cents' => 123450,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);

        $expected = (float) \App\Support\Money::fromCents(
            UserPercentDiscount::payableCents(123450, 10)
        );

        $response = $this->getJson(route('admin.lesson-packages.school-schedule.slot-user-bind-actions', [
            'user_id' => $student->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => self::WEEK_MONDAY,
        ]))->assertOk();

        $this->assertSame($expected, (float) $response->json('single_lesson.templates.0.fee_amount_default'));
        $this->assertSame(10, (int) $response->json('single_lesson.templates.0.discount_percent'));
        $this->assertSame('Скидка 10%. Льгота', (string) $response->json('single_lesson.templates.0.discount_tooltip'));
        $this->assertSame((int) $template->id, (int) $response->json('single_lesson.templates.0.id'));
    }

    public function test_assignments_page_includes_discount_badge_helpers(): void
    {
        $this->withoutVite();

        $html = (string) $this->get(route('admin.lesson-packages.assignments'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('KidsCrmUserDiscount', $html);
        $this->assertStringContainsString('kids-user-discount-price-wrap', $html);
        $this->assertStringContainsString('ulpFeeRender', $html);
        $this->assertStringContainsString('ulpSyncFeeFromSelectedPackage', $html);
    }

    public function test_show_ajax_includes_discount_snapshot_for_edit_modal(): void
    {
        $student = $this->studentWithDiscount();
        $package = $this->flexiblePackage(100000);
        $ulp = UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $package->id,
            'lessons_total' => 8,
            'lessons_remaining' => 8,
            'fee_amount_cents' => 90000,
            'discount_percent' => 10,
            'discount_comment' => 'Льгота',
            'created_by' => $this->user->id,
        ]);

        $response = $this->withHeaders($this->ajaxHeaders())
            ->getJson(route('admin.lesson-packages.assignments.show', [
                'assignment' => $ulp->id,
            ]));

        $response->assertOk();
        $this->assertNotSame('', trim((string) $response->getContent()));
        $response->assertJsonPath('assignment.discount_percent', 10)
            ->assertJsonPath('assignment.discount_comment', 'Льгота')
            ->assertJsonPath('assignment.discount_tooltip', 'Скидка 10%. Льгота')
            ->assertJsonPath('assignment.fee_amount', 900);
    }

    public function test_update_ajax_keeps_snapshot_when_fee_still_matches_catalog_formula(): void
    {
        $student = $this->studentWithDiscount();
        $package = $this->flexiblePackage(100000);
        $ulp = UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $package->id,
            'lessons_total' => 8,
            'lessons_remaining' => 8,
            'fee_amount_cents' => 90000,
            'discount_percent' => 10,
            'discount_comment' => 'Льгота',
            'created_by' => $this->user->id,
        ]);

        $this->withHeaders($this->ajaxHeaders())
            ->putJson(route('admin.lesson-packages.assignments.update', [
                'assignment' => $ulp->id,
            ]), [
                'fee_amount' => '900.00',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $ulp->refresh();
        $this->assertSame(90000, (int) $ulp->fee_amount_cents);
        $this->assertSame(10, (int) $ulp->discount_percent);
        $this->assertSame('Льгота', (string) $ulp->discount_comment);
    }

    public function test_update_ajax_clears_snapshot_when_manager_overrides_fee(): void
    {
        $student = $this->studentWithDiscount();
        $package = $this->flexiblePackage(100000);
        $ulp = UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $package->id,
            'lessons_total' => 8,
            'lessons_remaining' => 8,
            'fee_amount_cents' => 90000,
            'discount_percent' => 10,
            'discount_comment' => 'Льгота',
            'created_by' => $this->user->id,
        ]);

        $this->withHeaders($this->ajaxHeaders())
            ->putJson(route('admin.lesson-packages.assignments.update', [
                'assignment' => $ulp->id,
            ]), [
                'fee_amount' => '850.00',
            ])
            ->assertOk();

        $ulp->refresh();
        $this->assertSame(85000, (int) $ulp->fee_amount_cents);
        $this->assertNull($ulp->discount_percent);
        $this->assertNull($ulp->discount_comment);
    }

    public function test_store_assignment_non_ajax_validation_redirects_with_fee_error(): void
    {
        $student = $this->studentWithDiscount();
        $package = $this->flexiblePackage(100000);

        $this->from(route('admin.lesson-packages.assignments'))
            ->post(route('admin.lesson-packages.assignments.store'), [
                '_token' => csrf_token(),
                'user_id' => $student->id,
                'lesson_package_id' => $package->id,
                'fee_amount' => '',
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['fee_amount']);

        $this->assertDatabaseMissing('user_lesson_packages', [
            'user_id' => $student->id,
            'lesson_package_id' => $package->id,
        ]);
    }

    public function test_school_calendar_page_includes_discount_badge_helpers(): void
    {
        $this->withoutVite();

        $html = (string) $this->get(route('admin.lesson-packages.school-schedule'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('KidsCrmUserDiscount', $html);
        $this->assertStringContainsString('syncSchoolCalSlotSingleFeeBadgeFromOption', $html);
        $this->assertStringContainsString('data-fee-default', $html);
    }

    /**
     * @return array<string, string>
     */
    private function ajaxHeaders(): array
    {
        return [
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ];
    }

    private function studentWithDiscount(): User
    {
        return User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $this->roleId('user'),
            'is_enabled' => 1,
            'lastname' => 'Скидкин',
            'name' => 'Ученик',
            'discount_percent' => 10,
            'discount_comment' => 'Льгота',
        ]);
    }

    private function flexiblePackage(int $priceCents): LessonPackage
    {
        return LessonPackage::query()->create([
            'partner_id' => $this->partner->id,
            'name' => 'Гибкий скидка',
            'schedule_type' => 'flexible',
            'duration_days' => 30,
            'lessons_count' => 8,
            'price_cents' => $priceCents,
            'freeze_enabled' => 0,
            'freeze_days' => 0,
            'is_active' => 1,
        ]);
    }

    private function mondaySlot(): TeamScheduleSlot
    {
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);

        return TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'location_id' => null,
            'weekday' => 1,
            'time_start' => '14:00',
            'time_end' => '15:00',
            'date_start' => '2026-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
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
}
