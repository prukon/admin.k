<?php

namespace Tests\Feature\Crm\Dashboard;

use App\Models\LessonPackage;
use App\Models\Team;
use App\Models\User;
use App\Models\UserLessonPackage;
use Illuminate\Support\Facades\Auth;
use Tests\Feature\Crm\StudentTeams\StudentTeamPivotTestCase;

/**
 * Консоль (/cabinet): блок «Назначенные абонементы» (user_lesson_packages) и защита сумм
 * от обнуления refreshPrice() при пересборке сезонов.
 *
 * @see resources/views/dashboard.blade.php
 * @see app/Http/Controllers/DashboardController.php
 */
final class DashboardLessonPackagesFeatureTest extends StudentTeamPivotTestCase
{
    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config(['broadcasting.default' => 'null']);

        $this->team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Cabinet-LP-Team',
        ]);
    }

    public function test_cabinet_shows_assigned_lesson_packages_with_formatted_fee_amount(): void
    {
        $student = $this->makeStudentWithTeams([$this->team]);
        $this->grantPermissionForUser($student, 'setPrices.cabinetPackages.fixed.view');
        $ulp = $this->createAssignment($student, 12_500.50, 'Зимний пакет');

        $html = $this->cabinetHtmlFor($student);

        $this->assertStringContainsString('Назначенные абонементы', $html);
        $this->assertStringContainsString('Зимний пакет', $html);
        $this->assertStringContainsString('custom-payment-price', $html);
        $this->assertStringContainsString(
            'name="user_lesson_package_id" value="' . $ulp->id . '"',
            $html
        );
        $this->assertStringContainsString('name="payment_kind" value="lesson_package"', $html);
        $this->assertStringContainsString('<span class="price-value">12501<span', $html);
        $this->assertStringContainsString('name="outSum" value="12500.50"', $html);
        $this->assertStringContainsString('>Оплатить<', $html);
    }

    public function test_cabinet_hides_lesson_packages_with_zero_fee_amount(): void
    {
        $student = $this->makeStudentWithTeams([$this->team]);
        $this->grantPermissionForUser($student, 'setPrices.cabinetPackages.fixed.view');
        $this->createAssignment($student, 0, 'Бесплатный пакет');

        $html = $this->cabinetHtmlFor($student);

        $this->assertStringNotContainsString('Назначенные абонементы', $html);
        $this->assertStringNotContainsString('Бесплатный пакет', $html);
    }

    public function test_cabinet_shows_paid_lesson_package_with_oplacheno_state(): void
    {
        $student = $this->makeStudentWithTeams([$this->team]);
        $this->grantPermissionForUser($student, 'setPrices.cabinetPackages.fixed.view');
        $this->createAssignment($student, 3_000, 'Оплаченный пакет', [
            'is_paid' => true,
        ]);

        $html = $this->cabinetHtmlFor($student);

        $this->assertStringContainsString('Назначенные абонементы', $html);
        $this->assertStringContainsString('<span class="price-value">3000<span', $html);
        $this->assertStringContainsString('buttonPaided', $html);
        $this->assertStringContainsString('Оплачено', $html);
    }

    public function test_cabinet_refresh_price_resets_only_season_cells_not_lesson_package_amounts(): void
    {
        $student = $this->makeStudentWithTeams([$this->team]);
        $this->grantPermissionForUser($student, 'setPrices.cabinetPackages.fixed.view');
        $this->createAssignment($student, 8_800, 'Пакет для JS-guard');

        $html = $this->cabinetHtmlFor($student);

        $this->assertStringContainsString(
            "document.querySelectorAll('.seasons .border_price .price-amount')",
            $html
        );
        $this->assertStringContainsString(
            "document.querySelectorAll('.seasons .border_price .new-main-button-wrap button')",
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            "/function refreshPrice\(\)\s*\{[^}]*querySelectorAll\('\.price-value'\)/s",
            $html,
            'refreshPrice() не должен обнулять все .price-value на странице (в т.ч. абонементы).'
        );
    }

    public function test_cabinet_shows_lesson_package_amount_when_student_has_multiple_teams(): void
    {
        $teamB = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Cabinet-LP-Team-B',
        ]);

        $student = $this->makeStudentWithTeams([$this->team, $teamB]);
        $this->grantPermissionForUser($student, 'setPrices.cabinetPackages.fixed.view');
        $this->createAssignment($student, 15_000, 'Мультигрупповой пакет');

        $html = $this->cabinetHtmlFor($student);

        $this->assertStringContainsString('<span class="price-value">15000<span', $html);
        $this->assertStringContainsString('Мультигрупповой пакет', $html);
        $this->assertStringContainsString('id="dashboard-lesson-packages"', $html);
        $this->assertStringContainsString('id="dashboard-active-team"', $html);
        $this->assertStringContainsString('data-ulp-team-id="'.$this->team->id.'"', $html);
        $this->assertStringContainsString('filterLessonPackagesByTeam', $html);
        $this->assertStringContainsString('var dashboardTeamSwitcherEnabled = true', $html);

        $switcherPos = strpos($html, 'id="dashboard-active-team"');
        $packagesPos = strpos($html, 'id="dashboard-lesson-packages"');
        $this->assertNotFalse($switcherPos);
        $this->assertNotFalse($packagesPos);
        $this->assertLessThan($packagesPos, $switcherPos);
    }

    public function test_cabinet_shows_team_switcher_for_packages_without_seasons_permission(): void
    {
        $teamB = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Cabinet-LP-Team-NoSeasons-B',
        ]);

        $student = $this->makeStudentWithTeams([$this->team, $teamB]);
        $this->revokeCabinetSeasonsPermission($student);
        $this->grantPermissionForUser($student, 'setPrices.cabinetPackages.single.view');
        $this->createAssignment($student, 9_100, 'Разовое без сезонов', [], [
            'schedule_type' => LessonPackage::SCHEDULE_TYPE_NO_SCHEDULE,
            'duration_days' => 1,
            'lessons_count' => 1,
        ]);

        $html = $this->cabinetHtmlFor($student->fresh());

        $this->assertStringNotContainsString('class="row seasons"', $html);
        $this->assertStringContainsString('id="dashboard-active-team"', $html);
        $this->assertStringContainsString('Выбор группы', $html);
        $this->assertStringContainsString('Назначенные абонементы', $html);
        $this->assertStringContainsString('Разовое без сезонов', $html);
        $this->assertStringContainsString('var dashboardSeasonsEnabled = false', $html);
        $this->assertStringContainsString('var dashboardTeamSwitcherEnabled = true', $html);
    }

    public function test_cabinet_hides_team_switcher_without_seasons_when_no_packages(): void
    {
        $teamB = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Cabinet-LP-Empty-B',
        ]);

        $student = $this->makeStudentWithTeams([$this->team, $teamB]);
        $this->revokeCabinetSeasonsPermission($student);
        $this->grantPermissionForUser($student, 'setPrices.cabinetPackages.fixed.view');

        $html = $this->cabinetHtmlFor($student->fresh());

        $this->assertStringNotContainsString('id="dashboard-active-team"', $html);
        $this->assertStringNotContainsString('Назначенные абонементы', $html);
        $this->assertStringContainsString('var dashboardTeamSwitcherEnabled = false', $html);
    }

    public function test_cabinet_keeps_packages_without_team_id_when_student_has_single_team(): void
    {
        $student = $this->makeStudentWithTeams([$this->team]);
        $this->grantPermissionForUser($student, 'setPrices.cabinetPackages.fixed.view');
        $this->createAssignment($student, 3_300, 'Без группы на назначении', [
            'team_id' => null,
        ]);

        $html = $this->cabinetHtmlFor($student);

        $this->assertStringNotContainsString('id="dashboard-active-team"', $html);
        $this->assertStringContainsString('Без группы на назначении', $html);
        $this->assertStringContainsString('data-ulp-team-id=""', $html);
        $this->assertStringContainsString('var dashboardTeamSwitcherEnabled = false', $html);
    }

    public function test_cabinet_does_not_show_lesson_packages_of_foreign_partner_student(): void
    {
        $foreignTeam = Team::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'title'      => 'Foreign-LP',
        ]);
        $foreignStudent = $this->makeStudentWithTeams([$foreignTeam], [
            'partner_id' => $this->foreignPartner->id,
        ]);

        $package = LessonPackage::factory()->forPartner($this->foreignPartner->id)->create([
            'name' => 'Чужой абонемент',
        ]);
        UserLessonPackage::query()->create([
            'user_id'           => $foreignStudent->id,
            'lesson_package_id' => $package->id,
            'team_id'           => $foreignTeam->id,
            'lessons_total'     => (int) $package->lessons_count,
            'lessons_remaining' => (int) $package->lessons_count,
            'fee_amount_cents'        => 999900,
            'is_paid'           => false,
        ]);

        $localStudent = $this->makeStudentWithTeams([$this->team]);
        $this->grantPermissionForUser($localStudent, 'setPrices.cabinetPackages.fixed.view');
        $this->createAssignment($localStudent, 1_000, 'Свой абонемент');

        $html = $this->cabinetHtmlFor($localStudent);

        $this->assertStringContainsString('Свой абонемент', $html);
        $this->assertStringNotContainsString('Чужой абонемент', $html);
        $this->assertStringNotContainsString('<span class="price-value">9999<span', $html);
    }

    public function test_lesson_package_payment_page_uses_db_fee_not_cabinet_out_sum_override(): void
    {
        $student = $this->makeStudentWithTeams([$this->team]);
        $ulp = $this->createAssignment($student, 4_440, 'Пакет для оплаты');

        $this->actingAs($student);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);

        $this->post(route('payment'), [
            'payment_kind'            => 'lesson_package',
            'user_lesson_package_id'  => $ulp->id,
            'paymentDate'             => 'Абонемент: ignored',
            'outSum'                  => '1.00',
        ])
            ->assertOk()
            ->assertViewIs('payment.paymentUser')
            ->assertViewHas('outSum', '4440.00')
            ->assertViewHas('paymentKind', 'lesson_package')
            ->assertViewHas('userLessonPackageId', (int) $ulp->id);
    }

    public function test_guest_is_denied_on_cabinet_lesson_packages_page(): void
    {
        Auth::logout();

        $response = $this->get(route('dashboard'));

        $this->assertContains($response->getStatusCode(), [302, 401, 403, 419]);
        $this->assertNotEquals(500, $response->getStatusCode());
        $this->assertNotEquals(200, $response->getStatusCode());
    }

    public function test_user_without_dashboard_view_gets_403_on_cabinet(): void
    {
        $student = $this->makeStudentWithTeams([$this->team]);
        $this->createAssignment($student, 2_000, 'Пакет без доступа');

        $actor = $this->createUserWithoutPermission('dashboard.view', $this->partner);
        $this->actingAs($actor);
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->get(route('dashboard'))->assertForbidden();
    }

    public function test_student_with_dashboard_view_gets_200_on_cabinet_with_lesson_packages(): void
    {
        $student = $this->makeStudentWithTeams([$this->team]);
        $this->grantPermissionForUser($student, 'setPrices.cabinetPackages.fixed.view');
        $this->createAssignment($student, 6_500, 'Доступный пакет');

        $this->actingAs($student);
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Назначенные абонементы', false)
            ->assertSee('Доступный пакет', false)
            ->assertSee('6500', false);
    }

    public function test_cabinet_shows_single_lesson_package_date_as_day_month_year(): void
    {
        $student = $this->makeStudentWithTeams([$this->team]);
        $this->grantPermissionForUser($student, 'setPrices.cabinetPackages.single.view');
        $this->createAssignment($student, 2_500, 'Разовое занятие', [
            'starts_at' => '2026-08-11',
            'ends_at' => '2026-08-12',
        ], [
            'schedule_type' => LessonPackage::SCHEDULE_TYPE_NO_SCHEDULE,
            'duration_days' => 1,
            'lessons_count' => 1,
        ]);

        $html = $this->cabinetHtmlFor($student);

        $this->assertStringContainsString('Разовое занятие', $html);
        $this->assertStringContainsString('11 августа 2026', $html);
        $this->assertStringNotContainsString('11.08.2026', $html);
        $this->assertStringNotContainsString('12.08.2026', $html);
    }

    public function test_cabinet_shows_regular_lesson_package_as_date_range(): void
    {
        $student = $this->makeStudentWithTeams([$this->team]);
        $this->grantPermissionForUser($student, 'setPrices.cabinetPackages.fixed.view');
        $this->createAssignment($student, 8_000, 'Фикс 8 занятий', [
            'starts_at' => '2026-08-11',
            'ends_at' => '2026-09-12',
        ], [
            'schedule_type' => LessonPackage::SCHEDULE_TYPE_FIXED,
        ]);

        $html = $this->cabinetHtmlFor($student);

        $this->assertStringContainsString('Фикс 8 занятий', $html);
        $this->assertStringContainsString('11.08.2026 — 12.09.2026', $html);
        $this->assertStringNotContainsString('11 августа 2026', $html);
    }

    public function test_cabinet_hides_assigned_lesson_packages_without_type_permission(): void
    {
        $student = $this->makeStudentWithTeams([$this->team]);
        $this->createAssignment($student, 7_700, 'Скрытый пакет');

        $html = $this->cabinetHtmlFor($student);

        $this->assertStringNotContainsString('Назначенные абонементы', $html);
        $this->assertStringNotContainsString('Скрытый пакет', $html);
    }

    public function test_get_user_details_does_not_return_500_for_student_with_lesson_packages(): void
    {
        $student = $this->makeStudentWithTeams([$this->team]);
        $this->createAssignment($student, 2_500, 'AJAX пакет');

        $this->actingAs($student);
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->getJson(route('getUserDetails', ['userId' => $student->id]))
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @param  array<string, mixed>  $packageOverrides
     */
    private function createAssignment(
        User $student,
        float $feeAmount,
        string $packageName,
        array $overrides = [],
        array $packageOverrides = [],
    ): UserLessonPackage {
        $package = LessonPackage::factory()->forPartner($this->partner->id)->create(array_merge([
            'name' => $packageName,
            'schedule_type' => LessonPackage::SCHEDULE_TYPE_FIXED,
        ], $packageOverrides));

        $lessons = (int) $package->lessons_count;

        return UserLessonPackage::query()->create(array_merge([
            'user_id'           => $student->id,
            'lesson_package_id' => $package->id,
            'team_id'           => $this->team->id,
            'starts_at'         => null,
            'ends_at'           => null,
            'lessons_total'     => $lessons,
            'lessons_remaining' => $lessons,
            'fee_amount_cents'  => (int) round($feeAmount * 100),
            'is_paid'           => false,
        ], $overrides));
    }

    private function revokeCabinetSeasonsPermission(User $student): void
    {
        $permId = $this->permissionId('setPrices.cabinetSeasons.view');
        \Illuminate\Support\Facades\DB::table('permission_role')
            ->where('partner_id', $this->partner->id)
            ->where('role_id', $student->role_id)
            ->where('permission_id', $permId)
            ->delete();
    }

    private function cabinetHtmlFor(User $student): string
    {
        $this->actingAs($student);
        $this->withSession(['current_partner' => $this->partner->id]);

        $content = $this->get(route('dashboard'))->assertOk()->getContent();

        return is_string($content) ? $content : '';
    }
}
