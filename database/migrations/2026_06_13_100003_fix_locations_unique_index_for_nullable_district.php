<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('locations', 'district_id_unique_bucket')) {
            return;
        }

        Schema::table('locations', function (Blueprint $table) {
            $table->dropUnique('locations_partner_district_name_unique');
        });

        // MySQL treats NULL as distinct in UNIQUE(partner_id, district_id, name),
        // поэтому объекты без района не защищены от дублей имён.
        // COALESCE(district_id, 0) сохраняет прежнюю уникальность внутри партнёра для district_id IS NULL.
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
            $table->unique(['partner_id', 'district_id', 'name'], 'locations_partner_district_name_unique');
        });
    }
};
