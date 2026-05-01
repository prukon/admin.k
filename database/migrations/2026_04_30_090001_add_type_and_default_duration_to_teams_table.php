<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->string('type', 20)->default('group')->after('title');
            $table->unsignedSmallInteger('default_duration_minutes')->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn(['type', 'default_duration_minutes']);
        });
    }
};

