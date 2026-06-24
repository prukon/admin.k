<?php

namespace Tests\Feature\Crm\Reports;

use App\Models\Payment;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamUserSyncService;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Отчёт «Платежи»: pivot team_user — несколько групп, фильтр, колонка без дублей строк.
 */
final class PaymentReportTeamPivotFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        session(['current_partner' => $this->partner->id]);
        $this->asAdmin();
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<int, array<string, mixed>>
     */
    private function paymentRows(array $query = []): array
    {
        $json = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('payments.getPayments', $query))
            ->assertOk()
            ->json();

        return $json['data'] ?? [];
    }

    public function test_team_title_shows_paid_group_when_team_id_set(): void
    {
        $teamA = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Альфа']);
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Бета']);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $teamA->id,
        ]);

        DB::table('team_user')->insert([
            'partner_id' => $this->partner->id,
            'team_id' => $teamB->id,
            'user_id' => $student->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payment = Payment::factory()->create([
            'user_id' => $student->id,
            'partner_id' => $this->partner->id,
            'team_id' => $teamA->id,
            'team_title' => 'Альфа',
            'summ' => 1500,
        ]);

        $rows = collect($this->paymentRows(['filter_user_id' => $student->id]));
        $matches = $rows->where('id', $payment->id);

        $this->assertCount(1, $matches, 'Один платёж — одна строка в отчёте');
        $row = $matches->first();
        $teamTitle = html_entity_decode((string) ($row['team_title'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->assertSame('Альфа', $teamTitle);
        $this->assertStringNotContainsString('Бeta', $teamTitle);
        $this->assertStringNotContainsString('Бета', $teamTitle);
    }

    public function test_team_title_lists_all_groups_for_legacy_payment_without_team_id(): void
    {
        $teamA = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Альфа']);
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Бета']);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $teamA->id,
        ]);

        $sync = app(TeamUserSyncService::class);
        $sync->attachTeamForStudent($student, (int) $teamA->id);
        $sync->attachTeamForStudent($student, (int) $teamB->id);

        $payment = Payment::factory()->create([
            'user_id' => $student->id,
            'partner_id' => $this->partner->id,
            'team_id' => null,
            'team_title' => null,
            'summ' => 1500,
        ]);

        $rows = collect($this->paymentRows(['filter_user_id' => $student->id]));
        $matches = $rows->where('id', $payment->id);

        $this->assertCount(1, $matches);
        $teamTitle = html_entity_decode((string) ($matches->first()['team_title'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->assertStringContainsString('Альфа', $teamTitle);
        $this->assertStringContainsString('Бета', $teamTitle);
    }

    public function test_filter_by_team_uses_paid_team_id_when_set(): void
    {
        $teamA = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Группа A']);
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Группа B']);
        $teamC = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Группа C']);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $teamA->id,
        ]);

        DB::table('team_user')->insert([
            'partner_id' => $this->partner->id,
            'team_id' => $teamB->id,
            'user_id' => $student->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payment = Payment::factory()->create([
            'user_id' => $student->id,
            'partner_id' => $this->partner->id,
            'team_id' => $teamA->id,
            'team_title' => 'Группа A',
            'summ' => 900,
        ]);

        $onlyA = collect($this->paymentRows(['filter_team_id' => $teamA->id]));
        $this->assertTrue($onlyA->contains(fn ($r) => (int) ($r['id'] ?? 0) === $payment->id));

        $onlyB = collect($this->paymentRows(['filter_team_id' => $teamB->id]));
        $this->assertFalse($onlyB->contains(fn ($r) => (int) ($r['id'] ?? 0) === $payment->id));

        $onlyC = collect($this->paymentRows(['filter_team_id' => $teamC->id]));
        $this->assertFalse($onlyC->contains(fn ($r) => (int) ($r['id'] ?? 0) === $payment->id));
    }

    public function test_filter_by_team_shows_legacy_payment_without_team_id_if_student_in_team(): void
    {
        $teamA = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Группа A']);
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Группа B']);
        $teamC = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'Группа C']);

        $student = User::factory()->create([
            'partner_id' => $this->partner->id,
            'team_id' => $teamA->id,
        ]);

        $sync = app(TeamUserSyncService::class);
        $sync->attachTeamForStudent($student, (int) $teamA->id);
        $sync->attachTeamForStudent($student, (int) $teamB->id);

        $payment = Payment::factory()->create([
            'user_id' => $student->id,
            'partner_id' => $this->partner->id,
            'team_id' => null,
            'team_title' => null,
            'summ' => 900,
        ]);

        $onlyA = collect($this->paymentRows(['filter_team_id' => $teamA->id]));
        $this->assertTrue($onlyA->contains(fn ($r) => (int) ($r['id'] ?? 0) === $payment->id));

        $onlyB = collect($this->paymentRows(['filter_team_id' => $teamB->id]));
        $this->assertTrue($onlyB->contains(fn ($r) => (int) ($r['id'] ?? 0) === $payment->id));

        $onlyC = collect($this->paymentRows(['filter_team_id' => $teamC->id]));
        $this->assertFalse($onlyC->contains(fn ($r) => (int) ($r['id'] ?? 0) === $payment->id));
    }
}
