<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('deal_id')->unique()->nullable()->after('payment_month');      // ID сделки в системе Тинькофф
            $table->string('payment_id')->nullable()->after('deal_id');                   // ID транзакции от Тинькофф
            $table->string('payment_status')->nullable()->after('payment_id');            // Статус транзакции (CHECKED, COMPLETED, REJECTED и т.п.)
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['deal_id', 'payment_id', 'payment_status']);
        });
    }
};
 