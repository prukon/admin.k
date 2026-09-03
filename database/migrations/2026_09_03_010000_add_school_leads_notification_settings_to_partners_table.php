<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            if (!Schema::hasColumn('partners', 'school_leads_notification_emails')) {
                $table->json('school_leads_notification_emails')->nullable()->after('school_leads_telegram_chat_id');
            }
            if (!Schema::hasColumn('partners', 'school_leads_email_notifications_disabled')) {
                $table->boolean('school_leads_email_notifications_disabled')->default(false)->after('school_leads_notification_emails');
            }
        });
    }

    public function down(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            if (Schema::hasColumn('partners', 'school_leads_email_notifications_disabled')) {
                $table->dropColumn('school_leads_email_notifications_disabled');
            }
            if (Schema::hasColumn('partners', 'school_leads_notification_emails')) {
                $table->dropColumn('school_leads_notification_emails');
            }
        });
    }
};
