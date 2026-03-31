<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('payment_systems', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('partner_id')->index();
            $table->string('name', 255)->index();
            // Если у вас MySQL 5.7+ — рекомендуется использовать JSON:
//            $table->json('settings')->nullable();
            // Если нет поддержки JSON-типов, можно сделать:
             $table->text('settings')->nullable();
            $table->boolean('test_mode')->default(false);

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('payment_systems');
    }
};

