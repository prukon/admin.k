<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Refund extends Model
{
    use HasFactory;

    protected $table = 'refunds';

    protected $fillable = [
        'partner_id',
        'user_id',
        'payable_id',
        'payment_id',
        'amount',
        'currency',
        'status',
        'provider',
        'provider_refund_id',
        'processed_at',
        'meta',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'processed_at' => 'datetime',
    ];

    public function payable()
    {
        return $this->belongsTo(Payable::class, 'payable_id');
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }
}


