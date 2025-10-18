<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            // Оригинальный файл (не-PDF)
            $table->string('source_file_path')->nullable()->after('school_id');
            $table->string('source_mime')->nullable()->after('source_file_path');
            $table->string('source_ext', 16)->nullable()->after('source_mime');
            $table->unsignedBigInteger('source_size')->nullable()->after('source_ext');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn(['source_file_path', 'source_mime', 'source_ext', 'source_size']);
        });
    }
};
