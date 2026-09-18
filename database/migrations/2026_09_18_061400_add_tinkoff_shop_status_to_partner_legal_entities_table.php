<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner_legal_entities', function (Blueprint $table) {
            $table->boolean('tinkoff_disable_reimbursement')->nullable()->after('sm_register_status');
            $table->timestamp('tinkoff_shop_checked_at')->nullable()->after('tinkoff_disable_reimbursement');
            $table->json('tinkoff_shop_snapshot')->nullable()->after('tinkoff_shop_checked_at');
        });
    }

    public function down(): void
    {
        Schema::table('partner_legal_entities', function (Blueprint $table) {
            $table->dropColumn([
                'tinkoff_disable_reimbursement',
                'tinkoff_shop_checked_at',
                'tinkoff_shop_snapshot',
            ]);
        });
    }
};
