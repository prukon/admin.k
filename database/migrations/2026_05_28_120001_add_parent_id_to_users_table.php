<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_id')->nullable()->after('lastname');

            $table->index('parent_id', 'users_parent_id_idx');

            $table->foreign('parent_id', 'users_parent_fk')
                ->references('id')
                ->on('parents')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign('users_parent_fk');
            $table->dropIndex('users_parent_id_idx');
            $table->dropColumn('parent_id');
        });
    }
};
