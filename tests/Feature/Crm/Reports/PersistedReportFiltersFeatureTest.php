<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Reports;

use App\Models\Team;
use App\Models\UserTableSetting;
use Tests\Feature\Crm\CrmTestCase;

final class PersistedReportFiltersFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        session(['current_partner' => $this->partner->id]);
        $this->asAdmin();
    }

    public function test_guest_cannot_save_payments_filters(): void
    {
        auth()->logout();

        $this->postJson(route('reports.payments.filters.save'), [
            'payment_month' => '2026-03',
        ])->assertStatus(401);
    }

    public function test_user_without_reports_view_cannot_save_filters(): void
    {
        $actor = $this->createUserWithoutPermission('reports.view', $this->partner);
        $this->actingAs($actor);

        $this->postJson(route('reports.payments.filters.save'), [
            'payment_month' => '2026-03',
        ])->assertForbidden();

        $this->postJson(route('reports.payments.monthly.filters.save'), [
            'payment_provider' => 'tbank',
        ])->assertForbidden();

        $this->postJson(route('reports.ltv.filters.save'), [
            'payment_provider' => 'tbank',
        ])->assertForbidden();

        $this->postJson(route('reports.debts.filters.save'), [
            'debt_month' => '2026-03',
        ])->assertForbidden();
    }

    public function test_apply_restores_payments_filters_for_the_same_admin_only(): void
    {
        $team = Team::factory()->create([
            'partner_id' => $this->partner->id,
            'is_enabled' => true,
        ]);

        UserTableSetting::query()->create([
            'user_id' => $this->user->id,
            'table_key' => 'reports_payments',
            'columns' => ['summ' => false],
            'page_length' => 50,
        ]);

        $this->postJson(route('reports.payments.filters.save'), [
            'payment_month' => '2026-03',
            'payment_provider' => 'tbank',
            'status' => 'inactive',
            'filter_team_id' => [$team->id],
        ])->assertOk()->assertJson(['success' => true]);

        $row = UserTableSetting::query()
            ->where('user_id', $this->user->id)
            ->where('table_key', 'reports_payments')
            ->first();

        $this->assertNotNull($row);
        $this->assertSame(50, (int) $row->page_length);
        $this->assertFalse($row->columns['summ']);
        $this->assertSame('2026-03', $row->filters['payment_month']);
        $this->assertSame('tbank', $row->filters['payment_provider']);
        $this->assertSame('inactive', $row->filters['status']);
        $this->assertSame([(string) $team->id], $row->filters['filter_team_id']);

        $html = $this->get(route('payments'))->assertOk()->getContent();
        $this->assertStringContainsString('value="2026-03"', $html);
        $this->assertStringContainsString('value="inactive" selected', $html);
        $this->assertStringContainsString('>'.$team->title.'</option>', $html);

        $other = $this->createUserWithRole('admin', $this->partner);
        $this->actingAs($other);
        $otherHtml = $this->get(route('payments'))->assertOk()->getContent();
        $this->assertStringNotContainsString('value="2026-03"', $otherHtml);
        $this->assertDatabaseMissing('user_table_settings', [
            'user_id' => $other->id,
            'table_key' => 'reports_payments',
        ]);
    }

    public function test_query_string_overwrites_saved_payments_filters(): void
    {
        $this->postJson(route('reports.payments.filters.save'), [
            'payment_month' => '2026-03',
            'payment_provider' => 'tbank',
            'status' => 'inactive',
        ])->assertOk();

        $this->get(route('payments', ['payment_provider' => 'robokassa']))->assertOk();

        $row = UserTableSetting::query()
            ->where('user_id', $this->user->id)
            ->where('table_key', 'reports_payments')
            ->first();

        $this->assertSame('robokassa', $row->filters['payment_provider']);
        $this->assertSame('', $row->filters['payment_month']);
        $this->assertSame('active', $row->filters['status']);
    }

    public function test_reset_stores_default_payments_filters(): void
    {
        $this->postJson(route('reports.payments.filters.save'), [
            'payment_month' => '2026-04',
            'status' => '',
        ])->assertOk();

        $this->postJson(route('reports.payments.filters.save'), [
            'reset' => 1,
        ])->assertOk();

        $row = UserTableSetting::query()
            ->where('user_id', $this->user->id)
            ->where('table_key', 'reports_payments')
            ->first();

        $this->assertSame('', $row->filters['payment_month']);
        $this->assertSame('active', $row->filters['status']);
    }

    public function test_invalid_payments_filter_returns_field_error_and_does_not_save(): void
    {
        $this->postJson(route('reports.payments.filters.save'), [
            'payment_provider' => 'paypal',
            'operation_date_from' => '2026-05-10',
            'operation_date_to' => '2026-05-01',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['payment_provider', 'operation_date_to']);

        $this->assertDatabaseMissing('user_table_settings', [
            'user_id' => $this->user->id,
            'table_key' => 'reports_payments',
        ]);
    }

    public function test_foreign_team_is_rejected(): void
    {
        $foreignTeam = Team::factory()->create([
            'partner_id' => $this->foreignPartner->id,
            'is_enabled' => true,
        ]);

        $this->postJson(route('reports.payments.filters.save'), [
            'filter_team_id' => [$foreignTeam->id],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['filter_team_id.0']);
    }

    public function test_monthly_ltv_and_debts_filters_restore_on_next_visit(): void
    {
        $this->postJson(route('reports.payments.monthly.filters.save'), [
            'payment_provider' => 'robokassa',
            'payment_month' => '2026-01',
            'status' => 'inactive',
        ])->assertOk();

        $monthly = $this->get(route('reports.payments.monthly'))->assertOk()->getContent();
        $this->assertStringContainsString('value="2026-01"', $monthly);
        $this->assertStringContainsString('value="robokassa" selected', $monthly);

        $this->postJson(route('reports.ltv.filters.save'), [
            'operation_date_from' => '2026-02-01',
            'status' => 'inactive',
        ])->assertOk();

        $ltv = $this->get(route('reports.ltv'))->assertOk()->getContent();
        $this->assertStringContainsString('value="2026-02-01"', $ltv);

        $this->postJson(route('reports.debts.filters.save'), [
            'debt_month' => '2026-06',
            'status' => '',
        ])->assertOk();

        $debts = $this->get(route('debts'))->assertOk()->getContent();
        $this->assertStringContainsString('value="2026-06"', $debts);
        $this->assertStringNotContainsString('value="active" selected', $debts);
        $this->assertStringNotContainsString('value="inactive" selected', $debts);

        $this->assertSame('2026-01', UserTableSetting::query()->where('table_key', 'reports_payments_monthly')->value('filters')['payment_month'] ?? null);
    }

    public function test_ltv_teams_and_locations_filters_restore_on_next_visit(): void
    {
        $denied = $this->createUserWithoutPermission('reports.ltv.teams.view', $this->partner);
        $this->actingAs($denied);
        $this->postJson(route('reports.ltv.teams.filters.save'), [
            'payment_provider' => 'tbank',
        ])->assertForbidden();

        $this->actingAs($this->user);

        $this->postJson(route('reports.ltv.teams.filters.save'), [
            'payment_month' => '2026-07',
            'payment_provider' => 'tbank',
            'status' => 'inactive',
        ])->assertOk();

        $teams = $this->get(route('reports.ltv.teams'))->assertOk()->getContent();
        $this->assertStringContainsString('value="2026-07"', $teams);
        $this->assertStringContainsString('value="tbank" selected', $teams);
        $this->assertStringContainsString('value="inactive" selected', $teams);

        $this->get(route('reports.ltv.teams', ['payment_provider' => 'robokassa']))->assertOk();
        $teamsRow = UserTableSetting::query()
            ->where('user_id', $this->user->id)
            ->where('table_key', 'reports_ltv_teams')
            ->first();
        $this->assertSame('robokassa', $teamsRow->filters['payment_provider']);
        $this->assertSame('', $teamsRow->filters['payment_month']);

        $noLocations = $this->createUserWithoutPermission('reports.ltv.locations.view', $this->partner);
        $this->actingAs($noLocations);
        $this->postJson(route('reports.ltv.locations.filters.save'), [
            'payment_provider' => 'tbank',
        ])->assertForbidden();

        $this->actingAs($this->user);
        $this->postJson(route('reports.ltv.locations.filters.save'), [
            'operation_date_from' => '2026-08-01',
            'status' => 'inactive',
        ])->assertOk();

        $locations = $this->get(route('reports.ltv.locations'))->assertOk()->getContent();
        $this->assertStringContainsString('value="2026-08-01"', $locations);
        $this->assertStringContainsString('value="inactive" selected', $locations);

        $this->postJson(route('reports.ltv.locations.filters.save'), [
            'payment_provider' => 'paypal',
        ])->assertStatus(422)->assertJsonValidationErrors(['payment_provider']);
    }

    public function test_column_save_does_not_wipe_filters(): void
    {
        $this->postJson(route('reports.payments.filters.save'), [
            'payment_method' => 'card',
        ])->assertOk();

        $this->postJson('/admin/reports/payments/columns-settings', [
            'columns' => ['user_name' => true, 'summ' => false],
        ])->assertOk();

        $row = UserTableSetting::query()
            ->where('user_id', $this->user->id)
            ->where('table_key', 'reports_payments')
            ->first();

        $this->assertSame('card', $row->filters['payment_method']);
        $this->assertFalse($row->columns['summ']);
    }
}
