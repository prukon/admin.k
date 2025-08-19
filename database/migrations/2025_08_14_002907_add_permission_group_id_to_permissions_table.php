<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            // добавляем колонку после description (как ты хотел)
            $table->foreignId('permission_group_id')
                ->nullable()
                ->after('description')
                ->constrained('permission_groups')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->index(['permission_group_id', 'sort_order'], 'perm_group_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropIndex('perm_group_sort_idx');
            $table->dropConstrainedForeignId('permission_group_id');
        });
    }
};
