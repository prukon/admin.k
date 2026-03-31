<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('statuses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('partner_id')->nullable();
            $table->string('name');      // "не был", "учебный день", "оплачено", "заморозка" и т.д.
            $table->string('icon')->nullable();  // например, "fas fa-snowflake"
            $table->string('color')->nullable(); // например, "#ff0000" или иной формат
            $table->boolean('is_system')->default(false); // системный (true) или пользовательский (false)

            // Встроенный столбец для "мягкого" удаления
            $table->softDeletes();  // создаст поле "deleted_at"

            $table->timestamps();

            // Если есть таблица partners, можно раскомментировать внешний ключ:
            // $table->foreign('partner_id')->references('id')->on('partners')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('statuses');
    }
};
