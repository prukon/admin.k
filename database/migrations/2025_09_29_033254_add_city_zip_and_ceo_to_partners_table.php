<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ВНИМАНИЕ: порядок колонок «перед address» потребует doctrine/dbal для ->change()
        Schema::table('partners', function (Blueprint $table) {
            if (!Schema::hasColumn('partners', 'city')) {
                // Ставим перед address: сначала добавим city...
                $table->string('city', 100)->nullable()->after('email');
            }
            if (!Schema::hasColumn('partners', 'zip')) {
                // ... затем zip сразу после city
                $table->string('zip', 20)->nullable()->after('city');
            }
            if (!Schema::hasColumn('partners', 'ceo')) {
                // JSON: {firstName, lastName, middleName, phone}
                $table->json('ceo')->nullable()->after('zip');
            }
        });

        // Необязательно: если нужен строгий порядок «address» после zip,
        // потребуется doctrine/dbal для change():
        // composer require doctrine/dbal
        // Schema::table('partners', function (Blueprint $table) {
        //     if (Schema::hasColumn('partners', 'address')) {
        //         $table->string('address', 255)->nullable()->after('zip')->change();
        //     }
        // });
    }

    public function down(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            if (Schema::hasColumn('partners', 'ceo')) {
                $table->dropColumn('ceo');
            }
            if (Schema::hasColumn('partners', 'zip')) {
                $table->dropColumn('zip');
            }
            if (Schema::hasColumn('partners', 'city')) {
                $table->dropColumn('city');
            }
        });

        // Позицию address назад не возвращаем (не критично для работы приложения).
    }
};
