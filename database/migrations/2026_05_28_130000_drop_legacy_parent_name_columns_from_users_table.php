<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'parent_lastname',
                'parent_firstname',
                'parent_middlename',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('parent_lastname', 100)->nullable()->after('lastname');
            $table->string('parent_firstname', 100)->nullable()->after('parent_lastname');
            $table->string('parent_middlename', 100)->nullable()->after('parent_firstname');
        });
    }
};
