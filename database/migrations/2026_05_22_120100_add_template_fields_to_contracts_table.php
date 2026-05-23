<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->string('creation_mode', 16)->default('pdf')->after('group_id');
            $table->unsignedBigInteger('contract_template_version_id')->nullable()->after('creation_mode');
            $table->json('filled_data')->nullable()->after('contract_template_version_id');
            $table->timestamp('fill_expires_at')->nullable()->after('filled_data');

            $table->index(['creation_mode', 'status']);
            $table->index('contract_template_version_id');
        });

        // Без doctrine/dbal — явный MODIFY для MySQL.
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            \Illuminate\Support\Facades\DB::statement(
                'ALTER TABLE `contracts` MODIFY `source_pdf_path` VARCHAR(255) NULL'
            );
            \Illuminate\Support\Facades\DB::statement(
                'ALTER TABLE `contracts` MODIFY `source_sha256` VARCHAR(64) NULL'
            );
        }

        Schema::table('contracts', function (Blueprint $table) {
            $table->foreign('contract_template_version_id')
                ->references('id')
                ->on('contract_template_versions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropForeign(['contract_template_version_id']);
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->dropIndex(['creation_mode', 'status']);
            $table->dropIndex(['contract_template_version_id']);
            $table->dropColumn([
                'creation_mode',
                'contract_template_version_id',
                'filled_data',
                'fill_expires_at',
            ]);
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            \Illuminate\Support\Facades\DB::statement(
                'ALTER TABLE `contracts` MODIFY `source_pdf_path` VARCHAR(255) NOT NULL'
            );
            \Illuminate\Support\Facades\DB::statement(
                'ALTER TABLE `contracts` MODIFY `source_sha256` VARCHAR(64) NOT NULL'
            );
        }
    }
};
