<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_lesson_packages', function (Blueprint $table) {
            $table->boolean('is_manual_paid')->nullable()->after('is_paid');
            $table->unsignedBigInteger('manual_paid_by')->nullable()->after('is_manual_paid');
            $table->timestamp('manual_paid_at')->nullable()->after('manual_paid_by');
            $table->text('manual_paid_note')->nullable()->after('manual_paid_at');
        });

        Schema::table('user_lesson_packages', function (Blueprint $table) {
            $table->foreign('manual_paid_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('user_lesson_packages', function (Blueprint $table) {
            $table->dropForeign(['manual_paid_by']);
        });

        Schema::table('user_lesson_packages', function (Blueprint $table) {
            $table->dropColumn([
                'is_manual_paid',
                'manual_paid_by',
                'manual_paid_at',
                'manual_paid_note',
            ]);
        });
    }
};
