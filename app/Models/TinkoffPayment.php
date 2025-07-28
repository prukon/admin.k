<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TinkoffPayment extends Model
{
    protected $fillable = [
        'payment_id', 'order_id', 'amount', 'status', 'response'
    ];

    protected $casts = [
        'response' => 'array',
    ];
}
