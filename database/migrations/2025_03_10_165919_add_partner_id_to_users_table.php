<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // Добавляем новое поле partner_id (можно nullable, если не у каждого пользователя есть партнёр)
            $table->unsignedBigInteger('partner_id')->nullable()->after('id');

            // Если в БД есть таблица "partners", можно раскомментировать внешний ключ:
             $table->foreign('partner_id')->references('id')->on('partners')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            // Удаляем поле при откате
            // Если вы создавали FK, нужно сначала dropForeign():
            $table->dropForeign(['partner_id']);
            $table->dropColumn('partner_id');
        });
    }
};
