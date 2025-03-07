<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->string('bank_name')->nullable()->after('website');
            $table->string('bank_bik', 20)->nullable()->after('bank_name');
            $table->string('bank_account', 20)->nullable()->after('bank_bik');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->dropColumn(['bank_name', 'bank_bik', 'bank_account']);
        });
    }
};
