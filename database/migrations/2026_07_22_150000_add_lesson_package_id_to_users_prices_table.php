<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users_prices', 'lesson_package_id')) {
            Schema::table('users_prices', function (Blueprint $table) {
                $table->unsignedBigInteger('lesson_package_id')->nullable()->after('price');
                $table->foreign('lesson_package_id', 'users_prices_lesson_package_id_fk')
                    ->references('id')
                    ->on('lesson_packages')
                    ->nullOnDelete();
                $table->index('lesson_package_id', 'users_prices_lesson_package_id_idx');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users_prices', 'lesson_package_id')) {
            return;
        }

        Schema::table('users_prices', function (Blueprint $table) {
            $table->dropForeign('users_prices_lesson_package_id_fk');
            $table->dropIndex('users_prices_lesson_package_id_idx');
            $table->dropColumn('lesson_package_id');
        });
    }
};
