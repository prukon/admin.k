<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Users;

use App\Models\LessonOccurrenceStatus;
use App\Models\LessonPackage;
use App\Models\Role;
use App\Models\Team;
use App\Models\TeamScheduleSlot;
use App\Models\User;
use App\Models\UserLessonOccurrenceStatusEvent;
use App\Models\UserPrice;
use App\Support\Money;
use Database\Seeders\LessonOccurrenceStatusesSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Смена процента скидки: выбор, пересчитывать ли неоплаченные users_prices.
 *
 * @see /docs/documentation/admin-users.html#user-percent-discount
 */
final class UserUnpaidPriceDiscountRecalcFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->asAdmin();
        $this->grantPermission($this->user, 'users.view');
        $this->grantPermission($this->user, 'users.discount.manage');
    }

    public function test_users_page_renders_empty_discount_price_choice(): void
    {
        $this->withoutVite();

        $html = (string) $this->get(route('admin.user1'))->assertOk()->getContent();

        $this->assertStringContainsString('id="discountUnpaidPricesModal"', $html);
        $this->assertStringContainsString('id="discount-unpaid-prices-choice"', $html);
        $this->assertStringContainsString('<option value="" selected>Выберите</option>', $html);
        $this->assertStringContainsString('id="discount-unpaid-prices-continue" disabled', $html);
        $this->assertStringContainsString('unpaid-price-discount-preview', $html);
        $this->assertStringNotContainsString('id="discountUnpaidPricesModal"', substr($html, 0, strpos($html, 'id="discountUnpaidPricesModal"')));
        $modal = substr($html, (int) strpos($html, 'id="discountUnpaidPricesModal"'), 800);
        $this->assertStringNotContainsString('modal-fullscreen', $modal);
        $this->assertStringNotContainsString('modal-xl', $modal);
    }

    public function test_preview_lists_formula_unpaid_prepaid_and_skips_manual_and_paid(): void
    {
        $student = $this->createStudent(['discount_percent' => 10, 'discount_comment' => 'Льгота']);
        $team = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Младшая']);
        $package = LessonPackage::factory()->forPartner($this->partner->id)->create([
            'schedule_type' => 'fixed',
            'price_cents' => 100000,
        ]);

        $this->insertUserPrice($student, [
            'new_month' => '2026-09-01',
            'lesson_package_id' => $package->id,
            'price_cents' => 90000,
            'discount_percent' => 10,
            'discount_comment' => 'Льгота',
            'is_paid' => 0,
        ], $team);

        $this->insertUserPrice($student, [
            'new_month' => '2026-10-01',
            'lesson_package_id' => $package->id,
            'price_cents' => 80000,
            'discount_percent' => null,
            'is_paid' => 0,
        ], $team);

        $this->insertUserPrice($student, [
            'new_month' => '2026-11-01',
            'lesson_package_id' => $package->id,
            'price_cents' => 90000,
            'discount_percent' => 10,
            'is_paid' => 1,
        ], $team);

        $this->getJson(route('admin.user.unpaid-price-discount-preview', $student).'?discount_percent=20')
            ->assertOk()
            ->assertJsonCount(1, 'rows')
            ->assertJsonPath('rows.0.kind_label', 'Предоплата')
            ->assertJsonPath('rows.0.team_title', 'Младшая')
            ->assertJsonPath('rows.0.current_label', Money::formatRub(90000).' ₽')
            ->assertJsonPath('rows.0.new_label', Money::formatRub(80000).' ₽');
    }

    public function test_update_requires_choice_when_unpaid_formula_price_exists(): void
    {
        $student = $this->createStudent(['discount_percent' => 10, 'discount_comment' => 'Льгота']);
        $this->seedFormulaPrice($student, 90000, 10);

        $this->patchJson(route('admin.user.update', $student), $this->payload($student, [
            'discount_percent' => 20,
            'discount_comment' => 'Льгота',
        ]))->assertStatus(422)
            ->assertJsonValidationErrors(['recalculate_unpaid_prices']);

        $this->assertSame(10, (int) $student->fresh()->discount_percent);
        $this->assertSame(90000, (int) UserPrice::query()->where('user_id', $student->id)->value('price_cents'));
    }

    public function test_update_skip_keeps_prices_and_apply_recalculates_only_formula_unpaid(): void
    {
        $student = $this->createStudent(['discount_percent' => 10, 'discount_comment' => 'Льгота']);
        $formula = $this->seedFormulaPrice($student, 90000, 10, '2026-09-01');
        $manual = $this->seedFormulaPrice($student, 70000, null, '2026-10-01');
        $paid = $this->seedFormulaPrice($student, 90000, 10, '2026-11-01', true);

        $this->patchJson(route('admin.user.update', $student), $this->payload($student, [
            'discount_percent' => 20,
            'discount_comment' => 'Новое',
            'recalculate_unpaid_prices' => '0',
        ]))->assertOk();

        $this->assertSame(20, (int) $student->fresh()->discount_percent);
        $this->assertSame(90000, (int) $formula->fresh()->price_cents);
        $this->assertSame(10, (int) $formula->fresh()->discount_percent);

        $this->patchJson(route('admin.user.update', $student->fresh()), $this->payload($student->fresh(), [
            'discount_percent' => 50,
            'discount_comment' => 'Новое',
            'recalculate_unpaid_prices' => '1',
        ]))->assertOk();

        $formula->refresh();
        $this->assertSame(50000, (int) $formula->price_cents);
        $this->assertSame(50, (int) $formula->discount_percent);
        $this->assertSame('Новое', $formula->discount_comment);
        $this->assertSame(70000, (int) $manual->fresh()->price_cents);
        $this->assertNull($manual->fresh()->discount_percent);
        $this->assertSame(90000, (int) $paid->fresh()->price_cents);
        $this->assertSame(10, (int) $paid->fresh()->discount_percent);
    }

    public function test_comment_only_does_not_require_choice_or_change_price(): void
    {
        $student = $this->createStudent(['discount_percent' => 10, 'discount_comment' => 'Было']);
        $row = $this->seedFormulaPrice($student, 90000, 10);

        $this->patchJson(route('admin.user.update', $student), $this->payload($student, [
            'discount_percent' => 10,
            'discount_comment' => 'Другое основание',
        ]))->assertOk();

        $this->assertSame('Другое основание', $student->fresh()->discount_comment);
        $this->assertSame(90000, (int) $row->fresh()->price_cents);
        $this->assertSame('Льгота', $row->fresh()->discount_comment);
    }

    public function test_clearing_percent_restores_catalog_on_unpaid_formula_row(): void
    {
        $student = $this->createStudent(['discount_percent' => 10, 'discount_comment' => 'Льгота']);
        $row = $this->seedFormulaPrice($student, 90000, 10);

        $this->patchJson(route('admin.user.update', $student), $this->payload($student, [
            'discount_percent' => 0,
            'discount_comment' => 'Игнор',
            'recalculate_unpaid_prices' => '1',
        ]))->assertOk();

        $row->refresh();
        $this->assertNull($student->fresh()->discount_percent);
        $this->assertSame(100000, (int) $row->price_cents);
        $this->assertNull($row->discount_percent);
    }

    public function test_preview_recalculates_postpay_from_visits_and_skips_manual_postpay(): void
    {
        $student = $this->createStudent(['discount_percent' => 10, 'discount_comment' => 'Льгота']);
        $team = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Постоплата']);
        $package = LessonPackage::factory()->forPartner($this->partner->id)->create([
            'schedule_type' => 'postpay',
            'price_cents' => 100000,
        ]);

        $this->insertUserPrice($student, [
            'new_month' => '2026-09-01',
            'lesson_package_id' => $package->id,
            'price_cents' => 90000,
            'discount_percent' => 10,
            'discount_comment' => 'Льгота',
            'is_paid' => 0,
        ], $team);
        $this->insertUserPrice($student, [
            'new_month' => '2026-10-01',
            'lesson_package_id' => $package->id,
            'price_cents' => 150000,
            'discount_percent' => null,
            'is_paid' => 0,
        ], $team);
        $this->seedConsumingVisit($student, $team, '2026-09-08');

        $this->getJson(route('admin.user.unpaid-price-discount-preview', $student).'?discount_percent=20')
            ->assertOk()
            ->assertJsonCount(1, 'rows')
            ->assertJsonPath('rows.0.kind_label', 'Постоплата')
            ->assertJsonPath('rows.0.new_label', Money::formatRub(80000).' ₽');

        $this->patchJson(route('admin.user.update', $student), $this->payload($student, [
            'discount_percent' => 20,
            'discount_comment' => 'Льгота',
            'recalculate_unpaid_prices' => '1',
        ]))->assertOk();

        $rows = UserPrice::query()->where('user_id', $student->id)->orderBy('new_month')->get();
        $this->assertSame(80000, (int) $rows[0]->price_cents);
        $this->assertSame(20, (int) $rows[0]->discount_percent);
        $this->assertSame(150000, (int) $rows[1]->price_cents);
        $this->assertNull($rows[1]->discount_percent);
    }

    public function test_preview_forbidden_without_discount_permission(): void
    {
        $student = $this->createStudent();
        $this->revokePermission($this->user, 'users.discount.manage');

        $this->getJson(route('admin.user.unpaid-price-discount-preview', $student).'?discount_percent=10')
            ->assertForbidden();
    }

    private function seedFormulaPrice(User $student, int $priceCents, ?int $percent, string $month = '2026-09-01', bool $paid = false): UserPrice
    {
        $team = Team::factory()->create(['partner_id' => $this->partner->id]);
        $package = LessonPackage::factory()->forPartner($this->partner->id)->create([
            'schedule_type' => 'fixed',
            'price_cents' => 100000,
        ]);
        $this->insertUserPrice($student, [
            'new_month' => $month,
            'lesson_package_id' => $package->id,
            'price_cents' => $priceCents,
            'discount_percent' => $percent,
            'discount_comment' => $percent ? 'Льгота' : null,
            'is_paid' => $paid ? 1 : 0,
        ], $team);

        return UserPrice::query()
            ->where('user_id', $student->id)
            ->whereDate('new_month', $month)
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function payload(User $student, array $extra = []): array
    {
        return array_merge([
            'name' => $student->name,
            'lastname' => $student->lastname,
            'role_id' => $student->role_id,
            'is_enabled' => $student->is_enabled ? '1' : '0',
        ], $extra);
    }

    private function grantPermission(User $actor, string $permissionName): void
    {
        DB::table('permission_role')->insertOrIgnore([
            'partner_id' => $this->partner->id,
            'role_id' => $actor->role_id,
            'permission_id' => $this->permissionId($permissionName),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function revokePermission(User $actor, string $permissionName): void
    {
        DB::table('permission_role')
            ->where('partner_id', $this->partner->id)
            ->where('role_id', $actor->role_id)
            ->where('permission_id', $this->permissionId($permissionName))
            ->delete();
    }

    private function seedConsumingVisit(User $student, Team $team, string $date): void
    {
        LessonOccurrenceStatusesSeeder::ensureForPartner((int) $this->partner->id);
        $attendedId = LessonOccurrenceStatus::attendedIdForPartner((int) $this->partner->id);
        $this->assertNotNull($attendedId);

        $slot = TeamScheduleSlot::query()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $team->id,
            'weekday' => 2,
            'time_start' => '10:00:00',
            'time_end' => '11:00:00',
            'date_start' => '2020-01-01',
            'date_end' => '9999-12-31',
            'is_enabled' => 1,
        ]);

        UserLessonOccurrenceStatusEvent::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $student->id,
            'team_schedule_slot_id' => $slot->id,
            'occurrence_date' => $date,
            'user_lesson_package_id' => null,
            'lesson_occurrence_status_id' => $attendedId,
            'created_by' => $this->user->id,
        ]);
    }

    private function createStudent(array $attrs = []): User
    {
        return User::factory()->create(array_merge([
            'partner_id' => $this->partner->id,
            'role_id' => (int) Role::query()->where('name', 'user')->value('id'),
        ], $attrs));
    }
}
