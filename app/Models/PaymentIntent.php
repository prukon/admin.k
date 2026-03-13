<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentIntent extends Model
{
    use HasFactory;

    protected $table = 'payment_intents';

    protected $guarded = []; //разрешение на изменение данных в таблице}


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

    public function payable()
    {
        return $this->belongsTo(Payable::class, 'payable_id');
    }
}


