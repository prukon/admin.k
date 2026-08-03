<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Переименование description у прав группы setPrices (только подписи в матрице).
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = Carbon::now();

        $map = [
            'setPrices.cabinetSeasons.view' => 'Оплата сезонов',
            'setPrices.customPayments.view' => 'Дополнительные платежи',
            'setPrices.packageAssignments.view' => 'Назначение абонементов',
        ];

        foreach ($map as $name => $description) {
            DB::table('permissions')
                ->where('name', $name)
                ->update([
                    'description' => $description,
                    'updated_at'  => $now,
                ]);
        }
    }

    public function down(): void
    {
        $now = Carbon::now();

        $map = [
            'setPrices.cabinetSeasons.view' => 'Консоль: просмотр и оплата сезонов (месячных начислений)',
            'setPrices.customPayments.view' => 'Установка цен / Консоль: просмотр дополнительных платежей',
            'setPrices.packageAssignments.view' => 'Установка цен / Консоль: назначение абонементов',
        ];

        foreach ($map as $name => $description) {
            DB::table('permissions')
                ->where('name', $name)
                ->update([
                    'description' => $description,
                    'updated_at'  => $now,
                ]);
        }
    }
};
