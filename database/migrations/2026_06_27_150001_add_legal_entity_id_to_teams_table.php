<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('teams')) {
            return;
        }

        Schema::table('teams', function (Blueprint $table) {
            if (! Schema::hasColumn('teams', 'legal_entity_id')) {
                $table->unsignedBigInteger('legal_entity_id')
                    ->nullable()
                    ->after('partner_id');

                $table->index('legal_entity_id', 'teams_legal_entity_idx');

                $table->foreign('legal_entity_id', 'teams_legal_entity_fk')
                    ->references('id')
                    ->on('partner_legal_entities')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('teams')) {
            return;
        }

        Schema::table('teams', function (Blueprint $table) {
            if (Schema::hasColumn('teams', 'legal_entity_id')) {
                $table->dropForeign('teams_legal_entity_fk');
                $table->dropIndex('teams_legal_entity_idx');
                $table->dropColumn('legal_entity_id');
            }
        });
    }
};
