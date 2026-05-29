<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->unsignedBigInteger('sport_type_id')->nullable()->after('title');

            $table->index(['partner_id', 'sport_type_id'], 'teams_partner_sport_type_idx');

            $table->foreign('sport_type_id', 'teams_sport_type_fk')
                ->references('id')
                ->on('sport_types')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropForeign('teams_sport_type_fk');
            $table->dropIndex('teams_partner_sport_type_idx');
            $table->dropColumn('sport_type_id');
        });
    }
};
