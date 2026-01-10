<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('partner_id')->index();
            $table->unsignedBigInteger('user_id')->index();

            $table->unsignedBigInteger('payable_id')->index();
            $table->unsignedBigInteger('payment_id')->index();

            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('RUB');

            // pending | succeeded | failed
            $table->string('status', 30)->default('pending')->index();

            $table->string('provider', 50)->nullable()->index();
            $table->string('provider_refund_id', 255)->nullable()->index();

            $table->timestamp('processed_at')->nullable()->index();

            $table->text('meta')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};


