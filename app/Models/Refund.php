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

    public function setMetaAttribute($value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['meta'] = null;
            return;
        }
        $this->attributes['meta'] = is_array($value)
            ? json_encode($value, JSON_UNESCAPED_UNICODE)
            : (string) $value;
    }

    public function getMetaAttribute($value): array
    {
        if (empty($value)) {
            return [];
        }
        $arr = json_decode($value, true);
        return is_array($arr) ? $arr : [];
    }

    public function payable()
    {
        return $this->belongsTo(Payable::class, 'payable_id');
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }
}


