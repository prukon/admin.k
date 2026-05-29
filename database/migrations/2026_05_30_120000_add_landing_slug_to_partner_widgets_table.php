<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner_widgets', function (Blueprint $table) {
            $table->string('landing_slug', 40)->nullable()->unique()->after('landing_key');
        });
    }

    public function down(): void
    {
        Schema::table('partner_widgets', function (Blueprint $table) {
            $table->dropColumn('landing_slug');
        });
    }
};
