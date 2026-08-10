<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner_legal_entities', function (Blueprint $table) {
            $table->string('bank_corr_account', 20)->nullable()->after('bank_account');
        });
    }

    public function down(): void
    {
        Schema::table('partner_legal_entities', function (Blueprint $table) {
            $table->dropColumn('bank_corr_account');
        });
    }
};
