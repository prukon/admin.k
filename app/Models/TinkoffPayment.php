<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
}
