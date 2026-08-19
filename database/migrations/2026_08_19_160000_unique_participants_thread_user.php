<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $dupes = DB::table('participants')
            ->select('thread_id', 'user_id', DB::raw('MIN(id) as keep_id'))
            ->groupBy('thread_id', 'user_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($dupes as $dupe) {
            DB::table('participants')
                ->where('thread_id', $dupe->thread_id)
                ->where('user_id', $dupe->user_id)
                ->where('id', '<>', $dupe->keep_id)
                ->delete();
        }

        Schema::table('participants', function (Blueprint $table) {
            $table->dropIndex('participants_thread_user_index');
            $table->unique(['thread_id', 'user_id'], 'participants_thread_user_unique');
        });
    }

    public function down(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->dropUnique('participants_thread_user_unique');
            $table->index(['thread_id', 'user_id'], 'participants_thread_user_index');
        });
    }
};
