<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_lesson_package_public_pay_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_lesson_package_id');
            $table->unsignedBigInteger('partner_id')->index();
            $table->string('token', 80);
            $table->string('tinkoff_payment_id', 64)->nullable()->index();
            $table->unsignedBigInteger('payment_intent_id')->nullable()->index();
            $table->unsignedBigInteger('payable_id')->nullable()->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();

            $table->unique('user_lesson_package_id', 'ulp_pub_pay_ulp_uq');
            $table->unique('token', 'ulp_pub_pay_token_uq');

            $table->foreign('user_lesson_package_id', 'ulp_public_pay_links_ulp_fk')
                ->references('id')
                ->on('user_lesson_packages')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_lesson_package_public_pay_links');
    }
};
