<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePartnersTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('partners', function (Blueprint $table) {
            $table->id(); // id (PK)
            $table->enum('business_type', ['company', 'individual_entrepreneur']);
            $table->string('title');
            $table->string('tax_id')->unique()->nullable();
            $table->string('registration_number')->unique()->nullable();
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->unique();
            $table->string('website')->nullable();
            $table->timestamps(); // created_at и updated_at

            $table->softDeletes(); // Добавляет поле deleted_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('partners');
    }
};
