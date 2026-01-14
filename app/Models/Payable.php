<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payable extends Model
{
    use HasFactory;

    protected $table = 'payables';

    protected $fillable = [
        'partner_id',
        'user_id',
        'type',
        'amount',
        'currency',
        'status',
        'month',
        'meta',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'month'  => 'date',
        'paid_at'=> 'datetime',
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

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function partner()
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    public function intents()
    {
        return $this->hasMany(PaymentIntent::class, 'payable_id');
    }
}


