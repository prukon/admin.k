<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queue_smoke_test_runs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('run_id', 64)->unique();
            $table->string('scenario', 32)->default('success');
            // pending | processing | retrying | succeeded | failed
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('started_at')->nullable()->index();
            $table->timestamp('finished_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_smoke_test_runs');
    }
};

