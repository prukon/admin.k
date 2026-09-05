<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\SettingPrices;

use App\Models\LessonPackage;
use App\Models\Team;
use App\Models\TeamPrice;
use App\Models\User;
use App\Models\UserPrice;
use App\Services\TeamUserSyncService;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Non-AJAX: превью всегда JSON; apply пишет в БД и редиректит.
 */
final class SettingPricesMonthlyProlongNonAjaxSafetyNetFeatureTest extends CrmTestCase
{
    private Team $team;

    private User $student;

    private LessonPackage $package;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession([
            'current_partner' => $this->partner->id,
            '2fa:passed' => true,
        ]);
        $this->asAdmin();

        $this->team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'deleted_at' => null,
            'title' => 'Non-AJAX пролонг',
        ]);
        $this->student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);
        app(TeamUserSyncService::class)->syncTeamsForStudent($this->student, [(int) $this->team->id]);
        $this->package = LessonPackage::factory()->forPartner((int) $this->partner->id)->fixed(4, 60)->create([
            'price_cents' => 600000,
        ]);
        TeamPrice::query()->create([
            'team_id' => $this->team->id,
            'new_month' => '2026-09-01',
            'price_cents' => 600000,
            'lesson_package_id' => $this->package->id,
        ]);
        UserPrice::forceCreate([
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-09-01',
            'price_cents' => 600000,
            'lesson_package_id' => $this->package->id,
            'is_paid' => 0,
        ]);
    }

    public function test_preview_without_ajax_still_returns_json_and_does_not_write(): void
    {
        $this->post(route('setting-prices.prolong-month.preview'), [
            'selectedDate' => 'Сентябрь 2026',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('can_apply', true);

        $this->assertDatabaseMissing('users_prices', [
            'user_id' => $this->student->id,
            'new_month' => '2026-10-01',
        ]);
    }

    public function test_apply_without_ajax_redirects_and_persists(): void
    {
        $this->from(route('admin.settingPrices.indexMenu'))
            ->post(route('setting-prices.prolong-month.apply'), [
                'selectedDate' => 'Сентябрь 2026',
            ])
            ->assertRedirect(route('admin.settingPrices.indexMenu'));

        $this->assertDatabaseHas('users_prices', [
            'user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'new_month' => '2026-10-01',
            'lesson_package_id' => $this->package->id,
            'price_cents' => 600000,
        ]);
        $this->assertDatabaseHas('team_prices', [
            'team_id' => $this->team->id,
            'new_month' => '2026-10-01',
            'lesson_package_id' => $this->package->id,
        ]);
    }

    public function test_apply_without_ajax_invalid_month_redirects_with_errors(): void
    {
        $this->from(route('admin.settingPrices.indexMenu'))
            ->post(route('setting-prices.prolong-month.apply'), [
                'selectedDate' => 'Foo 2026',
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors(['selectedDate']);

        $this->assertDatabaseMissing('users_prices', [
            'user_id' => $this->student->id,
            'new_month' => '2026-10-01',
        ]);
    }

    public function test_preview_without_ajax_invalid_month_returns_json_422_not_redirect(): void
    {
        $this->from(route('admin.settingPrices.indexMenu'))
            ->post(route('setting-prices.prolong-month.preview'), [
                'selectedDate' => 'Foo 2026',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['selectedDate'])
            ->assertJsonPath('errors.selectedDate.0', 'Укажите корректный месяц.');

        $this->assertDatabaseMissing('users_prices', [
            'user_id' => $this->student->id,
            'new_month' => '2026-10-01',
        ]);
    }

    public function test_preview_without_ajax_missing_month_returns_json_422(): void
    {
        $this->from(route('admin.settingPrices.indexMenu'))
            ->post(route('setting-prices.prolong-month.preview'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['selectedDate'])
            ->assertJsonPath('errors.selectedDate.0', 'Укажите месяц.');
    }
}
