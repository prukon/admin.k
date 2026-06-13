<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->unsignedBigInteger('district_id')->nullable()->after('partner_id');

            $table->index(['partner_id', 'district_id'], 'locations_partner_district_idx');
            $table->index(['district_id', 'is_enabled'], 'locations_district_enabled_idx');

            $table->foreign('district_id', 'locations_district_fk')
                ->references('id')
                ->on('districts')
                ->restrictOnDelete();
        });

        Schema::table('locations', function (Blueprint $table) {
            $table->dropUnique('locations_partner_name_unique');
        });

        // MySQL treats NULL as distinct in UNIQUE(partner_id, district_id, name).
        // COALESCE(district_id, 0) сохраняет уникальность имён объектов без района внутри партнёра.
        DB::statement(
            'ALTER TABLE locations ADD COLUMN district_id_unique_bucket BIGINT UNSIGNED AS (COALESCE(district_id, 0)) STORED AFTER district_id'
        );

        Schema::table('locations', function (Blueprint $table) {
            $table->unique(
                ['partner_id', 'district_id_unique_bucket', 'name'],
                'locations_partner_district_bucket_name_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropUnique('locations_partner_district_bucket_name_unique');
        });

        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn('district_id_unique_bucket');
        });

        Schema::table('locations', function (Blueprint $table) {
            $table->unique(['partner_id', 'name'], 'locations_partner_name_unique');
        });

        Schema::table('locations', function (Blueprint $table) {
            $table->dropForeign('locations_district_fk');
            $table->dropIndex('locations_partner_district_idx');
            $table->dropIndex('locations_district_enabled_idx');
            $table->dropColumn('district_id');
        });
    }
};
