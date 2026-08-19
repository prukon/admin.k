<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participants', function (Blueprint $table) {
            $table->unsignedInteger('unread_count')->default(0)->after('last_read');
            $table->index(['user_id', 'thread_id'], 'participants_user_thread_index');
            $table->index(['thread_id', 'user_id'], 'participants_thread_user_index');
        });

        Schema::table('threads', function (Blueprint $table) {
            $table->unsignedInteger('last_message_id')->nullable()->after('subject');
            $table->index('last_message_id', 'threads_last_message_id_index');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->index(['thread_id', 'id'], 'messages_thread_id_id_index');
            $table->index(['thread_id', 'created_at'], 'messages_thread_id_created_at_index');
        });

        DB::statement(
            'UPDATE threads t
             LEFT JOIN (
                 SELECT thread_id, MAX(id) AS mid
                 FROM messages
                 WHERE deleted_at IS NULL
                 GROUP BY thread_id
             ) m ON m.thread_id = t.id
             SET t.last_message_id = m.mid'
        );

        DB::statement(
            "UPDATE participants p
             SET unread_count = (
                 SELECT COUNT(*)
                 FROM messages m
                 WHERE m.thread_id = p.thread_id
                   AND m.deleted_at IS NULL
                   AND m.user_id <> p.user_id
                   AND m.created_at > COALESCE(p.last_read, '1970-01-01 00:00:00')
             )
             WHERE p.deleted_at IS NULL"
        );
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('messages_thread_id_id_index');
            $table->dropIndex('messages_thread_id_created_at_index');
        });

        Schema::table('threads', function (Blueprint $table) {
            $table->dropIndex('threads_last_message_id_index');
            $table->dropColumn('last_message_id');
        });

        Schema::table('participants', function (Blueprint $table) {
            $table->dropIndex('participants_user_thread_index');
            $table->dropIndex('participants_thread_user_index');
            $table->dropColumn('unread_count');
        });
    }
};
