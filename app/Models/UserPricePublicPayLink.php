<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPricePublicPayLink extends Model
{
    protected $table = 'user_price_public_pay_links';

    protected $guarded = [];

    protected $casts = [
        'expires_at' => 'datetime',
        'users_price_id' => 'int',
        'partner_id' => 'int',
        'payment_intent_id' => 'int',
        'payable_id' => 'int',
    ];

    public function userPrice(): BelongsTo
    {
        return $this->belongsTo(UserPrice::class, 'users_price_id');
    }
}
