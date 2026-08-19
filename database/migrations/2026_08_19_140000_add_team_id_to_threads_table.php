<?php

use App\Services\Chat\TeamGroupChatService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('threads', function (Blueprint $table) {
            $table->unsignedBigInteger('team_id')->nullable()->unique()->after('is_group');
            $table->foreign('team_id')->references('id')->on('teams')->nullOnDelete();
        });

        app(TeamGroupChatService::class)->backfillMissing();
    }

    public function down(): void
    {
        Schema::table('threads', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
            $table->dropUnique(['team_id']);
            $table->dropColumn('team_id');
        });
    }
};
