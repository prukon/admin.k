<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users_prices', 'team_id')) {
            Schema::table('users_prices', function (Blueprint $table) {
                $table->unsignedBigInteger('team_id')->nullable()->after('user_id');
            });
        }

        $this->backfillTeamIds();
        $this->deduplicateByUserTeamMonth();

        DB::table('users_prices')->whereNull('team_id')->delete();

        Schema::table('users_prices', function (Blueprint $table) {
            $table->unsignedBigInteger('team_id')->nullable(false)->change();
        });

        Schema::table('users_prices', function (Blueprint $table) {
            if ($this->indexExists('users_prices', 'users_prices_user_month_idx')) {
                $table->dropIndex('users_prices_user_month_idx');
            }
            if (! $this->indexExists('users_prices', 'users_prices_user_team_month_unique')) {
                $table->unique(['user_id', 'team_id', 'new_month'], 'users_prices_user_team_month_unique');
            }
            if (! $this->indexExists('users_prices', 'users_prices_team_month_idx')) {
                $table->index(['team_id', 'new_month'], 'users_prices_team_month_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users_prices', function (Blueprint $table) {
            if ($this->indexExists('users_prices', 'users_prices_user_team_month_unique')) {
                $table->dropUnique('users_prices_user_team_month_unique');
            }
            if ($this->indexExists('users_prices', 'users_prices_team_month_idx')) {
                $table->dropIndex('users_prices_team_month_idx');
            }
            if (Schema::hasColumn('users_prices', 'team_id')) {
                $table->dropColumn('team_id');
            }
            if (! $this->indexExists('users_prices', 'users_prices_user_month_idx')) {
                $table->index(['user_id', 'new_month'], 'users_prices_user_month_idx');
            }
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();

        $row = DB::selectOne(
            'SELECT 1 FROM information_schema.statistics
             WHERE table_schema = ? AND table_name = ? AND index_name = ?
             LIMIT 1',
            [$database, $table, $indexName]
        );

        return $row !== null;
    }

    private function backfillTeamIds(): void
    {
        DB::table('users_prices')
            ->orderBy('id')
            ->select(['id', 'user_id'])
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $teamId = $this->resolveTeamIdForUser((int) $row->user_id);
                    if ($teamId <= 0) {
                        continue;
                    }

                    DB::table('users_prices')
                        ->where('id', $row->id)
                        ->update(['team_id' => $teamId]);
                }
            });
    }

    private function resolveTeamIdForUser(int $userId): int
    {
        $user = DB::table('users')
            ->where('id', $userId)
            ->select(['id', 'partner_id', 'team_id'])
            ->first();

        if (! $user) {
            return 0;
        }

        $partnerId = (int) ($user->partner_id ?? 0);

        if ($partnerId > 0) {
            $fromPivot = DB::table('team_user')
                ->join('teams', 'teams.id', '=', 'team_user.team_id')
                ->where('team_user.user_id', $userId)
                ->where('team_user.partner_id', $partnerId)
                ->whereNull('teams.deleted_at')
                ->orderBy('teams.order_by')
                ->orderBy('teams.title')
                ->value('teams.id');

            if ($fromPivot) {
                return (int) $fromPivot;
            }
        }

        if (! empty($user->team_id)) {
            return (int) $user->team_id;
        }

        return 0;
    }

    /**
     * После backfill в одну группу могли попасть несколько строк за один месяц — оставляем одну.
     */
    private function deduplicateByUserTeamMonth(): void
    {
        $duplicateKeys = DB::table('users_prices')
            ->select(['user_id', 'team_id', 'new_month'])
            ->whereNotNull('team_id')
            ->groupBy('user_id', 'team_id', 'new_month')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicateKeys as $key) {
            $rows = DB::table('users_prices')
                ->where('user_id', $key->user_id)
                ->where('team_id', $key->team_id)
                ->where('new_month', $key->new_month)
                ->orderByDesc('is_paid')
                ->orderByDesc('price')
                ->orderByDesc('id')
                ->get(['id']);

            $keepId = $rows->first()->id ?? null;
            if (! $keepId) {
                continue;
            }

            DB::table('users_prices')
                ->where('user_id', $key->user_id)
                ->where('team_id', $key->team_id)
                ->where('new_month', $key->new_month)
                ->where('id', '!=', $keepId)
                ->delete();
        }
    }
};
