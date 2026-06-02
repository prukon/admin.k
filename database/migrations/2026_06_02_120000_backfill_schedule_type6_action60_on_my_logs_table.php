<?php

use App\Enums\AuditEvent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Prod: type=6 + action=60 — индивидуальное расписание (до появления колонки event).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('my_logs')
            || ! Schema::hasColumn('my_logs', 'event')
            || ! Schema::hasColumn('my_logs', 'level')) {
            return;
        }

        $event = AuditEvent::ScheduleUserRangeUpdated;

        DB::table('my_logs')
            ->whereNull('event')
            ->where('type', 6)
            ->where('action', 60)
            ->update([
                'event' => $event->value,
                'level' => $event->level()->value,
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('my_logs')
            || ! Schema::hasColumn('my_logs', 'event')) {
            return;
        }

        DB::table('my_logs')
            ->where('type', 6)
            ->where('action', 60)
            ->where('event', AuditEvent::ScheduleUserRangeUpdated->value)
            ->update([
                'event' => null,
                'level' => null,
            ]);
    }
};
