<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('two_factor_phone_pending', 20)->nullable()->after('phone');
            $table->string('two_factor_phone_change_code', 255)->nullable()->after('two_factor_phone_pending');
            $table->timestamp('two_factor_phone_change_expires_at')->nullable()->after('two_factor_phone_change_code');
            $table->timestamp('two_factor_phone_changed_at')->nullable()->after('two_factor_phone_change_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'two_factor_phone_pending',
                'two_factor_phone_change_code',
                'two_factor_phone_change_expires_at',
                'two_factor_phone_changed_at',
            ]);
        });
    }
};
