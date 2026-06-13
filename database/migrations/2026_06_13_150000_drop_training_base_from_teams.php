<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('teams', 'training_base')) {
            Schema::table('teams', function (Blueprint $table) {
                $table->dropColumn('training_base');
            });
        }

        $permissionIds = DB::table('permissions')
            ->where('name', 'groups.training_base.view')
            ->pluck('id');

        if ($permissionIds->isNotEmpty()) {
            DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
            DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('teams', 'training_base')) {
            Schema::table('teams', function (Blueprint $table) {
                $table->string('training_base')->nullable()->after('title');
            });
        }
    }
};
