<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TinkoffPayout extends Model
{
    protected $fillable = [
        'payment_id','partner_id','deal_id','amount','is_final','status',
        'tinkoff_payout_payment_id','when_to_run','payload_init','payload_payment','payload_state','completed_at',
    ];

    protected $casts = [
        'payload_init' => 'array',
        'payload_payment' => 'array',
        'payload_state' => 'array',
        'when_to_run' => 'datetime',
        'completed_at' => 'datetime',
        'is_final' => 'boolean',
    ];

    public function partner() { return $this->belongsTo(\App\Models\Partner::class); }
    public function payment() { return $this->belongsTo(TinkoffPayment::class, 'payment_id'); }
}
