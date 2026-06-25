<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Collection;

class TinkoffCommissionRule extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'is_enabled' => 'boolean',
        'auto_payout_enabled' => 'boolean',
        'auto_payout_delay_hours' => 'integer',
    ];

    public function partner()
    {
        return $this->belongsTo(\App\Models\Partner::class, 'partner_id');
    }

    /**
     * Подбор правила комиссии/автовыплаты по партнёру и методу оплаты.
     * Приоритет: конкретный партнёр + метод → партнёр без метода → глобальные правила.
     */
    public static function pickForPartner(int $partnerId, ?string $method): self
    {
        $rules = static::query()
            ->where('is_enabled', true)
            ->orderByRaw('partner_id is null, method is null')
            ->get();

        $chosen = $rules->first(function (self $rule) use ($partnerId, $method) {
            return ($rule->partner_id === null || (int) $rule->partner_id === $partnerId)
                && ($rule->method === null || (string) $rule->method === (string) $method);
        });

        return $chosen ?: new self([
            'acquiring_percent' => 2.49,
            'acquiring_min_fixed' => 3.49,
            'payout_percent' => 0.10,
            'payout_min_fixed' => 0.00,
            'platform_percent' => 2.00,
            'platform_min_fixed' => 0.00,
            'auto_payout_enabled' => false,
            'auto_payout_delay_hours' => 0,
        ]);
    }

    /**
     * Сводка автовыплат по правилам партнёра для UI.
     *
     * @return Collection<int, array{method: string, enabled: bool, delay_hours: int}>
     */
    public static function autoPayoutSummaryForPartner(int $partnerId): Collection
    {
        return static::query()
            ->where('partner_id', $partnerId)
            ->orderByRaw('method is null desc')
            ->orderBy('method')
            ->get()
            ->map(function (self $rule) {
                $method = $rule->method;
                $label = TinkoffCommissionRule::methodLabel($method);

                return [
                    'method' => $label,
                    'enabled' => (bool) $rule->auto_payout_enabled,
                    'delay_hours' => (int) $rule->auto_payout_delay_hours,
                ];
            });
    }

    public static function methodLabel(?string $method): string
    {
        return match ($method) {
            'card' => 'карта',
            'sbp' => 'СБП',
            'tpay' => 'T‑Pay',
            default => 'все методы',
        };
    }
}
