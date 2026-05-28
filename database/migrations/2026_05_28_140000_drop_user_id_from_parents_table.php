<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('parents', 'user_id')) {
            return;
        }

        Schema::table('parents', function (Blueprint $table) {
            $table->dropForeign('parents_user_fk');
            $table->dropUnique('parents_user_id_unique');
            $table->dropColumn('user_id');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('parents', 'user_id')) {
            return;
        }

        Schema::table('parents', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->after('partner_id');
            $table->unique('user_id', 'parents_user_id_unique');
            $table->foreign('user_id', 'parents_user_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }
};
