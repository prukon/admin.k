<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_admin_user', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('partner_id');
            $table->unsignedBigInteger('location_id');
            $table->unsignedBigInteger('user_id');

            $table->timestamps();

            $table->unique(['location_id', 'user_id'], 'location_admin_user_location_user_unique');
            $table->index(['partner_id', 'location_id'], 'location_admin_user_partner_location_idx');
            $table->index(['partner_id', 'user_id'], 'location_admin_user_partner_user_idx');

            $table->foreign('partner_id', 'location_admin_user_partner_fk')
                ->references('id')
                ->on('partners')
                ->cascadeOnDelete();

            $table->foreign('location_id', 'location_admin_user_location_fk')
                ->references('id')
                ->on('locations')
                ->cascadeOnDelete();

            $table->foreign('user_id', 'location_admin_user_user_fk')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });

        if (Schema::hasColumn('locations', 'admin_user_id')) {
            $rows = DB::table('locations')
                ->whereNotNull('admin_user_id')
                ->get(['id', 'partner_id', 'admin_user_id']);

            $now = now();

            foreach ($rows as $row) {
                DB::table('location_admin_user')->insert([
                    'partner_id' => $row->partner_id,
                    'location_id' => $row->id,
                    'user_id' => $row->admin_user_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            Schema::table('locations', function (Blueprint $table) {
                $table->dropForeign('locations_admin_user_fk');
                $table->dropIndex('locations_admin_user_id_idx');
                $table->dropColumn('admin_user_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->unsignedBigInteger('admin_user_id')->nullable()->after('district_id');
            $table->index('admin_user_id', 'locations_admin_user_id_idx');
            $table->foreign('admin_user_id', 'locations_admin_user_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        $rows = DB::table('location_admin_user')
            ->select('location_id', DB::raw('MIN(user_id) as user_id'))
            ->groupBy('location_id')
            ->get();

        foreach ($rows as $row) {
            DB::table('locations')
                ->where('id', $row->location_id)
                ->update(['admin_user_id' => $row->user_id]);
        }

        Schema::dropIfExists('location_admin_user');
    }
};
