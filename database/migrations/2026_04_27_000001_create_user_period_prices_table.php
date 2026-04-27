<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_period_prices', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('partner_id')->index();
            $table->unsignedBigInteger('user_id')->index();

            $table->date('date_start')->index();
            $table->date('date_end')->index();

            $table->decimal('amount', 10, 2);
            $table->string('note')->nullable();

            $table->boolean('is_paid')->default(false);

            // manual override: если не null — важнее auto is_paid
            $table->boolean('is_manual_paid')->nullable();
            $table->unsignedBigInteger('manual_paid_by')->nullable();
            $table->dateTime('manual_paid_at')->nullable();
            $table->string('manual_paid_note')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'date_start', 'date_end'], 'uniq_user_period_prices_user_period');
            $table->index(['partner_id', 'user_id', 'date_start'], 'idx_user_period_prices_partner_user_start');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_period_prices');
    }
};

