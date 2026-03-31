<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('contract_events', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');

            $table->unsignedBigInteger('contract_id');

            $table->string('type');
            $table->longText('payload_json')->nullable();
            $table->timestamps();

            $table->index(['contract_id','type']);

            $table->foreign('contract_id')
                ->references('id')->on('contracts')
                ->onDelete('cascade');
        });

    }

    public function down(): void {
        Schema::dropIfExists('contract_events');
    }
};
