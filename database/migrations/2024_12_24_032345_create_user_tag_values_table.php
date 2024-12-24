<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('user_tag_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('tag_id')->constrained('tags')->onDelete('cascade');
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('user_tag_values');
    }
};
