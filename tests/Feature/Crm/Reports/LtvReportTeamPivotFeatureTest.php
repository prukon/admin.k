<?php

namespace Tests\Feature\Crm\Reports;

use App\Models\Payment;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Crm\CrmTestCase;

/**
 * Отчёт LTV: одна строка на ученика при нескольких группах, группы через запятую.
 */
final class LtvReportTeamPivotFeatureTest extends CrmTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        session(['current_partner' => $this->partner->id]);
        $this->asAdmin();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function ltvRows(array $query = []): array
    {
        $json = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson(route('reports.ltv.data', $query))
            ->assertOk()
            ->json();

        return $json['data'] ?? [];
    }

    public function test_ltv_aggregates_one_row_per_student_with_comma_separated_teams(): void
    {
        $teamA = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'LTV-A']);
        $teamB = Team::factory()->create(['partner_id' => $this->partner->id, 'title' => 'LTV-B']);

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

        Payment::factory()->create(['user_id' => $student->id, 'summ' => 1000]);
        Payment::factory()->create(['user_id' => $student->id, 'summ' => 500]);

        $rows = collect($this->ltvRows(['filter_user_id' => $student->id]));
        $studentRows = $rows->filter(fn ($r) => (int) ($r['user_id'] ?? 0) === $student->id);

        $this->assertCount(1, $studentRows, 'LTV: один ученик — одна агрегированная строка');

        $row = $studentRows->first();
        $teamTitle = html_entity_decode((string) ($row['team_title'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->assertStringContainsString('LTV-A', $teamTitle);
        $this->assertStringContainsString('LTV-B', $teamTitle);
        $this->assertEquals(1500, (float) ($row['total_price'] ?? 0));
        $this->assertEquals(2, (int) ($row['payment_count'] ?? 0));
    }
}
