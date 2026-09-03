<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner_legal_entities', function (Blueprint $table) {
            $table->text('podpislon_api_key')->nullable()->after('bank_corr_account');
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->unsignedBigInteger('legal_entity_id')->nullable()->after('group_id');
            $table->index('legal_entity_id');
            $table->foreign('legal_entity_id')
                ->references('id')
                ->on('partner_legal_entities')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropForeign(['legal_entity_id']);
            $table->dropIndex(['legal_entity_id']);
            $table->dropColumn('legal_entity_id');
        });

        Schema::table('partner_legal_entities', function (Blueprint $table) {
            $table->dropColumn('podpislon_api_key');
        });
    }
};
