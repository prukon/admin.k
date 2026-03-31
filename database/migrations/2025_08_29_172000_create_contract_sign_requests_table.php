<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('contract_sign_requests', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');

            // ВАЖНО: тип — unsignedBigInteger
            $table->unsignedBigInteger('contract_id');

            $table->string('signer_name')->nullable();
            $table->string('signer_phone');
            $table->unsignedSmallInteger('ttl_hours')->nullable();
            $table->string('provider_request_id')->nullable();
            $table->string('status')->default('created');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['contract_id','status']);

            // Явно задаём FK (после объявления столбца)
            $table->foreign('contract_id')
                ->references('id')->on('contracts')
                ->onDelete('cascade');
        });

    }

    public function down(): void {
        Schema::dropIfExists('contract_sign_requests');
    }
};
