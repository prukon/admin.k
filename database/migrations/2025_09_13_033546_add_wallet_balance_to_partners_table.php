<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('partners', function (Blueprint $table) {
            $table->decimal('wallet_balance', 12, 2)
                ->default(0.00)
                ->after('email');
            $table->index('wallet_balance');
        });
    }

    public function down(): void {
        Schema::table('partners', function (Blueprint $table) {
            $table->dropIndex(['wallet_balance']);
            $table->dropColumn('wallet_balance');
        });
    }
};
