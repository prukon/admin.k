<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->unsignedBigInteger('location_id')->nullable()->after('partner_id');

            $table->index(['partner_id', 'location_id'], 'teams_partner_location_idx');

            $table->foreign('location_id', 'teams_location_fk')
                ->references('id')
                ->on('locations')
                ->nullOnDelete();
        });

        if (Schema::hasTable('location_team')) {
            $rows = DB::table('location_team')
                ->select('team_id', DB::raw('MIN(location_id) as location_id'))
                ->groupBy('team_id')
                ->get();

            foreach ($rows as $row) {
                DB::table('teams')
                    ->where('id', $row->team_id)
                    ->whereNull('location_id')
                    ->update(['location_id' => $row->location_id]);
            }

            Schema::dropIfExists('location_team');
        }

        if (Schema::hasColumn('teams', 'address')) {
            Schema::table('teams', function (Blueprint $table) {
                $table->dropColumn('address');
            });
        }

        $permissionIds = DB::table('permissions')
            ->where('name', 'groups.address.view')
            ->pluck('id');

        if ($permissionIds->isNotEmpty()) {
            DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
            DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        }
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->string('address')->nullable()->after('training_base');
        });

        Schema::create('location_team', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('partner_id');
            $table->unsignedBigInteger('location_id');
            $table->unsignedBigInteger('team_id');
            $table->timestamps();

            $table->unique(['location_id', 'team_id'], 'location_team_location_team_unique');
            $table->index(['partner_id', 'location_id'], 'location_team_partner_location_idx');
            $table->index(['partner_id', 'team_id'], 'location_team_partner_team_idx');

            $table->foreign('partner_id', 'location_team_partner_fk')
                ->references('id')
                ->on('partners')
                ->cascadeOnDelete();

            $table->foreign('location_id', 'location_team_location_fk')
                ->references('id')
                ->on('locations')
                ->cascadeOnDelete();

            $table->foreign('team_id', 'location_team_team_fk')
                ->references('id')
                ->on('teams')
                ->cascadeOnDelete();
        });

        $teams = DB::table('teams')
            ->whereNotNull('location_id')
            ->get(['id', 'partner_id', 'location_id']);

        foreach ($teams as $team) {
            DB::table('location_team')->insert([
                'partner_id' => $team->partner_id,
                'location_id' => $team->location_id,
                'team_id' => $team->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('teams', function (Blueprint $table) {
            $table->dropForeign('teams_location_fk');
            $table->dropIndex('teams_partner_location_idx');
            $table->dropColumn('location_id');
        });
    }
};
