<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Уникальный индекс (partner_id, name(191)) — name у тебя TEXT, поэтому префикс.
        // Laravel Schema Builder не умеет префикс для TEXT, делаем raw SQL.
        try {
            DB::statement('CREATE UNIQUE INDEX settings_partner_name_unique ON settings (partner_id, name(191))');
        } catch (\Throwable $e) {
            // если индекс уже есть или движок не поддерживает — просто логнём и продолжим
            logger()->warning('settings_partner_name_unique already exists or cannot be created', [
                'message' => $e->getMessage(),
            ]);
        }

        // Инициализируем глобальную настройку force_2fa_admins (для всех партнёров => partner_id IS NULL)
        $exists = DB::table('settings')
            ->whereNull('partner_id')
            ->where('name', 'force_2fa_admins')
            ->exists();

        if (!$exists) {
            DB::table('settings')->insert([
                'name'       => 'force_2fa_admins',
                'status'     => 0,          // 0 = выключено
                'date'       => null,
                'text'       => 'Обязательная 2FA для роли 10 (админ). 0/1.',
                'partner_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Удалим саму настройку (безопасно)
        DB::table('settings')
            ->whereNull('partner_id')
            ->where('name', 'force_2fa_admins')
            ->delete();

        // Снимем индекс (если есть)
        try {
            DB::statement('DROP INDEX settings_partner_name_unique ON settings');
        } catch (\Throwable $e) {
            logger()->warning('Cannot drop settings_partner_name_unique', ['message' => $e->getMessage()]);
        }
    }
};
