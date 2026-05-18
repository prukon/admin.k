<?php

namespace App\Services;

use App\Models\Partner;
use App\Models\PartnerWidget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PartnerWidgetService
{
    public function ensureForPartner(int $partnerId): PartnerWidget
    {
        $existing = PartnerWidget::query()
            ->where('partner_id', $partnerId)
            ->first();

        if ($existing) {
            return $existing;
        }

        return PartnerWidget::create([
            'partner_id' => $partnerId,
            'widget_key' => $this->generateUniqueWidgetKey(),
            'is_active'  => true,
        ]);
    }

    public function ensureForAllPartners(): void
    {
        Partner::query()
            ->pluck('id')
            ->each(fn (int $partnerId) => $this->ensureForPartner($partnerId));
    }

    public function regenerateWidgetKey(PartnerWidget $widget): PartnerWidget
    {
        $widget->widget_key = $this->generateUniqueWidgetKey();
        $widget->save();

        return $widget->fresh();
    }

    private function generateUniqueWidgetKey(): string
    {
        do {
            $key = Str::random(48);
        } while (PartnerWidget::query()->where('widget_key', $key)->exists());

        return $key;
    }
}
