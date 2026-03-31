<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MakePartnerPaymentIdNullableInPartnerAccesses extends Migration
{
    public function up()
    {
        Schema::table('partner_accesses', function (Blueprint $table) {
            // Делаем поле nullable
            $table->string('partner_payment_id', 255)->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('partner_accesses', function (Blueprint $table) {
            // Возвращаем поле обратно к not nullable
            $table->string('partner_payment_id', 255)->nullable(false)->change();
        });
    }
}
