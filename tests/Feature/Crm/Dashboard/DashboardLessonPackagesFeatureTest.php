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
        $this->assertStringContainsString('<span class="price-value">12 501</span>', $html);
        $this->assertStringContainsString('name="outSum" value="12500.50"', $html);
        $this->assertStringContainsString('>Оплатить<', $html);
    }

    public function test_cabinet_hides_lesson_packages_with_zero_fee_amount(): void
    {
        $student = $this->makeStudentWithTeams([$this->team]);
        $this->createAssignment($student, 0, 'Бесплатный пакет');

        $html = $this->cabinetHtmlFor($student);

        $this->assertStringNotContainsString('Назначенные абонементы', $html);
        $this->assertStringNotContainsString('Бесплатный пакет', $html);
    }

    public function test_cabinet_shows_paid_lesson_package_with_oplacheno_state(): void
    {
        $student = $this->makeStudentWithTeams([$this->team]);
        $this->createAssignment($student, 3_000, 'Оплаченный пакет', [
            'is_paid' => true,
        ]);

        $html = $this->cabinetHtmlFor($student);

        $this->assertStringContainsString('Назначенные абонементы', $html);
        $this->assertStringContainsString('<span class="price-value">3 000</span>', $html);
        $this->assertStringContainsString('buttonPaided', $html);
        $this->assertStringContainsString('Оплачено', $html);
    }

    public function test_cabinet_refresh_price_resets_only_season_cells_not_lesson_package_amounts(): void
    {
        $student = $this->makeStudentWithTeams([$this->team]);
        $this->createAssignment($student, 8_800, 'Пакет для JS-guard');

        $html = $this->cabinetHtmlFor($student);

        $this->assertStringContainsString(
            "document.querySelectorAll('.seasons .border_price .price-value')",
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
        $this->createAssignment($student, 15_000, 'Мультигрупповой пакет');

        $html = $this->cabinetHtmlFor($student);

        $this->assertStringContainsString('<span class="price-value">15 000</span>', $html);
        $this->assertStringContainsString('Мультигрупповой пакет', $html);
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
            'fee_amount'        => '9999.00',
            'is_paid'           => false,
        ]);

        $localStudent = $this->makeStudentWithTeams([$this->team]);
        $this->createAssignment($localStudent, 1_000, 'Свой абонемент');

        $html = $this->cabinetHtmlFor($localStudent);

        $this->assertStringContainsString('Свой абонемент', $html);
        $this->assertStringNotContainsString('Чужой абонемент', $html);
        $this->assertStringNotContainsString('<span class="price-value">9 999</span>', $html);
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
        $this->createAssignment($student, 6_500, 'Доступный пакет');

        $this->actingAs($student);
        $this->withSession(['current_partner' => $this->partner->id]);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Назначенные абонементы', false)
            ->assertSee('Доступный пакет', false)
            ->assertSee('6 500', false);
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
     */
    private function createAssignment(
        User $student,
        float $feeAmount,
        string $packageName,
        array $overrides = [],
    ): UserLessonPackage {
        $package = LessonPackage::factory()->forPartner($this->partner->id)->create([
            'name' => $packageName,
        ]);

        $lessons = (int) $package->lessons_count;

        return UserLessonPackage::query()->create(array_merge([
            'user_id'           => $student->id,
            'lesson_package_id' => $package->id,
            'team_id'           => $this->team->id,
            'starts_at'         => null,
            'ends_at'           => null,
            'lessons_total'     => $lessons,
            'lessons_remaining' => $lessons,
            'fee_amount'        => number_format($feeAmount, 2, '.', ''),
            'is_paid'           => false,
        ], $overrides));
    }

    private function cabinetHtmlFor(User $student): string
    {
        $this->actingAs($student);
        $this->withSession(['current_partner' => $this->partner->id]);

        $content = $this->get(route('dashboard'))->assertOk()->getContent();

        return is_string($content) ? $content : '';
    }
}
