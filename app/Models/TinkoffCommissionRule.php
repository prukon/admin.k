<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TinkoffCommissionRule extends Model
{
    protected $fillable = [
        'partner_id',
        'method',

        // Комиссия банка за приём платежа
        'acquiring_percent',
        'acquiring_min_fixed',

        // Комиссия банка за выплату партнёру
        'payout_percent',
        'payout_min_fixed',

        // Комиссия платформы
        'platform_percent',
        'platform_min_fixed',

        // legacy (оставляем, чтобы не ломать массовые апдейты старым кодом)
        'percent',
        'min_fixed',

        'is_enabled',
    ];

    public function partner()
    {
        return $this->belongsTo(\App\Models\Partner::class, 'partner_id');
    }
}
