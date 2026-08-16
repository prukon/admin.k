<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_lesson_package_public_pay_links', function (Blueprint $table) {
            $table->string('short_code', 12)->nullable()->charset('ascii')->collation('ascii_bin')->after('token');
            $table->unique('short_code', 'ulp_pub_pay_short_uq');
        });
    }

    public function down(): void
    {
        Schema::table('user_lesson_package_public_pay_links', function (Blueprint $table) {
            $table->dropUnique('ulp_pub_pay_short_uq');
            $table->dropColumn('short_code');
        });
    }
};
