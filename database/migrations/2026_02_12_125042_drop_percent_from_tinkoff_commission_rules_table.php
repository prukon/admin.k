<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tinkoff_commission_rules', function (Blueprint $table) {
            if (Schema::hasColumn('tinkoff_commission_rules', 'percent')) {
                $table->dropColumn('percent');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tinkoff_commission_rules', function (Blueprint $table) {
            if (!Schema::hasColumn('tinkoff_commission_rules', 'percent')) {
                $table->decimal('percent', 8, 2)->default(0);
            }
        });
    }
};