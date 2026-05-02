<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_lesson_packages', function (Blueprint $table) {
            $table->decimal('fee_amount', 15, 2)->nullable()->after('lessons_remaining');
            $table->boolean('is_paid')->default(false)->after('fee_amount');
        });

        DB::statement('
            UPDATE user_lesson_packages ulp
            INNER JOIN lesson_packages lp ON lp.id = ulp.lesson_package_id
            SET ulp.fee_amount = ROUND(lp.price_cents / 100, 2)
            WHERE ulp.fee_amount IS NULL
        ');
    }

    public function down(): void
    {
        Schema::table('user_lesson_packages', function (Blueprint $table) {
            $table->dropColumn(['fee_amount', 'is_paid']);
        });
    }
};
