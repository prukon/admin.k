<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('partners', 'taxation_system')) {
            Schema::table('partners', function (Blueprint $table) {
                $table->dropColumn('taxation_system');
            });
        }

        if (Schema::hasColumn('partner_legal_entities', 'taxation_system')) {
            Schema::table('partner_legal_entities', function (Blueprint $table) {
                $table->dropColumn('taxation_system');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('partners', 'taxation_system')) {
            Schema::table('partners', function (Blueprint $table) {
                $table->unsignedTinyInteger('taxation_system')->nullable()->after('tax_id');
            });
        }

        if (! Schema::hasColumn('partner_legal_entities', 'taxation_system')) {
            Schema::table('partner_legal_entities', function (Blueprint $table) {
                $table->unsignedTinyInteger('taxation_system')->nullable()->after('sm_details_template');
            });
        }
    }
};
