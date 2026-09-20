<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->unsignedBigInteger('lesson_package_id')->nullable()->after('group_id');
            $table->json('package_snapshot')->nullable()->after('lesson_package_id');

            $table->foreign('lesson_package_id')
                ->references('id')
                ->on('lesson_packages')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropForeign(['lesson_package_id']);
            $table->dropColumn(['lesson_package_id', 'package_snapshot']);
        });
    }
};
