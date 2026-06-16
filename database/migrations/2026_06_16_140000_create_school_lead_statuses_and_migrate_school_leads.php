<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_lead_statuses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('partner_id')->nullable();
            $table->string('code', 32)->nullable();
            $table->string('name');
            $table->string('color', 32)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_default_in_filter')->default(false);
            $table->boolean('is_system')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('partner_id')
                ->references('id')
                ->on('partners')
                ->cascadeOnDelete();

            $table->index(['partner_id', 'sort_order']);
            $table->unique(['code', 'partner_id']);
        });

        $now = now();

        DB::table('school_lead_statuses')->insert([
            'partner_id'            => null,
            'code'                  => 'new',
            'name'                  => 'Новый',
            'color'                 => '#6c757d',
            'sort_order'            => 0,
            'is_default_in_filter'  => true,
            'is_system'             => true,
            'created_at'            => $now,
            'updated_at'            => $now,
        ]);

        Schema::table('school_leads', function (Blueprint $table) {
            $table->unsignedBigInteger('school_lead_status_id')
                ->nullable()
                ->after('phone');

            $table->foreign('school_lead_status_id')
                ->references('id')
                ->on('school_lead_statuses')
                ->nullOnDelete();

            $table->index(['partner_id', 'school_lead_status_id']);
        });

        Schema::table('school_leads', function (Blueprint $table) {
            $table->dropIndex(['partner_id', 'status']);
            $table->dropColumn('status');
        });
    }

    public function down(): void
    {
        Schema::table('school_leads', function (Blueprint $table) {
            $table->string('status', 32)->default('new')->after('phone');
            $table->index(['partner_id', 'status']);
        });

        Schema::table('school_leads', function (Blueprint $table) {
            $table->dropForeign(['school_lead_status_id']);
            $table->dropIndex(['partner_id', 'school_lead_status_id']);
            $table->dropColumn('school_lead_status_id');
        });

        Schema::dropIfExists('school_lead_statuses');
    }
};
