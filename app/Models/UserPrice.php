<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserPrice extends Model
{
    use HasFactory;

    protected $table = 'users_prices'; //явное указание к какой таблице в БД привязана модель
    protected $guarded = []; //разрешение на изменение данных в таблице}

    protected $casts = [
        'is_paid'         => 'boolean',
        'is_manual_paid'  => 'boolean',
        'manual_paid_at'  => 'datetime',
        'manual_paid_by'  => 'integer',
    ];

    protected $appends = [
        'effective_is_paid',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function manualPaidBy()
    {
        return $this->belongsTo(User::class, 'manual_paid_by');
    }

    /**
     * Факт «оплачен ли месяц» для UI/отчётов: ручной override важнее автоматического is_paid.
     */
    public function getEffectiveIsPaidAttribute(): bool
    {
        if ($this->is_manual_paid !== null) {
            return (bool) $this->is_manual_paid;
        }

        return (bool) $this->is_paid;
    }
}
