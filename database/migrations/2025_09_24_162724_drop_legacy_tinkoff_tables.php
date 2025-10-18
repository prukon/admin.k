<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        // Снесём только старые/конфликтующие таблицы, если остались от прошлых попыток
        Schema::dropIfExists('tinkoff_payments');
        // если вдруг где-то лежали старые версии — добавь здесь:
        // Schema::dropIfExists('tinkoff_payouts');
        // Schema::dropIfExists('tinkoff_payout_status_logs');
        // Schema::dropIfExists('tinkoff_commission_rules');

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        // Ничего не восстанавливаем осознанно
    }
};
