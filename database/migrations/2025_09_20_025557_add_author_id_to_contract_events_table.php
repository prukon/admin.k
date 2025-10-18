<?php

// database/migrations/2025_09_20_000000_add_author_id_to_contract_events.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('contract_events', function (Blueprint $table) {
            $table->foreignId('author_id')
                ->nullable()
                ->after('contract_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->index('author_id');
        });
    }

    public function down(): void
    {
        Schema::table('contract_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('author_id');
        });
    }
};
