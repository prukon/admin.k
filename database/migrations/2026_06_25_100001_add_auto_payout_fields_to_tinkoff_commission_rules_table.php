<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tinkoff_commission_rules', function (Blueprint $table) {
            $table->boolean('auto_payout_enabled')
                ->default(false)
                ->after('is_enabled')
                ->comment('Автовыплата партнёру после CONFIRMED (e2c)');

            $table->unsignedSmallInteger('auto_payout_delay_hours')
                ->default(0)
                ->after('auto_payout_enabled')
                ->comment('Задержка автовыплаты после CONFIRMED, часы; 0 = сразу');
        });
    }

    public function down(): void
    {
        Schema::table('tinkoff_commission_rules', function (Blueprint $table) {
            $table->dropColumn(['auto_payout_enabled', 'auto_payout_delay_hours']);
        });
    }
};
