<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            if (!Schema::hasColumn('partners', 'sms_name')) {
                // billingDescriptor хранится в partners.sms_name (до 14 символов по требованиям банка)
                $table->string('sms_name', 14)->nullable()->after('website');
            }
        });
    }

    public function down(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            if (Schema::hasColumn('partners', 'sms_name')) {
                $table->dropColumn('sms_name');
            }
        });
    }
};
