<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('offer_accepted')->default(false)->after('remember_token');
            $table->timestamp('offer_accepted_at')->nullable()->after('offer_accepted');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['offer_accepted', 'offer_accepted_at']);
        });
    }
};
