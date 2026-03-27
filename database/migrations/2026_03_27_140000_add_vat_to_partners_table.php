<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ставка НДС для фискальных чеков (коды CloudKassir /kkt/receipt, поле Items[].Vat).
     * null — НДС не облагается (в API передаётся null).
     */
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->unsignedSmallInteger('vat')
                ->nullable()
                ->after('taxation_system');
        });
    }

    public function down(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->dropColumn('vat');
        });
    }
};
