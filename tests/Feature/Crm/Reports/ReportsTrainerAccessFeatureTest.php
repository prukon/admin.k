<?php

namespace Tests\Feature\Crm\Reports;

use App\Models\Role;
use App\Models\TrainerProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Доступ к отчётам и фильтру тренера: страницы reports.view, UI фильтра — trainers.view.
 */
final class ReportsTrainerAccessFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        session(['current_partner' => $this->partner->id, '2fa:passed' => true]);
        $this->asAdmin();
    }

    private function revokeTrainersViewFromAdmin(): void
    {
        $adminRoleId = (int) Role::query()->where('name', 'admin')->value('id');

        DB::table('permission_role')
            ->where('partner_id', $this->partner->id)
            ->where('role_id', $adminRoleId)
            ->where('permission_id', $this->permissionId('trainers.view'))
            ->delete();
    }

    private function makeTrainerProfile(): TrainerProfile
    {
        $trainerRoleId = (int) Role::query()->where('name', 'trainer')->value('id');

        $user = User::factory()->create([
            'partner_id' => $this->partner->id,
            'role_id' => $trainerRoleId,
            'lastname' => 'Поисков',
            'name' => 'Тренер',
        ]);

        return TrainerProfile::query()->create([
            'partner_id' => $this->partner->id,
            'user_id' => $user->id,
            'is_enabled' => true,
            'sort_order' => 0,
        ]);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function reportPagesProvider(): array
    {
        return [
            'payments' => ['payments', 'for="pay-filter-trainer">Тренер</label>'],
            'payments_monthly' => ['reports.payments.monthly', 'for="pay-monthly-filter-trainer">Тренер</label>'],
            'ltv' => ['reports.ltv', 'for="pay-ltv-filter-trainer">Тренер</label>'],
            'debts' => ['debts', 'for="pay-debt-filter-trainer">Тренер</label>'],
        ];
    }

    /**
     * @dataProvider reportPagesProvider
     */
    public function test_report_page_is_ok_and_shows_trainer_filter_with_trainers_view(
        string $routeName,
        string $trainerFilterLabelHtml,
    ): void {
        $this->get(route($routeName))
            ->assertOk()
            ->assertSee($trainerFilterLabelHtml, false);
    }

    /**
     * @dataProvider reportPagesProvider
     */
    public function test_report_page_is_ok_but_hides_trainer_filter_without_trainers_view(
        string $routeName,
        string $trainerFilterLabelHtml,
    ): void {
        $this->revokeTrainersViewFromAdmin();

        $response = $this->get(route($routeName))->assertOk();

        $content = $response->getContent();
        $this->assertIsString($content);
        $this->assertStringNotContainsString($trainerFilterLabelHtml, $content);
    }

    public function test_trainers_search_returns_results_with_reports_view(): void
    {
        $profile = $this->makeTrainerProfile();

        $this->getJson(route('reports.payments.trainers.search', ['q' => 'Поисков']))
            ->assertOk()
            ->assertJsonFragment([
                'id' => $profile->id,
                'text' => 'Поисков Тренер',
            ]);
    }

    public function test_trainers_search_works_without_trainers_view_permission(): void
    {
        $this->revokeTrainersViewFromAdmin();
        $this->makeTrainerProfile();

        $this->getJson(route('reports.payments.trainers.search', ['q' => 'Поисков']))
            ->assertOk()
            ->assertJsonStructure(['results']);
    }

    public function test_report_data_endpoints_return_200_with_reports_view(): void
    {
        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest']);

        $this->getJson(route('payments.getPayments', ['draw' => 1, 'start' => 0, 'length' => 10]))
            ->assertOk();

        $this->getJson(route('reports.payments.monthly.data', ['draw' => 1, 'start' => 0, 'length' => 10]))
            ->assertOk();

        $this->getJson(route('reports.ltv.data', ['draw' => 1, 'start' => 0, 'length' => 10]))
            ->assertOk();

        $this->getJson(route('debts.getDebts', ['draw' => 1, 'start' => 0, 'length' => 10]))
            ->assertOk();

        $this->getJson(route('reports.payments.total'))->assertOk();
        $this->getJson(route('reports.payments.monthly.total'))->assertOk();
        $this->getJson(route('reports.ltv.total'))->assertOk();
        $this->getJson(route('reports.debts.total'))->assertOk();
    }
}
