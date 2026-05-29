<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_leads', function (Blueprint $table) {
            $table->unsignedBigInteger('sport_type_id')->nullable()->after('location_id');

            $table->index(['partner_id', 'sport_type_id'], 'school_leads_partner_sport_type_idx');

            $table->foreign('sport_type_id', 'school_leads_sport_type_fk')
                ->references('id')
                ->on('sport_types')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('school_leads', function (Blueprint $table) {
            $table->dropForeign('school_leads_sport_type_fk');
            $table->dropIndex('school_leads_partner_sport_type_idx');
            $table->dropColumn('sport_type_id');
        });
    }
};
