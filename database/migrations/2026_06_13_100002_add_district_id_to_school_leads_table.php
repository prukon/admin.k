<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_leads', function (Blueprint $table) {
            $table->unsignedBigInteger('district_id')->nullable()->after('partner_widget_id');

            $table->index(['partner_id', 'district_id'], 'school_leads_partner_district_idx');

            $table->foreign('district_id', 'school_leads_district_fk')
                ->references('id')
                ->on('districts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('school_leads', function (Blueprint $table) {
            $table->dropForeign('school_leads_district_fk');
            $table->dropIndex('school_leads_partner_district_idx');
            $table->dropColumn('district_id');
        });
    }
};
