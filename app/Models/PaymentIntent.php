<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentIntent extends Model
{
    use HasFactory;

    protected $table = 'payment_intents';

    protected $fillable = [
        'partner_id',
        'user_id',
        'provider',
        'provider_inv_id',
        'status',
        'out_sum',
        'payment_date',
        'meta',
        'paid_at',
    ];

    protected $casts = [
        'out_sum' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function partner()
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }
}


