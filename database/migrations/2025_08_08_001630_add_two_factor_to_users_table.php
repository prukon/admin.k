<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Добавляет поля для SMS-2FA:
     * - phone
     * - two_factor_enabled
     * - two_factor_code
     * - two_factor_expires_at
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Телефон в формате +7XXXXXXXXXX. Длина с запасом, индекс для быстрых выборок.
            if (!Schema::hasColumn('users', 'phone')) {
                $table->string('phone', 20)
                    ->nullable()
                    ->after('email')
                    ->index()
                    ->comment('Номер телефона для SMS-2FA (+7XXXXXXXXXX)');
            }

            if (!Schema::hasColumn('users', 'two_factor_enabled')) {
                $table->boolean('two_factor_enabled')
                    ->default(false)
                    ->after('phone')
                    ->comment('Флаг включения 2FA (SMS)');
            }

            if (!Schema::hasColumn('users', 'two_factor_code')) {
                $table->string('two_factor_code', 10)
                    ->nullable()
                    ->after('two_factor_enabled')
                    ->comment('Одноразовый код подтверждения (6 цифр)');
            }

            if (!Schema::hasColumn('users', 'two_factor_expires_at')) {
                $table->timestamp('two_factor_expires_at')
                    ->nullable()
                    ->after('two_factor_code')
                    ->comment('Срок действия кода 2FA');
            }
        });
    }

    /**
     * Откатывает изменения.
     * Удаляем по одному столбцу (без зависимости от doctrine/dbal).
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'two_factor_expires_at')) {
                $table->dropColumn('two_factor_expires_at');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'two_factor_code')) {
                $table->dropColumn('two_factor_code');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'two_factor_enabled')) {
                $table->dropColumn('two_factor_enabled');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'phone')) {
                // Сначала снимем индекс, если он существует (название индекса может отличаться).
                try {
                    $table->dropIndex(['phone']);
                } catch (\Throwable $e) {
                    // игнорируем, если индекс сгенерирован с иным именем
                }
                $table->dropColumn('phone');
            }
        });
    }
};
