<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            // 1) отметка времени первой успешной регистрации
            if (!Schema::hasColumn('partners', 'registered_at')) {
                $table->timestamp('registered_at')->nullable()->after('sm_register_status');
            }
        });

        // 2) вычисляемое поле is_registered (tinyint 0/1) — STORED, чтобы можно было индексировать
        //    В MySQL 8.0 допускается GENERATED STORED с индексом
        $hasIsRegistered = Schema::hasColumn('partners', 'is_registered');
        if (!$hasIsRegistered) {
            DB::statement("
                ALTER TABLE `partners`
                ADD COLUMN `is_registered` TINYINT(1)
                AS (CASE WHEN `tinkoff_partner_id` IS NULL OR `tinkoff_partner_id` = '' THEN 0 ELSE 1 END)
                STORED
                AFTER `tinkoff_partner_id`
            ");
            DB::statement("CREATE INDEX `partners_is_registered_index` ON `partners`(`is_registered`)");
        }

        // 3) уникальный индекс на shopCode (tinkoff_partner_id)
        //    снимем старый обычный индекс (если он есть и не уникальный) и поставим UNIQUE
        $indexes = DB::select("SHOW INDEX FROM `partners` WHERE Column_name='tinkoff_partner_id'");
        $hasUnique = collect($indexes)->contains(fn($i) => (int)$i->Non_unique === 0);
        if (!$hasUnique) {
            // попытка удалить неуникальные индексы на этом поле
            foreach ($indexes as $idx) {
                if ((int)$idx->Non_unique === 1) {
                    DB::statement("DROP INDEX `{$idx->Key_name}` ON `partners`");
                }
            }
            Schema::table('partners', function (Blueprint $table) {
                $table->unique('tinkoff_partner_id', 'partners_tinkoff_partner_id_unique');
            });
        }
    }

    public function down(): void
    {
        // аккуратно откатываем, если нужно
        if (Schema::hasColumn('partners', 'is_registered')) {
            DB::statement("DROP INDEX `partners_is_registered_index` ON `partners`");
            DB::statement("ALTER TABLE `partners` DROP COLUMN `is_registered`");
        }
        Schema::table('partners', function (Blueprint $table) {
            if (Schema::hasColumn('partners', 'registered_at')) {
                $table->dropColumn('registered_at');
            }
            // уникальный индекс можно снять по необходимости
            $sm = DB::select("SHOW INDEX FROM `partners` WHERE Key_name='partners_tinkoff_partner_id_unique'");
            if ($sm) {
                $table->dropUnique('partners_tinkoff_partner_id_unique');
            }
        });
    }
};
