<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_leads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('partner_id');
            $table->unsignedBigInteger('partner_widget_id')->nullable();
            $table->string('name');
            $table->string('phone', 50);
            $table->string('status', 32)->default('new');
            $table->text('comment')->nullable();
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('utm_content')->nullable();
            $table->string('utm_term')->nullable();
            $table->string('page_url', 2048)->nullable();
            $table->string('referrer', 2048)->nullable();
            $table->timestamp('consent_accepted_at')->nullable();
            $table->string('policy_url', 2048)->nullable();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('partner_id')
                ->references('id')
                ->on('partners')
                ->cascadeOnDelete();

            $table->foreign('partner_widget_id')
                ->references('id')
                ->on('partner_widgets')
                ->nullOnDelete();

            $table->index(['partner_id', 'status']);
            $table->index(['partner_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_leads');
    }
};
