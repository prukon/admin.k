<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('in_app_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('category', 32);
            $table->string('source', 32)->default('manual');
            $table->string('title', 160);
            $table->text('body');
            $table->string('action_url', 2048)->nullable();
            $table->boolean('is_global')->default(false);
            $table->json('audience_role_ids')->nullable();
            $table->string('ttl_preset', 32);
            $table->timestamp('expires_at')->nullable();
            $table->string('status', 32)->default('pending');
            $table->unsignedInteger('recipients_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('error_message', 2000)->nullable();
            $table->timestamps();

            $table->index(['status', 'expires_at']);
            $table->index(['is_global', 'created_at']);
            $table->index('created_by');
        });

        Schema::create('in_app_notification_partners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('in_app_notification_id')
                ->constrained('in_app_notifications')
                ->cascadeOnDelete();
            $table->foreignId('partner_id')->constrained('partners')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(
                ['in_app_notification_id', 'partner_id'],
                'in_app_notif_partner_unique'
            );
            $table->index('partner_id');
        });

        Schema::create('in_app_notification_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('in_app_notification_id')
                ->constrained('in_app_notifications')
                ->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(
                ['in_app_notification_id', 'user_id'],
                'in_app_notif_recipient_unique'
            );
            $table->index('user_id');
        });

        Schema::create('in_app_notification_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('in_app_notification_id')
                ->constrained('in_app_notifications')
                ->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('read_at');
            $table->timestamps();

            $table->unique(
                ['in_app_notification_id', 'user_id'],
                'in_app_notif_read_unique'
            );
            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('in_app_notification_reads');
        Schema::dropIfExists('in_app_notification_recipients');
        Schema::dropIfExists('in_app_notification_partners');
        Schema::dropIfExists('in_app_notifications');
    }
};
