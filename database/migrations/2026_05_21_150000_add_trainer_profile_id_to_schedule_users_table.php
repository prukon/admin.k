<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('schedule_users')
            || Schema::hasColumn('schedule_users', 'trainer_profile_id')) {
            return;
        }

        Schema::table('schedule_users', function (Blueprint $table) {
            $table->unsignedBigInteger('trainer_profile_id')
                ->nullable()
                ->after('status_id');

            $table->foreign('trainer_profile_id', 'schedule_users_trainer_profile_fk')
                ->references('id')
                ->on('trainer_profiles')
                ->nullOnDelete();

            $table->index(['trainer_profile_id', 'date'], 'schedule_users_trainer_date_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('schedule_users')
            || ! Schema::hasColumn('schedule_users', 'trainer_profile_id')) {
            return;
        }

        Schema::table('schedule_users', function (Blueprint $table) {
            $table->dropForeign('schedule_users_trainer_profile_fk');
            $table->dropIndex('schedule_users_trainer_date_idx');
            $table->dropColumn('trainer_profile_id');
        });
    }
};
