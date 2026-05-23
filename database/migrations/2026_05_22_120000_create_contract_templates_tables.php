<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_templates', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('partner_id');
            $table->string('title');
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->boolean('is_archived')->default(false);
            $table->timestamps();

            $table->index(['partner_id', 'is_archived']);
        });

        Schema::create('contract_template_versions', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->unsignedBigInteger('contract_template_id');
            $table->unsignedInteger('version');
            $table->string('docx_path');
            $table->string('docx_sha256', 64);
            $table->json('fields_schema');
            $table->string('email_subject')->nullable();
            $table->text('email_body_html')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['contract_template_id', 'version']);
            $table->index('contract_template_id');
        });

        Schema::table('contract_templates', function (Blueprint $table) {
            $table->foreign('current_version_id')
                ->references('id')
                ->on('contract_template_versions')
                ->nullOnDelete();
        });

        Schema::table('contract_template_versions', function (Blueprint $table) {
            $table->foreign('contract_template_id')
                ->references('id')
                ->on('contract_templates')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('contract_templates', function (Blueprint $table) {
            $table->dropForeign(['current_version_id']);
        });

        Schema::table('contract_template_versions', function (Blueprint $table) {
            $table->dropForeign(['contract_template_id']);
        });

        Schema::dropIfExists('contract_template_versions');
        Schema::dropIfExists('contract_templates');
    }
};
