<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('threads', function (Blueprint $table) {
            $table->boolean('is_group')->default(false)->after('subject');
        });

        $groupIds = DB::table('participants')
            ->whereNull('deleted_at')
            ->select('thread_id')
            ->groupBy('thread_id')
            ->havingRaw('COUNT(*) > 2')
            ->pluck('thread_id');

        if ($groupIds->isNotEmpty()) {
            DB::table('threads')->whereIn('id', $groupIds)->update(['is_group' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('threads', function (Blueprint $table) {
            $table->dropColumn('is_group');
        });
    }
};
