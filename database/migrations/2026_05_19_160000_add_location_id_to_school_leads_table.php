<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_leads', function (Blueprint $table) {
            $table->unsignedBigInteger('location_id')->nullable()->after('partner_widget_id');

            $table->index(['partner_id', 'location_id'], 'school_leads_partner_location_idx');

            $table->foreign('location_id', 'school_leads_location_fk')
                ->references('id')
                ->on('locations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('school_leads', function (Blueprint $table) {
            $table->dropForeign('school_leads_location_fk');
            $table->dropIndex('school_leads_partner_location_idx');
            $table->dropColumn('location_id');
        });
    }
};
