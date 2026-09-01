<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Dashboard;

use App\Models\ParentProfile;
use App\Models\Team;
use App\Models\User;
use App\Services\Users\FamilyStudentContextService;
use App\Support\CabinetLessonPackagePermission;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\Dashboard\Concerns\InteractsWithCabinetSeasonYears;
use Tests\Feature\Crm\StudentTeams\StudentTeamPivotTestCase;

/**
 * UX сезонов на /cabinet: шапка текущего учебного года + ячейка под users_prices.
 *
 * Реальный баг (до фикса): начисление за 2026-09 было в payload, а createSeasons()
 * не создавал «Сентябрь 2026», потому что HTML обрывался на data-season="2026".
 */
final class DashboardCabinetSeasonYearsFeatureTest extends StudentTeamPivotTestCase
{
    use InteractsWithCabinetSeasonYears;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config(['broadcasting.default' => 'null']);

        $this->team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Season-Years-UX',
        ]);
    }

    public function test_parent_with_september_2026_fee_sees_payload_and_js_cell_for_that_month(): void
    {
        $this->travelTo(Carbon::parse('2026-09-01 12:00:00', 'Europe/Moscow'));

        $student = $this->makeStudentWithTeams([$this->team]);
        $this->insertUserPrice($student, [
            'new_month' => '2026-09-01',
            'price'     => 4_500,
            'is_paid'   => 0,
        ], $this->team);

        $html = $this->cabinetHtmlFor($student);

        $this->assertStringContainsString('class="row seasons"', $html);
        $this->assertStringContainsString('id="season-2027"', $html);
        $this->assertStringContainsString('Сезон 2026 - 2027', $html);
        $this->assertStringContainsString('data-season="2027"', $html);
        $this->assertChargeHasJsSeasonCell($html, '2026-09-01');
        $this->assertContains('2027-08-01', $this->billingMonthsFromCabinetHtml($html));
        $this->assertContains('2025-09-01', $this->billingMonthsFromCabinetHtml($html));
    }

    public function test_admin_user_switch_json_returns_september_2026_price_and_page_has_matching_shell(): void
    {
        $this->travelTo(Carbon::parse('2026-09-01 12:00:00', 'Europe/Moscow'));

        $student = $this->makeStudentWithTeams([$this->team]);
        $this->insertUserPrice($student, [
            'new_month' => '2026-09-01',
            'price'     => 6_200,
            'is_paid'   => 0,
        ], $this->team);

        $this->asAdmin();
        $this->withSession(['current_partner' => $this->partner->id]);

        $page = (string) $this->get(route('dashboard'))->assertOk()->getContent();
        $this->assertContains('2026-09-01', $this->billingMonthsFromCabinetHtml($page));

        $json = $this->getJson(route('getUserDetails', ['userId' => $student->id]))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'userPrice'])
            ->json();

        $this->assertIsArray($json['userPrice']);
        $this->assertContains('2026-09-01', $this->priceMonthKeys($json['userPrice']));
        $this->assertTrue(
            collect($json['userPrice'])->contains(
                fn ($row) => substr((string) ($row['new_month'] ?? ''), 0, 10) === '2026-09-01'
                    && (int) ($row['team_id'] ?? 0) === (int) $this->team->id
            ),
            'getUserDetails должен отдать team_id начисления, иначе apendPrice не заполнит форму'
        );
    }

    public function test_august_2026_does_not_open_next_season_even_when_september_price_exists(): void
    {
        $this->travelTo(Carbon::parse('2026-08-31 23:59:59', 'Europe/Moscow'));

        $student = $this->makeStudentWithTeams([$this->team]);
        $this->insertUserPrice($student, [
            'new_month' => '2026-09-01',
            'price'     => 4_500,
            'is_paid'   => 0,
        ], $this->team);

        $html = $this->cabinetHtmlFor($student);

        $this->assertContains('2026-09-01', $this->priceMonthKeys($this->embeddedUserPrices($html)));
        $this->assertNotContains('2026-09-01', $this->billingMonthsFromCabinetHtml($html));
        $this->assertStringNotContainsString('id="season-2027"', $html);
        $this->assertContains('2026-08-01', $this->billingMonthsFromCabinetHtml($html));
    }

    public function test_future_season_price_does_not_create_extra_shell_in_september_2026(): void
    {
        $this->travelTo(Carbon::parse('2026-09-01 12:00:00', 'Europe/Moscow'));

        $student = $this->makeStudentWithTeams([$this->team]);
        $this->insertUserPrice($student, [
            'new_month' => '2028-09-01',
            'price'     => 9_900,
            'is_paid'   => 0,
        ], $this->team);

        $html = $this->cabinetHtmlFor($student);

        $this->assertContains('2028-09-01', $this->priceMonthKeys($this->embeddedUserPrices($html)));
        $this->assertNotContains('2028-09-01', $this->billingMonthsFromCabinetHtml($html));
        $this->assertStringNotContainsString('id="season-2029"', $html);
        $this->assertSame(2027, $this->seasonEndYearsFromHtml($html)[0] ?? 0);
    }

    public function test_season_headers_are_contiguous_from_current_down_to_2021_2022(): void
    {
        $this->travelTo(Carbon::parse('2026-09-01 12:00:00', 'Europe/Moscow'));

        $student = $this->makeStudentWithTeams([$this->team]);
        $html = $this->cabinetHtmlFor($student);

        $years = $this->seasonEndYearsFromHtml($html);
        $this->assertSame(range(2027, 2022), $years);

        foreach ($years as $endYear) {
            $startYear = $endYear - 1;
            $this->assertStringContainsString('id="season-'.$endYear.'"', $html);
            $this->assertStringContainsString('Сезон '.$startYear.' - '.$endYear, $html);
            $this->assertMatchesRegularExpression(
                '/id="season-'.$endYear.'"[\s\S]*?class="display-none from">'.$startYear.'<\/span>[\s\S]*?class="display-none to">'.$endYear.'<\/span>/',
                $html
            );
        }

        $this->assertStringNotContainsString('id="season-2021"', $html);
        $this->assertStringNotContainsString('id="season-2028"', $html);
    }

    public function test_family_switch_shows_sibling_september_2026_fee_on_matching_shell(): void
    {
        $this->travelTo(Carbon::parse('2026-09-01 12:00:00', 'Europe/Moscow'));

        [$viewer, $sibling] = $this->makeFamilySiblings();
        $this->insertUserPrice($viewer, [
            'new_month' => '2026-09-01',
            'price'     => 1_000,
            'is_paid'   => 0,
        ], $this->team);
        $this->insertUserPrice($sibling, [
            'new_month' => '2026-09-01',
            'price'     => 2_500,
            'is_paid'   => 0,
        ], $this->team);

        $this->actingAs($viewer);
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);

        $this->post(route('cabinet.active-student.switch'), [
            'student_user_id' => $sibling->id,
        ])->assertRedirect();
        $this->assertSame($sibling->id, session(FamilyStudentContextService::SESSION_KEY));

        $html = (string) $this->get(route('dashboard'))->assertOk()->getContent();

        $this->assertChargeHasJsSeasonCell($html, '2026-09-01');
        $prices = $this->embeddedUserPrices($html);
        $this->assertTrue(
            collect($prices)->contains(
                fn ($row) => (int) ($row['user_id'] ?? 0) === (int) $sibling->id
                    && substr((string) ($row['new_month'] ?? ''), 0, 10) === '2026-09-01'
            )
        );
        $this->assertFalse(
            collect($prices)->contains(fn ($row) => (int) ($row['user_id'] ?? 0) === (int) $viewer->id)
        );
    }

    public function test_postpay_permission_alone_still_renders_2026_2027_shell(): void
    {
        $this->travelTo(Carbon::parse('2026-09-01 12:00:00', 'Europe/Moscow'));

        $student = $this->makeStudentWithTeams([$this->team]);
        $this->revokePermission($student, 'setPrices.cabinetSeasons.view');
        $this->grantPermissionForUser($student, CabinetLessonPackagePermission::POSTPAY);

        $this->assertFalse($student->fresh()->can('setPrices.cabinetSeasons.view'));
        $this->assertTrue($student->fresh()->can(CabinetLessonPackagePermission::POSTPAY));

        $html = $this->cabinetHtmlFor($student->fresh());

        $this->assertStringContainsString('id="season-2027"', $html);
        $this->assertContains('2026-09-01', $this->billingMonthsFromCabinetHtml($html));
        $this->assertStringContainsString('var dashboardSeasonsEnabled = true', $html);
    }

    public function test_without_season_and_postpay_permissions_september_price_has_no_shell(): void
    {
        $this->travelTo(Carbon::parse('2026-09-01 12:00:00', 'Europe/Moscow'));

        $student = $this->makeStudentWithTeams([$this->team]);
        $this->revokePermission($student, 'setPrices.cabinetSeasons.view');
        $this->insertUserPrice($student, [
            'new_month' => '2026-09-01',
            'price'     => 4_500,
            'is_paid'   => 0,
        ], $this->team);

        $html = $this->cabinetHtmlFor($student->fresh());

        $this->assertStringNotContainsString('class="row seasons"', $html);
        $this->assertStringNotContainsString('id="season-2027"', $html);
        $this->assertStringContainsString('var dashboardSeasonsEnabled = false', $html);
        $this->assertSame([], $this->embeddedUserPrices($html));
        $this->assertSame([], $this->billingMonthsFromCabinetHtml($html));
    }

    public function test_non_ajax_post_cabinet_keeps_september_2026_shell_and_payload(): void
    {
        $this->travelTo(Carbon::parse('2026-09-01 12:00:00', 'Europe/Moscow'));

        $student = $this->makeStudentWithTeams([$this->team]);
        $this->insertUserPrice($student, [
            'new_month' => '2026-09-01',
            'price'     => 4_500,
            'is_paid'   => 0,
        ], $this->team);

        $this->actingAs($student);
        $this->withSession(['current_partner' => $this->partner->id]);

        $html = (string) $this->from(route('dashboard'))
            ->post(route('dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertNotSame('', trim($html));
        $this->assertChargeHasJsSeasonCell($html, '2026-09-01');
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
            'name'      => 'Петя',
            'lastname'  => 'Сезон',
        ]);
        $sibling = $this->makeStudentWithTeams([$this->team], [
            'parent_id' => $parent->id,
            'name'      => 'Вася',
            'lastname'  => 'Сезон',
        ]);

        return [$viewer, $sibling];
    }

    private function revokePermission(User $user, string $permissionName): void
    {
        $permId = $this->permissionId($permissionName);
        DB::table('permission_role')
            ->where('partner_id', $user->partner_id)
            ->where('role_id', $user->role_id)
            ->where('permission_id', $permId)
            ->delete();
    }
}
