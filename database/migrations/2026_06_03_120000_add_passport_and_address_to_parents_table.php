<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parents', function (Blueprint $table) {
            $table->string('passport', 100)->nullable()->after('middlename');
            $table->string('passport_issued', 500)->nullable()->after('passport');
            $table->string('address', 1000)->nullable()->after('passport_issued');
        });
    }

    public function down(): void
    {
        Schema::table('parents', function (Blueprint $table) {
            $table->dropColumn(['passport', 'passport_issued', 'address']);
        });
    }
};
