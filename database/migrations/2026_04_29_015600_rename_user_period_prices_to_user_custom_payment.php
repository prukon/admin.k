<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Backward-compatible for environments:
        // - prod: table exists as user_period_prices
        // - fresh install: create migration may already create new table name
        if (Schema::hasTable('user_period_prices') && !Schema::hasTable('user_custom_payment')) {
            Schema::rename('user_period_prices', 'user_custom_payment');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('user_custom_payment') && !Schema::hasTable('user_period_prices')) {
            Schema::rename('user_custom_payment', 'user_period_prices');
        }
    }
};

