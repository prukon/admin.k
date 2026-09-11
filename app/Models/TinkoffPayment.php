<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class TinkoffPayment extends Model
{
    public const CHANNEL_MULTISPLIT = 'multisplit';

    public const CHANNEL_ACQUIRING = 'acquiring';

    public const METHODS = ['card', 'sbp', 'tpay'];

    public const METHOD_LABELS = [
        'card' => 'Карта',
        'sbp' => 'СБП',
        'tpay' => 'T‑Pay',
    ];

    public static function methodLabel(?string $method): string
    {
        $code = strtolower(trim((string) $method));
        if ($code === '') {
            return '—';
        }
        if ($code === 'sbp_qr') {
            return self::METHOD_LABELS['sbp'];
        }

        return self::METHOD_LABELS[$code] ?? (string) $method;
    }

    protected $fillable = [
        'order_id','partner_id','legal_entity_id','amount','method','channel','status','tinkoff_payment_id',
        'deal_id','payment_url','payload','confirmed_at','canceled_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'confirmed_at' => 'datetime',
        'canceled_at' => 'datetime',
    ];

    public function partner() { return $this->belongsTo(\App\Models\Partner::class); }
    public function legalEntity() { return $this->belongsTo(PartnerLegalEntity::class, 'legal_entity_id'); }
    public function payout()  { return $this->hasOne(TinkoffPayout::class, 'payment_id'); }

    /**
     * CONFIRMED мультисплит без успешной выплаты, когда задержка автовыплаты партнёра уже прошла.
     * Не считает платежи, у которых выплата ещё только назначена (when_to_run в будущем)
     * или уже ушла в банк и не финальна.
     */
    public function scopeMissingPayoutAfterDelay(Builder $q, ?\DateTimeInterface $at = null): Builder
    {
        $at = $at ? Carbon::instance($at) : now();
        $atSql = $at->format('Y-m-d H:i:s');
        $inFlight = ['NEW', 'AUTHORIZING', 'CHECKING', 'CREDIT_CHECKING', 'CHECKED', 'COMPLETING'];

        return $q
            ->where('tinkoff_payments.status', 'CONFIRMED')
            ->where('tinkoff_payments.channel', self::CHANNEL_MULTISPLIT)
            ->whereNotNull('tinkoff_payments.confirmed_at')
            ->whereRaw(
                'tinkoff_payments.confirmed_at <= DATE_SUB(?, INTERVAL COALESCE((
                    SELECT r.auto_payout_delay_hours
                    FROM tinkoff_commission_rules AS r
                    WHERE r.is_enabled = 1
                      AND (r.partner_id = tinkoff_payments.partner_id OR r.partner_id IS NULL)
                      AND (r.method = tinkoff_payments.method OR r.method IS NULL)
                    ORDER BY (r.partner_id IS NULL) ASC, (r.method IS NULL) ASC, r.id ASC
                    LIMIT 1
                ), 0) HOUR)',
                [$atSql]
            )
            ->whereNotExists(function ($sub): void {
                $sub->selectRaw('1')
                    ->from('tinkoff_payouts')
                    ->whereColumn('tinkoff_payouts.payment_id', 'tinkoff_payments.id')
                    ->where('tinkoff_payouts.status', 'COMPLETED');
            })
            ->whereNotExists(function ($sub) use ($atSql): void {
                $sub->selectRaw('1')
                    ->from('tinkoff_payouts')
                    ->whereColumn('tinkoff_payouts.payment_id', 'tinkoff_payments.id')
                    ->where('tinkoff_payouts.status', 'INITIATED')
                    ->whereNotNull('tinkoff_payouts.when_to_run')
                    ->where('tinkoff_payouts.when_to_run', '>', $atSql);
            })
            ->whereNotExists(function ($sub) use ($inFlight): void {
                $sub->selectRaw('1')
                    ->from('tinkoff_payouts')
                    ->whereColumn('tinkoff_payouts.payment_id', 'tinkoff_payments.id')
                    ->where(function ($inner) use ($inFlight): void {
                        $inner->whereIn('tinkoff_payouts.status', $inFlight)
                            ->orWhere(function ($sent): void {
                                $sent->where('tinkoff_payouts.status', 'INITIATED')
                                    ->whereNotNull('tinkoff_payouts.tinkoff_payout_payment_id');
                            });
                    });
            });
    }
}
