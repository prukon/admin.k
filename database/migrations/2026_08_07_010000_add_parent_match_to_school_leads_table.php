<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_leads', function (Blueprint $table) {
            $table->foreignId('parent_id')
                ->nullable()
                ->after('user_id')
                ->constrained('parents')
                ->nullOnDelete();

            $table->string('parent_match_reason', 20)
                ->nullable()
                ->after('parent_id');

            $table->unsignedInteger('parent_match_count')
                ->nullable()
                ->after('parent_match_reason');

            $table->string('parent_match_confirmed', 20)
                ->nullable()
                ->after('parent_match_count');
        });
    }

    public function down(): void
    {
        Schema::table('school_leads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn([
                'parent_match_reason',
                'parent_match_count',
                'parent_match_confirmed',
            ]);
        });
    }
};
