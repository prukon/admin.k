<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const UNIQUE_NAME = 'pr_partner_role_permission_unique';

    public function up(): void
    {
        if (!Schema::hasTable('permission_role')) {
            return;
        }

        $driver = DB::getDriverName();
        if (!in_array($driver, ['mysql', 'mariadb'], true)) {
            // Проект исторически использует MySQL/MariaDB; для других драйверов не трогаем схему.
            return;
        }

        if ($this->hasUniqueTripletIndexMySql()) {
            return;
        }

        // Если исторически были дубли — удалим их перед добавлением UNIQUE.
        $this->dedupeTripletsMySql();

        Schema::table('permission_role', function (Blueprint $table) {
            $table->unique(['partner_id', 'role_id', 'permission_id'], self::UNIQUE_NAME);
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('permission_role')) {
            return;
        }

        $driver = DB::getDriverName();
        if (!in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        if (!$this->indexNameExistsMySql(self::UNIQUE_NAME)) {
            return;
        }

        Schema::table('permission_role', function (Blueprint $table) {
            $table->dropUnique(self::UNIQUE_NAME);
        });
    }

    private function hasUniqueTripletIndexMySql(): bool
    {
        $dbName = DB::connection()->getDatabaseName();

        $rows = DB::select(
            "SELECT INDEX_NAME, NON_UNIQUE, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols
             FROM information_schema.statistics
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME = 'permission_role'
             GROUP BY INDEX_NAME, NON_UNIQUE",
            [$dbName]
        );

        foreach ($rows as $row) {
            $nonUnique = (int) ($row->NON_UNIQUE ?? 1);
            $cols = (string) ($row->cols ?? '');

            if ($nonUnique === 0 && $cols === 'partner_id,role_id,permission_id') {
                return true;
            }
        }

        return false;
    }

    private function indexNameExistsMySql(string $indexName): bool
    {
        $dbName = DB::connection()->getDatabaseName();

        $row = DB::selectOne(
            "SELECT 1
             FROM information_schema.statistics
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME = 'permission_role'
               AND INDEX_NAME = ?
             LIMIT 1",
            [$dbName, $indexName]
        );

        return $row !== null;
    }

    private function dedupeTripletsMySql(): void
    {
        DB::statement(
            "DELETE pr1
             FROM permission_role pr1
             INNER JOIN permission_role pr2
               ON pr1.partner_id = pr2.partner_id
              AND pr1.role_id = pr2.role_id
              AND pr1.permission_id = pr2.permission_id
              AND pr1.id > pr2.id"
        );
    }
};

