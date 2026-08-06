<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartnerWalletTransaction extends Model
{
    protected $fillable = [
        'partner_id','user_id','type','amount_cents','currency',
        'provider','payment_id','status','description','meta',
    ];

    protected $casts = [
        'amount_cents' => 'integer',
        'meta' => 'array',
    ];

    public function partner() {
        return $this->belongsTo(Partner::class);
    }

    public function user() {
        return $this->belongsTo(User::class);
    }
}
