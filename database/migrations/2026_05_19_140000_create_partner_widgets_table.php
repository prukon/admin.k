<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_widgets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('partner_id');
            $table->string('widget_key', 64)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('partner_id')
                ->references('id')
                ->on('partners')
                ->cascadeOnDelete();

            $table->unique('partner_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_widgets');
    }
};
