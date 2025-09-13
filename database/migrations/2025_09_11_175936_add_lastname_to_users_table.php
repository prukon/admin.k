<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // если у тебя уже есть поле last_name с другим именем — скорректируй
            $table->string('lastname', 191)
                ->nullable()
                ->after('name'); // положим сразу после name
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('lastname');
        });
    }
};

