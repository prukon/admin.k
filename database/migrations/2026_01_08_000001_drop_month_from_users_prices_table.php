<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users_prices', 'month')) {
            Schema::table('users_prices', function (Blueprint $table) {
                $table->dropColumn('month');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('users_prices', 'month')) {
            Schema::table('users_prices', function (Blueprint $table) {
                // Историческое поле (старый формат "Месяц Год"), оставляем nullable
                $table->string('month')->nullable();
            });
        }
    }
};


