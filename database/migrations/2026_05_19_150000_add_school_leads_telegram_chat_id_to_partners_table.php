<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            if (!Schema::hasColumn('partners', 'school_leads_telegram_chat_id')) {
                $table->string('school_leads_telegram_chat_id', 32)->nullable()->after('email');
            }
        });
    }

    public function down(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            if (Schema::hasColumn('partners', 'school_leads_telegram_chat_id')) {
                $table->dropColumn('school_leads_telegram_chat_id');
            }
        });
    }
};
