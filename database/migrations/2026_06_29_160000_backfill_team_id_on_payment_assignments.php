<?php

use App\Services\Payments\PaymentAssignmentTeamBackfill;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(PaymentAssignmentTeamBackfill::class)->run();
    }

    public function down(): void
    {
        // Не откатываем: backfill не восстанавливает прежние NULL без потери данных.
    }
};
