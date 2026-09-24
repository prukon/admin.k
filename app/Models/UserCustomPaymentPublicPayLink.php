<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserCustomPaymentPublicPayLink extends Model
{
    protected $table = 'user_custom_payment_public_pay_links';

    protected $guarded = [];

    protected $casts = [
        'expires_at' => 'datetime',
        'user_custom_payment_id' => 'int',
        'partner_id' => 'int',
        'payment_intent_id' => 'int',
        'payable_id' => 'int',
    ];

    public function customPayment(): BelongsTo
    {
        return $this->belongsTo(UserCustomPayment::class, 'user_custom_payment_id');
    }
}
