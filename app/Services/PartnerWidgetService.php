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
            if ($existing->landing_key === null || $existing->landing_key === '') {
                $existing->landing_key = $this->generateUniqueLandingKey();
                $existing->save();
            }

            return $existing->fresh();
        }

        return PartnerWidget::create([
            'partner_id'        => $partnerId,
            'widget_key'        => $this->generateUniqueWidgetKey(),
            'landing_key'       => $this->generateUniqueLandingKey(),
            'is_active'         => true,
            'is_landing_active' => true,
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

    private function generateUniqueLandingKey(): string
    {
        do {
            $key = Str::random(48);
        } while (PartnerWidget::query()->where('landing_key', $key)->exists());

        return $key;
    }
}
