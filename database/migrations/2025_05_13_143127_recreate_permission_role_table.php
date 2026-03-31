<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Удаляем старую таблицу, если есть
        Schema::dropIfExists('permission_role');

        // 2) Создаём заново с правильными колонками, FK и уникальным индексом
        Schema::create('permission_role', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('role_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('permission_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->timestamps();

            // гарантируем, что одна и та же тройка не повторится
            $table->unique(
                ['partner_id', 'role_id', 'permission_id'],
                'pr_partner_role_permission_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_role');
    }
};
