<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tinkoff_payout_status_logs', function (Blueprint $t) {
            $t->id();

            $t->unsignedBigInteger('payout_id')->index();
            $t->string('from_status', 32)->nullable();
            $t->string('to_status', 32)->nullable();
            $t->json('payload')->nullable();

            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tinkoff_payout_status_logs');
    }
};

