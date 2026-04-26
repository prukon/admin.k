<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('outgoing_email_logs')) {
            return;
        }

        Schema::create('outgoing_email_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('partner_id')->nullable()->index();
            $table->string('status', 32)->index();
            $table->string('queue', 191)->nullable()->index();
            $table->unsignedBigInteger('database_queue_job_id')->nullable()->index();

            $table->string('from_address', 255)->nullable();
            $table->string('from_name', 255)->nullable();
            $table->json('reply_to')->nullable();
            $table->json('to_addresses')->nullable();
            $table->string('to_summary', 1024)->nullable();
            $table->json('cc_addresses')->nullable();
            $table->json('bcc_addresses')->nullable();

            $table->string('subject', 998)->nullable();

            $table->longText('html_body')->nullable();
            $table->longText('text_body')->nullable();

            $table->string('notification_class', 255)->nullable()->index();
            $table->string('laravel_notification_id', 64)->nullable()->index();
            $table->string('notifiable_type', 255)->nullable();
            $table->unsignedBigInteger('notifiable_id')->nullable()->index();
            $table->string('mailable_class', 255)->nullable()->index();

            $table->json('attachments')->nullable();

            $table->unsignedInteger('send_attempts')->default(0);
            $table->timestamp('sent_at')->nullable()->index();
            $table->timestamp('failed_at')->nullable();
            $table->text('error_message')->nullable();

            $table->timestamps();

            $table->index(['notifiable_type', 'notifiable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outgoing_email_logs');
    }
};
