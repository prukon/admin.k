<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Раньше использовался $table->enum(...)->change(), из‑за чего Laravel подключал
     * Doctrine DBAL к introspection схемы; тип MySQL ENUM в DBAL по умолчанию не зарегистрирован
     * → «Unknown column type enum». Сырой ALTER обходит DBAL.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            "ALTER TABLE partners MODIFY business_type ENUM("
            . "'company',"
            . "'individual_entrepreneur',"
            . "'physical_person',"
            . "'non_commercial_organization'"
            . ") NOT NULL"
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            "ALTER TABLE partners MODIFY business_type ENUM("
            . "'company',"
            . "'individual_entrepreneur'"
            . ") NOT NULL"
        );
    }
};
