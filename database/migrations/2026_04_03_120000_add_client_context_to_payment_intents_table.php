<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_intents', function (Blueprint $table) {
            $table->text('client_user_agent')->nullable()->after('meta');
            $table->string('client_device_type', 20)->nullable()->index()->after('client_user_agent');
            $table->string('client_os_family', 64)->nullable()->index()->after('client_device_type');
            $table->string('client_os_version', 32)->nullable()->after('client_os_family');
            $table->string('client_browser_family', 64)->nullable()->after('client_os_version');
            $table->string('client_browser_version', 32)->nullable()->after('client_browser_family');
            $table->string('client_ip', 45)->nullable()->after('client_browser_version');
            $table->text('client_referrer')->nullable()->after('client_ip');
        });
    }

    public function down(): void
    {
        Schema::table('payment_intents', function (Blueprint $table) {
            $table->dropColumn([
                'client_user_agent',
                'client_device_type',
                'client_os_family',
                'client_os_version',
                'client_browser_family',
                'client_browser_version',
                'client_ip',
                'client_referrer',
            ]);
        });
    }
};
