<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE user_custom_payment MODIFY date_start DATE NULL');
        DB::statement('ALTER TABLE user_custom_payment MODIFY date_end DATE NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE user_custom_payment MODIFY date_start DATE NOT NULL');
        DB::statement('ALTER TABLE user_custom_payment MODIFY date_end DATE NOT NULL');
    }
};
