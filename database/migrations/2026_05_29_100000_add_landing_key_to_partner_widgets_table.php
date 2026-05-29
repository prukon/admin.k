<?php

use App\Models\PartnerWidget;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner_widgets', function (Blueprint $table) {
            $table->string('landing_key', 64)->nullable()->unique()->after('widget_key');
            $table->boolean('is_landing_active')->default(true)->after('is_active');
        });

        PartnerWidget::query()
            ->whereNull('landing_key')
            ->each(function (PartnerWidget $widget): void {
                do {
                    $key = Str::random(48);
                } while (PartnerWidget::query()->where('landing_key', $key)->exists());

                $widget->update(['landing_key' => $key]);
            });
    }

    public function down(): void
    {
        Schema::table('partner_widgets', function (Blueprint $table) {
            $table->dropColumn(['landing_key', 'is_landing_active']);
        });
    }
};
