<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TinkoffPayment extends Model
{
    protected $fillable = [
        'order_id','partner_id','amount','method','status','tinkoff_payment_id',
        'deal_id','payment_url','payload','confirmed_at','canceled_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'confirmed_at' => 'datetime',
        'canceled_at' => 'datetime',
    ];

    public function partner() { return $this->belongsTo(\App\Models\Partner::class); }
    public function payout()  { return $this->hasOne(TinkoffPayout::class, 'payment_id'); }
}
