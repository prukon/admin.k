<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('tinkoff_commission_rules', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('partner_id')->nullable()->index(); // NULL = глобально
            $t->enum('method', ['card','sbp','tpay'])->nullable(); // NULL = для всех методов
            $t->decimal('percent', 5, 2);
            $t->decimal('min_fixed', 10, 2)->default(0);
            $t->boolean('is_enabled')->default(true);
            $t->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('tinkoff_commission_rules');
    }
};
