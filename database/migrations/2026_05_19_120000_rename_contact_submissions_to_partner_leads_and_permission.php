<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('contact_submissions') && !Schema::hasTable('partner_leads')) {
            Schema::rename('contact_submissions', 'partner_leads');
        }

        if (DB::table('permissions')->where('name', 'leads.view')->exists()) {
            DB::table('permissions')
                ->where('name', 'leads.view')
                ->update([
                    'name'        => 'partnerLeads.view',
                    'description' => 'Страница "Лиды партнёров"',
                    'updated_at'  => now(),
                ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('partner_leads') && !Schema::hasTable('contact_submissions')) {
            Schema::rename('partner_leads', 'contact_submissions');
        }

        if (DB::table('permissions')->where('name', 'partnerLeads.view')->exists()) {
            DB::table('permissions')
                ->where('name', 'partnerLeads.view')
                ->update([
                    'name'        => 'leads.view',
                    'description' => 'Страница "Лиды"',
                    'updated_at'  => now(),
                ]);
        }
    }
};
