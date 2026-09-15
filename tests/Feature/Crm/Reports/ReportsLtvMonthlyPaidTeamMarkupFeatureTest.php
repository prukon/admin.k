<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Reports;

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Первая отрисовка фильтров «Группа» в LTV и «Платежи по месяцам»:
 * пустой Select2, selected только из query, @can тренера не прячет группу.
 */
final class ReportsLtvMonthlyPaidTeamMarkupFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
    }

    public function test_monthly_team_filter_is_empty_on_first_open(): void
    {
        $this->asAdmin();

        $html = $this->get(route('reports.payments.monthly'))->assertOk()->getContent();

        $this->assertStringContainsString('id="pay-monthly-filter-team"', $html);
        $this->assertStringContainsString('data-placeholder="Все группы"', $html);
        $this->assertStringContainsString('name="filter_team_id"', $html);
        $this->assertMatchesRegularExpression(
            '/id="pay-monthly-filter-team"[\s\S]{0,400}<option value=""><\/option>/',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/id="pay-monthly-filter-team"[\s\S]{0,800}<option value="\d+" selected>/',
            $html
        );

        $posUser = strpos($html, 'for="pay-monthly-filter-user"');
        $posTeam = strpos($html, 'for="pay-monthly-filter-team"');
        $this->assertNotFalse($posUser);
        $this->assertNotFalse($posTeam);
        $this->assertTrue($posUser < $posTeam, 'Сначала ученик, затем группа');
    }

    public function test_ltv_team_filter_is_empty_on_first_open_and_group_column_is_visible(): void
    {
        $this->asAdmin();

        $html = $this->get(route('reports.ltv'))->assertOk()->getContent();

        $this->assertStringContainsString('id="pay-ltv-filter-team"', $html);
        $this->assertStringContainsString('data-placeholder="Все группы"', $html);
        $this->assertStringContainsString('data-column-key="team_title"', $html);
        $this->assertStringContainsString('<th>Группа</th>', $html);
        $this->assertDoesNotMatchRegularExpression(
            '/id="pay-ltv-filter-team"[\s\S]{0,800}<option value="\d+" selected>/',
            $html
        );
    }

    public function test_query_filter_team_id_preselects_group_on_monthly_and_ltv(): void
    {
        $this->asAdmin();
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title' => 'Выбранная группа UX',
        ]);

        $monthly = $this->get(route('reports.payments.monthly', [
            'filter_team_id' => $team->id,
        ]))->assertOk()->getContent();

        $this->assertStringContainsString(
            'option value="'.$team->id.'" selected',
            $monthly
        );
        $this->assertStringContainsString('Выбранная группа UX', $monthly);

        $ltv = $this->get(route('reports.ltv', [
            'filter_team_id' => $team->id,
        ]))->assertOk()->getContent();

        $this->assertStringContainsString(
            'option value="'.$team->id.'" selected',
            $ltv
        );
        $this->assertStringContainsString('Выбранная группа UX', $ltv);
    }

    public function test_without_trainers_view_team_filter_stays_visible_trainer_hidden(): void
    {
        $actor = $this->createUserWithoutPermission('trainers.view', $this->partner);
        $this->grantPermission($actor, 'reports.view');
        $this->actingAs($actor);

        $monthly = $this->get(route('reports.payments.monthly'))->assertOk()->getContent();
        $this->assertStringContainsString('id="pay-monthly-filter-team"', $monthly);
        $this->assertStringNotContainsString('id="pay-monthly-filter-trainer"', $monthly);

        $ltv = $this->get(route('reports.ltv'))->assertOk()->getContent();
        $this->assertStringContainsString('id="pay-ltv-filter-team"', $ltv);
        $this->assertStringNotContainsString('id="pay-ltv-filter-trainer"', $ltv);
    }

    public function test_monthly_nested_detail_header_has_group_column(): void
    {
        $this->asAdmin();

        $html = $this->get(route('reports.payments.monthly'))->assertOk()->getContent();
        $fn = strpos($html, 'function buildMonthlyDetailContainerHtml');
        $this->assertNotFalse($fn);
        $chunk = substr($html, $fn, 2500);
        $this->assertStringContainsString('<th>Группа</th>', $chunk);
        $this->assertStringContainsString("data: 'team_title'", $html);
        $this->assertStringContainsString("name: 'team_title'", $html);
    }

    public function test_garbage_filter_team_id_does_not_preselect_a_group(): void
    {
        $this->asAdmin();

        $html = $this->get(route('reports.payments.monthly', [
            'filter_team_id' => 'not-a-team',
        ]))->assertOk()->getContent();

        $this->assertDoesNotMatchRegularExpression(
            '/id="pay-monthly-filter-team"[\s\S]{0,800}<option value="[^"]+" selected>/',
            $html
        );
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
}
