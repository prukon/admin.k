<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('contracts', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('school_id');
            $table->unsignedBigInteger('user_id'); // ученик
            $table->unsignedBigInteger('group_id')->nullable();
            $table->string('source_pdf_path');
            $table->string('source_sha256', 64);
            $table->string('provider')->default('podpislon');
            $table->string('provider_doc_id')->nullable();
            $table->string('status')->default('draft');
            $table->string('signed_pdf_path')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamps();

            $table->index(['school_id','user_id','group_id']);
            $table->index(['status','provider']);
        });    }

    public function down(): void {
        Schema::dropIfExists('contracts');
    }
};
