<?php

namespace App\Models;

use App\Support\UserPriceTeamMembership;
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
        'team_id'         => 'integer',
    ];

    protected $appends = [
        'effective_is_paid',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
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

    /**
     * Отметить месячное начисление оплаченным (webhook / refund).
     */
    public static function markMonthlyPaid(int $userId, string $month, ?int $teamId = null, bool $paid = true): void
    {
        if ($teamId === null || $teamId <= 0) {
            $user = User::query()->find($userId);
            if ($user && (int) $user->partner_id > 0) {
                $teamId = UserPriceTeamMembership::primaryTeamIdForStudent($user, (int) $user->partner_id);
            }
        }

        if ($teamId !== null && $teamId > 0) {
            static::updateOrCreate(
                [
                    'user_id'   => $userId,
                    'team_id'   => $teamId,
                    'new_month' => $month,
                ],
                [
                    'is_paid' => $paid ? 1 : 0,
                ]
            );

            return;
        }

        $rows = static::query()
            ->where('user_id', $userId)
            ->whereDate('new_month', $month)
            ->get();

        if ($rows->count() === 1) {
            $rows->first()->update(['is_paid' => $paid ? 1 : 0]);
        }
    }
}
