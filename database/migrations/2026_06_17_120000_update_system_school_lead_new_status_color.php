<?php

use App\Models\SchoolLeadStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('school_lead_statuses')
            ->whereNull('partner_id')
            ->where('is_system', true)
            ->where('code', SchoolLeadStatus::CODE_NEW)
            ->update([
                'color'      => SchoolLeadStatus::DEFAULT_NEW_COLOR,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('school_lead_statuses')
            ->whereNull('partner_id')
            ->where('is_system', true)
            ->where('code', SchoolLeadStatus::CODE_NEW)
            ->update([
                'color'      => '#6c757d',
                'updated_at' => now(),
            ]);
    }
};
