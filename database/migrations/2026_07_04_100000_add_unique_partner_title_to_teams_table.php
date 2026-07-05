<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('teams')) {
            return;
        }

        $this->deduplicateActiveTeamTitles();

        Schema::table('teams', function (Blueprint $table) {
            $table->unique(['partner_id', 'title'], 'teams_partner_title_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('teams')) {
            return;
        }

        Schema::table('teams', function (Blueprint $table) {
            $table->dropUnique('teams_partner_title_unique');
        });
    }

    private function deduplicateActiveTeamTitles(): void
    {
        $duplicateGroups = DB::table('teams')
            ->select('partner_id', 'title', DB::raw('COUNT(*) as cnt'))
            ->whereNull('deleted_at')
            ->groupBy('partner_id', 'title')
            ->having('cnt', '>', 1)
            ->get();

        foreach ($duplicateGroups as $group) {
            $teamIds = DB::table('teams')
                ->where('partner_id', (int) $group->partner_id)
                ->where('title', (string) $group->title)
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if (count($teamIds) < 2) {
                continue;
            }

            $canonicalId = $teamIds[0];
            $duplicateIds = array_slice($teamIds, 1);

            foreach ($duplicateIds as $duplicateId) {
                if (! Schema::hasTable('team_user')) {
                    break;
                }

                $pivotRows = DB::table('team_user')
                    ->where('team_id', $duplicateId)
                    ->get();

                foreach ($pivotRows as $pivotRow) {
                    $exists = DB::table('team_user')
                        ->where('team_id', $canonicalId)
                        ->where('user_id', (int) $pivotRow->user_id)
                        ->exists();

                    if ($exists) {
                        DB::table('team_user')
                            ->where('team_id', $duplicateId)
                            ->where('user_id', (int) $pivotRow->user_id)
                            ->delete();
                    } else {
                        DB::table('team_user')
                            ->where('team_id', $duplicateId)
                            ->where('user_id', (int) $pivotRow->user_id)
                            ->update(['team_id' => $canonicalId]);
                    }
                }

                DB::table('teams')
                    ->where('id', $duplicateId)
                    ->update(['deleted_at' => now()]);
            }
        }
    }
};
