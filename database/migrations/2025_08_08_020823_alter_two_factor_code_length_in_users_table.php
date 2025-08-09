<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE `users` MODIFY `two_factor_code` VARCHAR(255) NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE `users` MODIFY `two_factor_code` VARCHAR(10) NULL');
    }
};
