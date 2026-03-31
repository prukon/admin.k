<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Важно: хотим хранить статусы выплат e2c прозрачно (ENUM).
        // Добавляем недостающие статусы из протокола A2C_V2:
        // NEW, AUTHORIZING, CHECKING, COMPLETING.
        //
        // Текущие (оставляем): INITIATED (наш внутренний), CREDIT_CHECKING, CHECKED, COMPLETED, REJECTED.
        if (!Schema::hasTable('tinkoff_payouts')) {
            return;
        }

        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement(
                "ALTER TABLE `tinkoff_payouts` MODIFY `status` ENUM(
                    'INITIATED',
                    'NEW',
                    'AUTHORIZING',
                    'CHECKING',
                    'CREDIT_CHECKING',
                    'CHECKED',
                    'COMPLETING',
                    'COMPLETED',
                    'REJECTED'
                ) NOT NULL DEFAULT 'INITIATED'"
            );
        }
        // Другие драйверы не поддерживаем в этом проекте (исторически MySQL/MariaDB).
    }

    public function down(): void
    {
        if (!Schema::hasTable('tinkoff_payouts')) {
            return;
        }

        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            // Откат может не пройти, если в таблице уже есть записи с новыми статусами.
            // Поэтому делаем "мягкий" откат: пытаемся вернуть исходный ENUM.
            DB::statement(
                "ALTER TABLE `tinkoff_payouts` MODIFY `status` ENUM(
                    'INITIATED',
                    'CREDIT_CHECKING',
                    'COMPLETED',
                    'REJECTED',
                    'CHECKED'
                ) NOT NULL DEFAULT 'INITIATED'"
            );
        }
    }
};

