<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Явная отметка, что текущий phone верифицирован (успешная 2FA)
            if (!Schema::hasColumn('users', 'phone_verified_at')) {
                $table->timestamp('phone_verified_at')->nullable()->after('phone');
            }

            // Переименуем "код/срок для нового номера" в осмысленные имена
            if (Schema::hasColumn('users', 'two_factor_phone_change_code')) {
                DB::statement('ALTER TABLE `users` CHANGE `two_factor_phone_change_code` `phone_change_new_code` VARCHAR(255) NULL');
            }
            if (Schema::hasColumn('users', 'two_factor_phone_change_expires_at')) {
                DB::statement('ALTER TABLE `users` CHANGE `two_factor_phone_change_expires_at` `phone_change_new_expires_at` TIMESTAMP NULL');
            }

            // Коды/сроки для подтверждения СТАРОГО номера (шаг 1)
            if (!Schema::hasColumn('users', 'phone_change_old_code')) {
                $table->string('phone_change_old_code', 255)->nullable()->after('two_factor_phone_pending');
            }
            if (!Schema::hasColumn('users', 'phone_change_old_expires_at')) {
                $table->timestamp('phone_change_old_expires_at')->nullable()->after('phone_change_old_code');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'phone_verified_at')) {
                $table->dropColumn('phone_verified_at');
            }
            if (Schema::hasColumn('users', 'phone_change_old_code')) {
                $table->dropColumn('phone_change_old_code');
            }
            if (Schema::hasColumn('users', 'phone_change_old_expires_at')) {
                $table->dropColumn('phone_change_old_expires_at');
            }

            // Вернём старые имена (если нужно откатить)
            if (Schema::hasColumn('users', 'phone_change_new_code')) {
                DB::statement('ALTER TABLE `users` CHANGE `phone_change_new_code` `two_factor_phone_change_code` VARCHAR(255) NULL');
            }
            if (Schema::hasColumn('users', 'phone_change_new_expires_at')) {
                DB::statement('ALTER TABLE `users` CHANGE `phone_change_new_expires_at` `two_factor_phone_change_expires_at` TIMESTAMP NULL');
            }
        });
    }
};
