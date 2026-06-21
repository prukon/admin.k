<?php

namespace Tests\Feature\Crm\SettingPrices;

use App\Models\Team;
use App\Models\UserPrice;
use Tests\Feature\Crm\StudentTeams\StudentTeamPivotTestCase;

/**
 * E2E smoke вкладки «по ученикам»: страница → выбор группы → «Подробно» → save →
 * повторная загрузка цен (как loadUserYearPrices после submit, без F5).
 *
 * Реальный браузер не используется: цепочка HTTP-запросов повторяет AJAX-поток из users.blade.php.
 */
final class SettingPricesUsersTabWorkflowFeatureTest extends StudentTeamPivotTestCase
{
    private Team $teamA;

    private Team $teamB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed'      => true,
        ]);

        $this->asAdmin();

        $this->teamA = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Workflow-A',
            'deleted_at' => null,
        ]);
        $this->teamB = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'title'      => 'Workflow-B',
            'deleted_at' => null,
        ]);
    }

    public function test_users_tab_page_renders_panel_ajax_handlers_and_manual_paid_modal(): void
    {
        $student = $this->makeStudentWithTeams([$this->teamA, $this->teamB], [
            'name'     => 'Smoke',
            'lastname' => 'Workflow',
        ]);

        $this->withoutVite();

        $response = $this->get(route('admin.settingPrices.users'))
            ->assertOk()
            ->assertViewIs('admin.SettingPrices.index')
            ->assertViewHas('activeTab', 'users');

        $html = $response->getContent();

        $this->assertStringContainsString('id="filter-team"', $html);
        $this->assertStringContainsString('user-detail-btn', $html);
        $this->assertStringContainsString('id="save-user-year-prices"', $html);
        $this->assertStringContainsString('loadUserYearPrices', $html);
        $this->assertStringContainsString('resolveTeamContext', $html);
        $this->assertStringContainsString('Выберите конкретную группу', $html);
        $this->assertStringContainsString('data-team-count="2"', $html);
        $this->assertStringContainsString('data-user-id="' . $student->id . '"', $html);
        $this->assertStringContainsString('/admin/setting-prices/user-year-prices', $html);
        $this->assertStringContainsString('/admin/setting-prices/user-year-prices/save', $html);
        $this->assertStringContainsString('id="manualUserPricePaidModal"', $html);
        $this->assertStringContainsString('team_id: teamId', $html);
    }

    public function test_users_tab_workflow_team_context_load_save_and_reload_shows_updated_price(): void
    {
        $student = $this->makeStudentWithTeams([$this->teamA, $this->teamB], [
            'name'     => 'Цена',
            'lastname' => 'Workflow',
        ]);

        UserPrice::query()->create([
            'user_id'   => $student->id,
            'team_id'   => $this->teamA->id,
            'new_month' => '2024-03-01',
            'price'     => 1000,
            'is_paid'   => 0,
        ]);

        $this->withoutVite();
        $this->get(route('admin.settingPrices.users'))
            ->assertOk()
            ->assertSee($student->full_name, false);

        $loadBefore = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.user-year-prices'), [
                'user_id' => $student->id,
                'team_id' => $this->teamA->id,
                'year'    => 2024,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('user.team_id', $this->teamA->id)
            ->assertJsonPath('user.team_name', 'Workflow-A');

        $marchBefore = collect($loadBefore->json('months'))->firstWhere('new_month', '2024-03-01');
        $this->assertNotNull($marchBefore);
        $this->assertSame(1000, $marchBefore['price']);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.user-year-prices.save'), [
                'user_id' => $student->id,
                'team_id' => $this->teamA->id,
                'year'    => 2024,
                'prices'  => [
                    ['new_month' => '2024-03-01', 'price' => 2750],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $loadAfter = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.user-year-prices'), [
                'user_id' => $student->id,
                'team_id' => $this->teamA->id,
                'year'    => 2024,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $marchAfter = collect($loadAfter->json('months'))->firstWhere('new_month', '2024-03-01');
        $this->assertNotNull($marchAfter);
        $this->assertSame(2750, $marchAfter['price']);

        $this->assertDatabaseHas('users_prices', [
            'user_id'   => $student->id,
            'team_id'   => $this->teamA->id,
            'new_month' => '2024-03-01',
            'price'     => 2750,
        ]);

        $otherTeamMarch = collect(
            $this->withHeaders($this->ajaxHeaders())
                ->postJson(route('setting-prices.user-year-prices'), [
                    'user_id' => $student->id,
                    'team_id' => $this->teamB->id,
                    'year'    => 2024,
                ])
                ->json('months')
        )->firstWhere('new_month', '2024-03-01');

        $this->assertTrue(
            $otherTeamMarch === null || (int) ($otherTeamMarch['price'] ?? 0) === 0,
            'Цена группы B не должна измениться после save для группы A'
        );
    }

    public function test_manual_paid_modal_workflow_submit_and_reload_shows_paid_in_year_prices(): void
    {
        $this->asSuperadmin();

        $student = $this->makeStudentWithTeams([$this->teamA], [
            'name'     => 'Manual',
            'lastname' => 'PaidSmoke',
        ]);

        UserPrice::query()->create([
            'user_id'   => $student->id,
            'team_id'   => $this->teamA->id,
            'new_month' => '2024-08-01',
            'price'     => 1500,
            'is_paid'   => 0,
        ]);

        $this->withoutVite();
        $this->get(route('admin.settingPrices.users'))
            ->assertOk()
            ->assertSee('id="manualUserPricePaidModal"', false);

        $before = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.user-year-prices'), [
                'user_id' => $student->id,
                'team_id' => $this->teamA->id,
                'year'    => 2024,
            ])
            ->assertOk();

        $augustBefore = collect($before->json('months'))->firstWhere('new_month', '2024-08-01');
        $this->assertNotNull($augustBefore);
        $this->assertFalse($augustBefore['effective_is_paid']);

        $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.manual-paid'), [
                'user_id'      => $student->id,
                'team_id'      => $this->teamA->id,
                'selectedDate' => 'Август 2024',
                'mode'         => 'paid',
                'comment'      => 'E2E smoke: ручная оплата через модалку',
            ])
            ->assertOk()
            ->assertJsonStructure(['success', 'user_price'])
            ->assertJsonPath('success', true);

        $after = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.user-year-prices'), [
                'user_id' => $student->id,
                'team_id' => $this->teamA->id,
                'year'    => 2024,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $augustAfter = collect($after->json('months'))->firstWhere('new_month', '2024-08-01');
        $this->assertNotNull($augustAfter);
        $this->assertTrue($augustAfter['effective_is_paid']);
        $this->assertSame('E2E smoke: ручная оплата через модалку', $augustAfter['manual_paid_note']);
    }

    public function test_users_tab_workflow_endpoints_never_return_empty_200_or_500(): void
    {
        $student = $this->makeStudentWithTeams([$this->teamA], [
            'name'     => 'Status',
            'lastname' => 'Check',
        ]);

        UserPrice::query()->create([
            'user_id'   => $student->id,
            'team_id'   => $this->teamA->id,
            'new_month' => '2024-04-01',
            'price'     => 800,
            'is_paid'   => 0,
        ]);

        $this->withoutVite();
        $page = $this->get(route('admin.settingPrices.users'));
        $this->assertSame(200, $page->getStatusCode());
        $this->assertNotSame('', trim(strip_tags($page->getContent())));

        $load = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.user-year-prices'), [
                'user_id' => $student->id,
                'team_id' => $this->teamA->id,
                'year'    => 2024,
            ]);
        $this->assertSame(200, $load->getStatusCode());
        $this->assertTrue($load->json('success'));
        $this->assertNotEmpty($load->json('months'));

        $save = $this->withHeaders($this->ajaxHeaders())
            ->postJson(route('setting-prices.user-year-prices.save'), [
                'user_id' => $student->id,
                'team_id' => $this->teamA->id,
                'year'    => 2024,
                'prices'  => [
                    ['new_month' => '2024-04-01', 'price' => 900],
                ],
            ]);
        $this->assertSame(200, $save->getStatusCode());
        $this->assertTrue($save->json('success'));
    }

    /**
     * @return array<string, string>
     */
    private function ajaxHeaders(): array
    {
        return ['X-Requested-With' => 'XMLHttpRequest'];
    }
}
