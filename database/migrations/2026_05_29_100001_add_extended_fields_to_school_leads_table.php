<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_leads', function (Blueprint $table) {
            $table->string('source', 16)->default('widget')->after('partner_widget_id');

            $table->string('parent_lastname', 100)->nullable()->after('phone');
            $table->string('parent_firstname', 100)->nullable()->after('parent_lastname');
            $table->string('parent_middlename', 100)->nullable()->after('parent_firstname');
            $table->string('parent_phone', 50)->nullable()->after('parent_middlename');
            $table->string('parent_email', 255)->nullable()->after('parent_phone');

            $table->string('child_lastname', 100)->nullable()->after('parent_email');
            $table->string('child_firstname', 100)->nullable()->after('child_lastname');
            $table->string('child_middlename', 100)->nullable()->after('child_firstname');
            $table->date('child_birthday')->nullable()->after('child_middlename');

            $table->boolean('is_individual_traits')->default(false)->after('child_birthday');
            $table->boolean('is_on_medical_register')->default(false)->after('is_individual_traits');
            $table->boolean('is_with_disability')->default(false)->after('is_on_medical_register');

            $table->unsignedBigInteger('team_id')->nullable()->after('location_id');
            $table->boolean('needs_contact_help')->default(false)->after('team_id');

            $table->index(['partner_id', 'source'], 'school_leads_partner_source_idx');

            $table->foreign('team_id', 'school_leads_team_fk')
                ->references('id')
                ->on('teams')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('school_leads', function (Blueprint $table) {
            $table->dropForeign('school_leads_team_fk');
            $table->dropIndex('school_leads_partner_source_idx');
            $table->dropColumn([
                'source',
                'parent_lastname',
                'parent_firstname',
                'parent_middlename',
                'parent_phone',
                'parent_email',
                'child_lastname',
                'child_firstname',
                'child_middlename',
                'child_birthday',
                'is_individual_traits',
                'is_on_medical_register',
                'is_with_disability',
                'team_id',
                'needs_contact_help',
            ]);
        });
    }
};
