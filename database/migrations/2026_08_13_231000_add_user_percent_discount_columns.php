<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Персональная скидка ученика (%) + основание.
 * Снимок на начислении (users_prices) и назначении (user_lesson_packages):
 * иконка «%» и tooltip показывают то, что применилось в момент выставления.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('discount_percent')->nullable()->after('comment');
            $table->string('discount_comment', 500)->nullable()->after('discount_percent');
        });

        Schema::table('users_prices', function (Blueprint $table) {
            $table->unsignedTinyInteger('discount_percent')->nullable()->after('price_cents');
            $table->string('discount_comment', 500)->nullable()->after('discount_percent');
        });

        Schema::table('user_lesson_packages', function (Blueprint $table) {
            $table->unsignedTinyInteger('discount_percent')->nullable()->after('fee_amount_cents');
            $table->string('discount_comment', 500)->nullable()->after('discount_percent');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['discount_percent', 'discount_comment']);
        });

        Schema::table('users_prices', function (Blueprint $table) {
            $table->dropColumn(['discount_percent', 'discount_comment']);
        });

        Schema::table('user_lesson_packages', function (Blueprint $table) {
            $table->dropColumn(['discount_percent', 'discount_comment']);
        });
    }
};
