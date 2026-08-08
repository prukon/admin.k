<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_lesson_packages', function (Blueprint $table) {
            $table->boolean('auto_prolong_enabled')
                ->default(false)
                ->after('created_by');

            $table->unsignedBigInteger('auto_prolonged_from_id')
                ->nullable()
                ->after('auto_prolong_enabled');

            $table->unsignedBigInteger('auto_prolonged_to_id')
                ->nullable()
                ->after('auto_prolonged_from_id');

            $table->index('auto_prolong_enabled', 'ulp_auto_prolong_enabled_idx');
            $table->index('auto_prolonged_from_id', 'ulp_auto_prolonged_from_idx');
            $table->index('auto_prolonged_to_id', 'ulp_auto_prolonged_to_idx');
        });
    }

    public function down(): void
    {
        Schema::table('user_lesson_packages', function (Blueprint $table) {
            $table->dropIndex('ulp_auto_prolong_enabled_idx');
            $table->dropIndex('ulp_auto_prolonged_from_idx');
            $table->dropIndex('ulp_auto_prolonged_to_idx');
            $table->dropColumn([
                'auto_prolong_enabled',
                'auto_prolonged_from_id',
                'auto_prolonged_to_id',
            ]);
        });
    }
};
