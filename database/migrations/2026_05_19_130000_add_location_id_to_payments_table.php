<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('location_id')->nullable()->after('team_title');

            $table->index('location_id', 'payments_location_id_idx');

            $table->foreign('location_id', 'payments_location_fk')
                ->references('id')
                ->on('locations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign('payments_location_fk');
            $table->dropIndex('payments_location_id_idx');
            $table->dropColumn('location_id');
        });
    }
};
