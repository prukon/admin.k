<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_leads', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->after('location_id');

            $table->unique('user_id', 'school_leads_user_id_unique');

            $table->foreign('user_id', 'school_leads_user_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('school_leads', function (Blueprint $table) {
            $table->dropForeign('school_leads_user_fk');
            $table->dropUnique('school_leads_user_id_unique');
            $table->dropColumn('user_id');
        });
    }
};
