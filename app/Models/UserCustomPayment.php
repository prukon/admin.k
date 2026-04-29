<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class UserCustomPayment extends Model
{
    use HasFactory;

    protected $table = 'user_custom_payment';

    protected $guarded = [];

    protected $casts = [
        'partner_id'      => 'integer',
        'user_id'         => 'integer',
        'date_start'      => 'date:Y-m-d',
        'date_end'        => 'date:Y-m-d',
        'amount'          => 'decimal:2',
        'is_paid'         => 'boolean',
        'is_manual_paid'  => 'boolean',
        'manual_paid_at'  => 'datetime',
        'manual_paid_by'  => 'integer',
    ];

    protected $appends = [
        'effective_is_paid',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function manualPaidBy()
    {
        return $this->belongsTo(User::class, 'manual_paid_by');
    }

    public function getEffectiveIsPaidAttribute(): bool
    {
        if ($this->is_manual_paid !== null) {
            return (bool) $this->is_manual_paid;
        }

        return (bool) $this->is_paid;
    }
}

