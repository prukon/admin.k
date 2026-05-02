<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE user_lesson_packages MODIFY starts_at DATE NULL');
        DB::statement('ALTER TABLE user_lesson_packages MODIFY ends_at DATE NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE user_lesson_packages MODIFY starts_at DATE NOT NULL');
        DB::statement('ALTER TABLE user_lesson_packages MODIFY ends_at DATE NOT NULL');
    }
};
