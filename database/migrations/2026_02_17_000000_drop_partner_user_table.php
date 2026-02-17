<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('partner_user');
    }

    public function down(): void
    {
        // Таблица удалена намеренно: модель "1 user -> 1 partner" через users.partner_id.
        // Восстановление при откате не выполняем.
    }
};

