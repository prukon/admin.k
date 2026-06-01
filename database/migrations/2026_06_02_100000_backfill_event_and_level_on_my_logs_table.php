<?php

use App\Enums\AuditEvent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('my_logs')
            || ! Schema::hasColumn('my_logs', 'event')
            || ! Schema::hasColumn('my_logs', 'level')) {
            return;
        }

        DB::table('my_logs')
            ->whereNull('event')
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    $type = isset($row->type) && $row->type !== '' ? (int) $row->type : null;
                    $action = isset($row->action) && $row->action !== '' ? (int) $row->action : null;

                    $event = AuditEvent::fromLegacy($type, $action);
                    if ($event === null) {
                        continue;
                    }

                    DB::table('my_logs')
                        ->where('id', $row->id)
                        ->update([
                            'event' => $event->value,
                            'level' => $event->level()->value,
                        ]);
                }
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('my_logs')
            || ! Schema::hasColumn('my_logs', 'event')
            || ! Schema::hasColumn('my_logs', 'level')) {
            return;
        }

        DB::table('my_logs')
            ->whereNotNull('event')
            ->update([
                'event' => null,
                'level' => null,
            ]);
    }
};
