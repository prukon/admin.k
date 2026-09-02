<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_price_public_pay_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('users_price_id');
            $table->unsignedBigInteger('partner_id')->index();
            $table->string('token', 80);
            $table->string('short_code', 12)->nullable()->charset('ascii')->collation('ascii_bin');
            $table->string('tinkoff_payment_id', 64)->nullable()->index();
            $table->unsignedBigInteger('payment_intent_id')->nullable()->index();
            $table->unsignedBigInteger('payable_id')->nullable()->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();

            $table->unique('users_price_id', 'up_pub_pay_up_uq');
            $table->unique('token', 'up_pub_pay_token_uq');
            $table->unique('short_code', 'up_pub_pay_short_uq');

            $table->foreign('users_price_id', 'up_public_pay_links_up_fk')
                ->references('id')
                ->on('users_prices')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_price_public_pay_links');
    }
};
