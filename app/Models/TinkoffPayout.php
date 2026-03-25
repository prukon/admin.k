<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TinkoffPayout extends Model
{
    protected $fillable = [
        'payment_id','partner_id','deal_id','amount','is_final','status',
        'tinkoff_payout_payment_id','when_to_run','payload_init','payload_payment','payload_state','completed_at',
        'source','initiated_by_user_id','payer_user_id',
        'gross_amount','bank_accept_fee','bank_payout_fee','platform_fee','net_amount',
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
    public function payer() { return $this->belongsTo(\App\Models\User::class, 'payer_user_id'); }
    public function initiator() { return $this->belongsTo(\App\Models\User::class, 'initiated_by_user_id'); }

    /**
     * Отложенная выплата, срок которой наступил, но банковский PaymentId ещё не получен
     * (должна подхватываться TinkoffRunScheduledPayoutsJob).
     */
    public function scopeOverdueScheduled(Builder $q): Builder
    {
        return $q
            ->whereNull('tinkoff_payout_payment_id')
            ->whereNotNull('when_to_run')
            ->where('when_to_run', '<=', now())
            ->where('status', 'INITIATED')
            ->whereNull('completed_at');
    }
}
