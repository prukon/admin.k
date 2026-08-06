<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users_prices', 'user_lesson_package_id')) {
            Schema::table('users_prices', function (Blueprint $table) {
                $table->unsignedBigInteger('user_lesson_package_id')->nullable()->after('lesson_package_id');
                $table->foreign('user_lesson_package_id', 'users_prices_user_lesson_package_id_fk')
                    ->references('id')
                    ->on('user_lesson_packages')
                    ->nullOnDelete();
                $table->index('user_lesson_package_id', 'users_prices_user_lesson_package_id_idx');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users_prices', 'user_lesson_package_id')) {
            return;
        }

        Schema::table('users_prices', function (Blueprint $table) {
            $table->dropForeign('users_prices_user_lesson_package_id_fk');
            $table->dropIndex('users_prices_user_lesson_package_id_idx');
            $table->dropColumn('user_lesson_package_id');
        });
    }
};
