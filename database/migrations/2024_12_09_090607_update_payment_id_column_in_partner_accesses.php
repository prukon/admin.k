<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdatePaymentIdColumnInPartnerAccesses extends Migration
{
    public function up()
    {
        Schema::table('partner_accesses', function (Blueprint $table) {
            // Удаляем внешний ключ, если он существует
            $table->dropForeign(['payment_id']);

            // Переименовываем столбец
            $table->renameColumn('payment_id', 'partner_payment_id');

            // Добавляем индекс, если необходимо
            $table->index('partner_payment_id');
        });
    }

    public function down()
    {
        Schema::table('partner_accesses', function (Blueprint $table) {
            // Удаляем индекс
            $table->dropIndex(['partner_payment_id']);

            // Возвращаем старое имя столбца
            $table->renameColumn('partner_payment_id', 'payment_id');

            // Восстанавливаем внешний ключ, если необходимо
            $table->foreign('payment_id')
                ->references('payment_id')
                ->on('partner_payments')
                ->onDelete('cascade');
        });
    }
}
