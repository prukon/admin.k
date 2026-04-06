<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            if (!Schema::hasColumn('partners', 'registration_verified_at')) {
                $table->timestamp('registration_verified_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            if (Schema::hasColumn('partners', 'registration_verified_at')) {
                $table->dropColumn('registration_verified_at');
            }
        });
    }
};
