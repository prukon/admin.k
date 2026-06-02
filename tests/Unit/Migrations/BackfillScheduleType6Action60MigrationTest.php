<?php

namespace Tests\Unit\Migrations;

use App\Enums\AuditEvent;
use App\Models\MyLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Crm\CrmTestCase;

class BackfillScheduleType6Action60MigrationTest extends CrmTestCase
{
    use RefreshDatabase;

    #[Test]
    public function migration_fills_event_for_type6_action60_rows(): void
    {
        MyLog::query()->create([
            'type' => 6,
            'action' => 60,
            'partner_id' => $this->partner->id,
            'description' => 'legacy individual schedule',
            'created_at' => now(),
        ]);

        $migration = require database_path(
            'migrations/2026_06_02_120000_backfill_schedule_type6_action60_on_my_logs_table.php'
        );
        $migration->up();

        $this->assertDatabaseHas('my_logs', [
            'type' => 6,
            'action' => 60,
            'event' => AuditEvent::ScheduleUserRangeUpdated->value,
            'level' => AuditEvent::ScheduleUserRangeUpdated->level()->value,
        ]);
    }
}
