<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_reactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('message_id');
            $table->unsignedBigInteger('user_id');
            $table->string('emoji', 32);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['message_id', 'user_id'], 'message_reactions_message_user_unique');
            $table->index(['message_id', 'emoji'], 'message_reactions_message_emoji_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_reactions');
    }
};
