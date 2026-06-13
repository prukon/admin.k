<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->unsignedBigInteger('admin_user_id')->nullable()->after('district_id');

            $table->index('admin_user_id', 'locations_admin_user_id_idx');

            $table->foreign('admin_user_id', 'locations_admin_user_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropForeign('locations_admin_user_fk');
            $table->dropIndex('locations_admin_user_id_idx');
            $table->dropColumn('admin_user_id');
        });
    }
};
