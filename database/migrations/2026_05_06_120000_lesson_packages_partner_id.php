<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lesson_packages', function (Blueprint $table) {
            $table->foreignId('partner_id')
                ->nullable()
                ->after('id')
                ->constrained('partners');
        });

        DB::statement('
            UPDATE lesson_packages lp
            INNER JOIN (
                SELECT ulp.lesson_package_id AS lid, MIN(u.partner_id) AS pid
                FROM user_lesson_packages ulp
                INNER JOIN users u ON u.id = ulp.user_id
                GROUP BY ulp.lesson_package_id
            ) x ON x.lid = lp.id
            SET lp.partner_id = x.pid
        ');

        $firstPartnerId = DB::table('partners')->orderBy('id')->value('id');
        if ($firstPartnerId !== null) {
            DB::table('lesson_packages')->whereNull('partner_id')->update(['partner_id' => $firstPartnerId]);
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE lesson_packages MODIFY partner_id BIGINT UNSIGNED NOT NULL');
        }
    }

    public function down(): void
    {
        Schema::table('lesson_packages', function (Blueprint $table) {
            $table->dropForeign(['partner_id']);
            $table->dropIndex(['partner_id']);
            $table->dropColumn('partner_id');
        });
    }
};
