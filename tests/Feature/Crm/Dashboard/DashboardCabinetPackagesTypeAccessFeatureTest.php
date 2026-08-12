<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Dashboard;

use App\Models\LessonPackage;
use App\Models\ParentProfile;
use App\Models\Team;
use App\Models\User;
use App\Models\UserLessonPackage;
use App\Services\Users\FamilyStudentContextService;
use App\Support\CabinetLessonPackagePermission;
use Tests\Feature\Crm\StudentTeams\StudentTeamPivotTestCase;

/**
 * Консоль: отдельные права на типы абонементов (fixed / flexible / single / postpay).
 *
 * @see App\Support\CabinetLessonPackagePermission
 * @see resources/views/dashboard.blade.php
 */
final class DashboardCabinetPackagesTypeAccessFeatureTest extends StudentTeamPivotTestCase
{
    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config(['broadcasting.default' => 'null']);

        $this->team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Cabinet-Type-Team',
        ]);
    }

    public function test_cabinet_shows_only_fixed_when_only_fixed_permission_granted(): void
    {
        $student = $this->makeStudentWithTeams([$this->team]);
        $this->grantPermissionForUser($student, CabinetLessonPackagePermission::FIXED);
        $this->createAssignment($student, 1_100, 'Фикс виден', LessonPackage::SCHEDULE_TYPE_FIXED);
        $this->createAssignment($student, 2_200, 'Гибкий скрыт', LessonPackage::SCHEDULE_TYPE_FLEXIBLE);
        $this->createAssignment($student, 3_300, 'Разовое скрыто', LessonPackage::SCHEDULE_TYPE_NO_SCHEDULE);

        $html = $this->cabinetHtmlFor($student);

        $this->assertStringContainsString('Назначенные абонементы', $html);
        $this->assertStringContainsString('Фикс виден', $html);
        $this->assertStringNotContainsString('Гибкий скрыт', $html);
        $this->assertStringNotContainsString('Разовое скрыто', $html);
    }

    public function test_cabinet_shows_only_flexible_when_only_flexible_permission_granted(): void
    {
        $student = $this->makeStudentWithTeams([$this->team]);
        $this->grantPermissionForUser($student, CabinetLessonPackagePermission::FLEXIBLE);
        $this->createAssignment($student, 1_100, 'Фикс скрыт', LessonPackage::SCHEDULE_TYPE_FIXED);
        $this->createAssignment($student, 2_200, 'Гибкий виден', LessonPackage::SCHEDULE_TYPE_FLEXIBLE);

        $html = $this->cabinetHtmlFor($student);

        $this->assertStringContainsString('Гибкий виден', $html);
        $this->assertStringNotContainsString('Фикс скрыт', $html);
    }

    public function test_cabinet_shows_only_single_when_only_single_permission_granted(): void
    {
        $student = $this->makeStudentWithTeams([$this->team]);
        $this->grantPermissionForUser($student, CabinetLessonPackagePermission::SINGLE);
        $this->createAssignment($student, 1_100, 'Фикс скрыт', LessonPackage::SCHEDULE_TYPE_FIXED);
        $this->createAssignment($student, 3_300, 'Разовое видно', LessonPackage::SCHEDULE_TYPE_NO_SCHEDULE);

        $html = $this->cabinetHtmlFor($student);

        $this->assertStringContainsString('Разовое видно', $html);
        $this->assertStringNotContainsString('Фикс скрыт', $html);
    }

    public function test_cabinet_shows_all_assignment_types_when_all_type_permissions_granted(): void
    {
        $student = $this->makeStudentWithTeams([$this->team]);
        $this->grantPermissionForUser($student, CabinetLessonPackagePermission::FIXED);
        $this->grantPermissionForUser($student, CabinetLessonPackagePermission::FLEXIBLE);
        $this->grantPermissionForUser($student, CabinetLessonPackagePermission::SINGLE);
        $this->createAssignment($student, 1_100, 'Фикс все', LessonPackage::SCHEDULE_TYPE_FIXED);
        $this->createAssignment($student, 2_200, 'Гибкий все', LessonPackage::SCHEDULE_TYPE_FLEXIBLE);
        $this->createAssignment($student, 3_300, 'Разовое все', LessonPackage::SCHEDULE_TYPE_NO_SCHEDULE);

        $html = $this->cabinetHtmlFor($student);

        $this->assertStringContainsString('Фикс все', $html);
        $this->assertStringContainsString('Гибкий все', $html);
        $this->assertStringContainsString('Разовое все', $html);
        $this->assertStringContainsString('id="dashboard-lesson-packages"', $html);
    }

    public function test_zero_fee_assignment_stays_hidden_even_with_type_permission(): void
    {
        $student = $this->makeStudentWithTeams([$this->team]);
        $this->grantPermissionForUser($student, CabinetLessonPackagePermission::FIXED);
        $this->createAssignment($student, 0, 'Бесплатный фикс', LessonPackage::SCHEDULE_TYPE_FIXED);

        $html = $this->cabinetHtmlFor($student);

        $this->assertStringNotContainsString('Назначенные абонементы', $html);
        $this->assertStringNotContainsString('Бесплатный фикс', $html);
    }

    public function test_crm_package_permissions_do_not_show_cabinet_cards(): void
    {
        $student = $this->makeStudentWithTeams([$this->team]);
        $this->grantPermissionForUser($student, 'setPrices.view');
        $this->grantPermissionForUser($student, 'lessonPackages.view');
        $this->grantPermissionForUser($student, 'setPrices.packageAssignments.view');
        $this->createAssignment($student, 4_400, 'Только CRM права', LessonPackage::SCHEDULE_TYPE_FIXED);

        $html = $this->cabinetHtmlFor($student);

        $this->assertStringNotContainsString('Назначенные абонементы', $html);
        $this->assertStringNotContainsString('Только CRM права', $html);
    }

    public function test_lesson_packages_type_postpay_does_not_show_postpay_months_on_cabinet(): void
    {
        $student = $this->makeStudentWithTeams([$this->team]);
        $this->grantPermissionForUser($student, 'lessonPackages.type.postpay');
        $this->insertRegularAndPostpayPrices($student);

        $html = $this->cabinetHtmlFor($student);

        $this->assertStringNotContainsString('"is_postpay":true', $html);
        $this->assertStringNotContainsString('Постоплата консоль', $html);
        $this->assertStringContainsString('class="row seasons"', $html);
    }

    public function test_team_switcher_shows_with_postpay_permission_without_seasons_or_ulp(): void
    {
        $teamB = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Cabinet-Type-Team-B',
        ]);
        $student = $this->makeStudentWithTeams([$this->team, $teamB]);
        $this->revokeCabinetSeasonsPermission($student);
        $this->grantPermissionForUser($student, CabinetLessonPackagePermission::POSTPAY);

        $html = $this->cabinetHtmlFor($student->fresh());

        $this->assertStringContainsString('id="dashboard-active-team"', $html);
        $this->assertStringContainsString('Выбор группы', $html);
        $this->assertStringContainsString('var dashboardTeamSwitcherEnabled = true', $html);
        $this->assertStringContainsString('var dashboardSeasonsEnabled = true', $html);
        $this->assertStringNotContainsString('Назначенные абонементы', $html);
    }

    public function test_team_switcher_stays_hidden_when_assignments_exist_but_type_permission_missing(): void
    {
        $teamB = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Cabinet-Type-Hidden-B',
        ]);
        $student = $this->makeStudentWithTeams([$this->team, $teamB]);
        $this->revokeCabinetSeasonsPermission($student);
        $this->grantPermissionForUser($student, 'setPrices.packageAssignments.view');
        $this->createAssignment($student, 7_700, 'Скрытый при селекте', LessonPackage::SCHEDULE_TYPE_FIXED);

        $html = $this->cabinetHtmlFor($student->fresh());

        $this->assertStringNotContainsString('id="dashboard-active-team"', $html);
        $this->assertStringNotContainsString('Назначенные абонементы', $html);
        $this->assertStringContainsString('var dashboardTeamSwitcherEnabled = false', $html);
    }

    public function test_superadmin_sees_all_assignment_types_without_explicit_grants(): void
    {
        $this->asSuperadmin();
        $this->withSession(['current_partner' => $this->partner->id]);
        app(\App\Services\TeamUserSyncService::class)->syncTeamsForStudent($this->user, [(int) $this->team->id]);
        $this->createAssignment($this->user, 1_100, 'SA фикс', LessonPackage::SCHEDULE_TYPE_FIXED);
        $this->createAssignment($this->user, 2_200, 'SA гибкий', LessonPackage::SCHEDULE_TYPE_FLEXIBLE);
        $this->createAssignment($this->user, 3_300, 'SA разовое', LessonPackage::SCHEDULE_TYPE_NO_SCHEDULE);

        $html = (string) $this->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('SA фикс', $html);
        $this->assertStringContainsString('SA гибкий', $html);
        $this->assertStringContainsString('SA разовое', $html);
    }

    public function test_trainer_gets_200_but_does_not_see_packages_without_type_permission(): void
    {
        $trainerRoleId = (int) \App\Models\Role::query()->where('name', 'trainer')->value('id');
        $trainer = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $trainerRoleId,
            'is_enabled' => true,
        ]);
        app(\App\Services\TeamUserSyncService::class)->syncTeamsForStudent($trainer, [(int) $this->team->id]);
        $this->createAssignment($trainer, 5_500, 'Тренерский пакет', LessonPackage::SCHEDULE_TYPE_FIXED);

        $html = $this->cabinetHtmlFor($trainer);

        $this->assertStringNotContainsString('Назначенные абонементы', $html);
        $this->assertStringNotContainsString('Тренерский пакет', $html);
        $this->assertStringContainsString('var dashboardSeasonsEnabled = false', $html);
    }

    public function test_pay_button_is_disabled_without_paying_classes_when_card_is_visible(): void
    {
        $student = $this->makeStudentWithTeams([$this->team]);
        $this->grantPermissionForUser($student, CabinetLessonPackagePermission::FIXED);
        $this->revokePermission($student, 'paying.classes');
        $ulp = $this->createAssignment($student, 2_200, 'Карточка без оплаты', LessonPackage::SCHEDULE_TYPE_FIXED);

        $html = $this->cabinetHtmlFor($student->fresh());

        $this->assertStringContainsString('Карточка без оплаты', $html);
        $this->assertStringNotContainsString('action="'.route('payment').'"', $html);
        $this->assertStringContainsString('disabled', $html);
        $this->assertStringNotContainsString(
            'name="user_lesson_package_id" value="'.$ulp->id.'"',
            $html
        );
    }

    public function test_student_can_open_payment_page_without_cabinet_type_permission(): void
    {
        $student = $this->makeStudentWithTeams([$this->team]);
        $ulp = $this->createAssignment($student, 3_300, 'Оплата без карточки', LessonPackage::SCHEDULE_TYPE_FIXED);

        $this->actingAs($student);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->post(route('payment'), [
            'payment_kind' => 'lesson_package',
            'user_lesson_package_id' => $ulp->id,
            'paymentDate' => 'Абонемент',
            'outSum' => '1.00',
        ])
            ->assertOk()
            ->assertViewIs('payment.paymentUser')
            ->assertViewHas('userLessonPackageId', (int) $ulp->id);
    }

    public function test_admin_user_details_omits_postpay_months_without_postpay_permission(): void
    {
        $student = $this->makeStudentWithTeams([$this->team]);
        $this->insertRegularAndPostpayPrices($student);

        $this->asAdmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $without = $this->getJson(route('getUserDetails', ['userId' => $student->id]))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->json('userPrice');

        $this->assertIsArray($without);
        $postpayWithout = array_values(array_filter($without, static fn ($row) => ! empty($row['is_postpay'])));
        $this->assertCount(0, $postpayWithout);

        $this->grantPermissionForUser($this->user, CabinetLessonPackagePermission::POSTPAY);

        $with = $this->getJson(route('getUserDetails', ['userId' => $student->id]))
            ->assertOk()
            ->json('userPrice');

        $postpayWith = array_values(array_filter($with, static fn ($row) => ! empty($row['is_postpay'])));
        $this->assertCount(1, $postpayWith);
    }

    public function test_seasons_and_postpay_months_both_appear_when_both_permissions_granted(): void
    {
        $student = $this->makeStudentWithTeams([$this->team]);
        $this->grantPermissionForUser($student, CabinetLessonPackagePermission::POSTPAY);
        $this->insertRegularAndPostpayPrices($student);

        $html = $this->cabinetHtmlFor($student);

        $this->assertStringContainsString('class="row seasons"', $html);
        $this->assertStringContainsString('"is_postpay":true', $html);
        $this->assertStringContainsString('Постоплата консоль', $html);
        $this->assertStringContainsString('"price":5500', $html);
    }

    public function test_pay_form_markup_contains_lesson_package_fields_when_card_visible(): void
    {
        $student = $this->makeStudentWithTeams([$this->team]);
        $this->grantPermissionForUser($student, CabinetLessonPackagePermission::FIXED);
        $ulp = $this->createAssignment($student, 6_600, 'Поля оплаты', LessonPackage::SCHEDULE_TYPE_FIXED);

        $html = $this->cabinetHtmlFor($student);

        $this->assertStringContainsString('name="payment_kind" value="lesson_package"', $html);
        $this->assertStringContainsString('name="user_lesson_package_id" value="'.$ulp->id.'"', $html);
        $this->assertStringContainsString('data-ulp-team-id="'.$this->team->id.'"', $html);
        $this->assertStringContainsString('action="'.route('payment').'"', $html);

        $amountPos = strpos($html, '6600');
        $namePos = strpos($html, 'Поля оплаты');
        $formPos = strpos($html, 'name="payment_kind" value="lesson_package"');
        $this->assertNotFalse($amountPos);
        $this->assertNotFalse($namePos);
        $this->assertNotFalse($formPos);
        $this->assertLessThan($namePos, $amountPos, 'Сумма карточки должна быть выше названия');
        $this->assertLessThan($formPos, $namePos, 'Название должно быть выше формы оплаты');
    }

    public function test_postpay_permission_does_not_show_ulp_cards(): void
    {
        $student = $this->makeStudentWithTeams([$this->team]);
        $this->grantPermissionForUser($student, CabinetLessonPackagePermission::POSTPAY);
        $this->createAssignment($student, 4_400, 'ULP при одном postpay', LessonPackage::SCHEDULE_TYPE_FIXED);

        $html = $this->cabinetHtmlFor($student);

        $this->assertStringNotContainsString('Назначенные абонементы', $html);
        $this->assertStringNotContainsString('ULP при одном postpay', $html);
        $this->assertStringContainsString('class="row seasons"', $html);
    }

    public function test_family_switch_shows_active_sibling_assignment_not_logged_in_users(): void
    {
        [$viewer, $sibling] = $this->makeFamilySiblings();
        $this->grantPermissionForUser($viewer, CabinetLessonPackagePermission::FIXED);
        $this->createAssignment($viewer, 1_100, 'Абонемент смотрящего', LessonPackage::SCHEDULE_TYPE_FIXED);
        $this->createAssignment($sibling, 8_800, 'Абонемент брата', LessonPackage::SCHEDULE_TYPE_FIXED);

        $this->actingAs($viewer);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $before = (string) $this->get(route('dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('Абонемент смотрящего', $before);
        $this->assertStringNotContainsString('Абонемент брата', $before);

        $this->post(route('cabinet.active-student.switch'), [
            'student_user_id' => $sibling->id,
        ])->assertRedirect();
        $this->assertSame($sibling->id, session(FamilyStudentContextService::SESSION_KEY));

        $after = (string) $this->get(route('dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('Назначенные абонементы', $after);
        $this->assertStringContainsString('Абонемент брата', $after);
        $this->assertStringNotContainsString('Абонемент смотрящего', $after);
    }

    public function test_family_switch_does_not_show_sibling_assignment_without_type_permission(): void
    {
        [$viewer, $sibling] = $this->makeFamilySiblings();
        $this->createAssignment($sibling, 8_800, 'Скрыт после смены ребёнка', LessonPackage::SCHEDULE_TYPE_FIXED);

        $this->actingAs($viewer);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);

        $this->post(route('cabinet.active-student.switch'), [
            'student_user_id' => $sibling->id,
        ])->assertRedirect();

        $html = (string) $this->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringNotContainsString('Назначенные абонементы', $html);
        $this->assertStringNotContainsString('Скрыт после смены ребёнка', $html);
    }

    /**
     * P2: консоль с видимой карточкой — не белый экран, блок и форма на месте.
     */
    public function test_cabinet_with_visible_package_is_not_blank_screen(): void
    {
        $student = $this->makeStudentWithTeams([$this->team]);
        $this->grantPermissionForUser($student, CabinetLessonPackagePermission::FIXED);
        $this->createAssignment($student, 9_900, 'Smoke фикс', LessonPackage::SCHEDULE_TYPE_FIXED);

        $html = $this->cabinetHtmlFor($student);

        $this->assertNotSame('', trim($html));
        $this->assertStringContainsString('Консоль', $html);
        $this->assertStringContainsString('Назначенные абонементы', $html);
        $this->assertStringContainsString('Smoke фикс', $html);
        $this->assertStringContainsString('id="dashboard-lesson-packages"', $html);
        $this->assertStringContainsString('filterLessonPackagesByTeam', $html);
    }

    public function test_cabinet_hides_postpay_month_without_postpay_permission(): void
    {
        $student = $this->makeStudentWithTeams([$this->team]);
        $this->insertRegularAndPostpayPrices($student);

        $html = $this->cabinetHtmlFor($student);

        $this->assertStringContainsString('class="row seasons"', $html);
        $this->assertStringContainsString('var dashboardSeasonsEnabled = true', $html);
        $this->assertStringNotContainsString('"is_postpay":true', $html);
        $this->assertStringNotContainsString('Постоплата консоль', $html);
        $this->assertStringContainsString('"price":5500', $html);
    }

    public function test_cabinet_shows_postpay_month_with_postpay_permission_without_seasons(): void
    {
        $student = $this->makeStudentWithTeams([$this->team]);
        $this->revokeCabinetSeasonsPermission($student);
        $this->grantPermissionForUser($student, CabinetLessonPackagePermission::POSTPAY);
        $this->insertRegularAndPostpayPrices($student);

        $html = $this->cabinetHtmlFor($student->fresh());

        $this->assertStringContainsString('class="row seasons"', $html);
        $this->assertStringContainsString('var dashboardSeasonsEnabled = true', $html);
        $this->assertStringContainsString('"is_postpay":true', $html);
        $this->assertStringContainsString('Постоплата консоль', $html);
        $this->assertStringNotContainsString('"price":5500', $html);
    }

    public function test_get_user_details_filters_postpay_by_permission(): void
    {
        $student = $this->makeStudentWithTeams([$this->team]);
        $this->insertRegularAndPostpayPrices($student);

        $this->actingAs($student);
        $this->withSession(['current_partner' => $this->partner->id]);

        $without = $this->getJson(route('getUserDetails', ['userId' => $student->id]))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->json('userPrice');

        $this->assertIsArray($without);
        $this->assertCount(1, $without);
        $this->assertFalse((bool) ($without[0]['is_postpay'] ?? false));

        $this->grantPermissionForUser($student, CabinetLessonPackagePermission::POSTPAY);

        $with = $this->getJson(route('getUserDetails', ['userId' => $student->id]))
            ->assertOk()
            ->json('userPrice');

        $this->assertIsArray($with);
        $this->assertCount(2, $with);
        $postpayRows = array_values(array_filter($with, static fn ($row) => ! empty($row['is_postpay'])));
        $this->assertCount(1, $postpayRows);
    }

    private function createAssignment(
        User $student,
        float $feeAmount,
        string $packageName,
        string $scheduleType,
    ): UserLessonPackage {
        $package = LessonPackage::factory()->forPartner($this->partner->id)->create([
            'name' => $packageName,
            'schedule_type' => $scheduleType,
            'duration_days' => $scheduleType === LessonPackage::SCHEDULE_TYPE_NO_SCHEDULE ? 1 : 90,
            'lessons_count' => $scheduleType === LessonPackage::SCHEDULE_TYPE_NO_SCHEDULE ? 1 : 8,
        ]);

        return UserLessonPackage::query()->create([
            'user_id' => $student->id,
            'lesson_package_id' => $package->id,
            'team_id' => $this->team->id,
            'lessons_total' => (int) $package->lessons_count,
            'lessons_remaining' => (int) $package->lessons_count,
            'fee_amount_cents' => (int) round($feeAmount * 100),
            'is_paid' => false,
        ]);
    }

    private function insertRegularAndPostpayPrices(User $student): void
    {
        $postpay = LessonPackage::factory()->forPartner($this->partner->id)->postpay()->create([
            'name' => 'Постоплата консоль',
            'price_cents' => 123400,
        ]);

        $this->insertUserPrice($student, [
            'new_month' => '2026-08-01',
            'price' => 5_500,
            'is_paid' => 0,
        ], $this->team);

        $this->insertUserPrice($student, [
            'new_month' => '2026-07-01',
            'price' => 1_234,
            'is_paid' => 0,
            'lesson_package_id' => $postpay->id,
        ], $this->team);
    }

    /**
     * @return array{0: User, 1: User}
     */
    private function makeFamilySiblings(): array
    {
        $parent = ParentProfile::factory()->create([
            'partner_id' => $this->partner->id,
        ]);

        $viewer = $this->makeStudentWithTeams([$this->team], [
            'parent_id' => $parent->id,
            'name' => 'Петя',
            'lastname' => 'Семейный',
        ]);

        $sibling = $this->makeStudentWithTeams([$this->team], [
            'parent_id' => $parent->id,
            'name' => 'Вася',
            'lastname' => 'Семейный',
        ]);

        return [$viewer, $sibling];
    }

    private function revokeCabinetSeasonsPermission(User $student): void
    {
        $this->revokePermission($student, 'setPrices.cabinetSeasons.view');
    }

    private function revokePermission(User $user, string $permissionName): void
    {
        $permId = $this->permissionId($permissionName);
        \Illuminate\Support\Facades\DB::table('permission_role')
            ->where('partner_id', $user->partner_id)
            ->where('role_id', $user->role_id)
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
