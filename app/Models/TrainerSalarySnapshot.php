<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\PartnerContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainerSalarySnapshot extends Model
{
    protected $table = 'trainer_salary_snapshots';

    protected $guarded = [];

    protected $casts = [
        'trainer_salary_period_id' => 'int',
        'trainer_profile_id' => 'int',
        'version' => 'int',
        'scheme_code' => 'string',
        'base_salary_cents' => 'integer',
        'rate_per_training_cents' => 'integer',
        'trainings_count' => 'int',
        'trainings_amount_cents' => 'integer',
        'bonuses_cents' => 'integer',
        'deductions_cents' => 'integer',
        'total_cents' => 'integer',
        'formed_by_user_id' => 'int',
        'formed_at' => 'datetime',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(TrainerSalaryPeriod::class, 'trainer_salary_period_id');
    }

    public function trainerProfile(): BelongsTo
    {
        return $this->belongsTo(TrainerProfile::class, 'trainer_profile_id');
    }

    public function formedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'formed_by_user_id');
    }

    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $partnerId = app(PartnerContext::class)->partnerId();
        $query = static::query()->whereKey($value);

        if ($partnerId) {
            $query->whereHas('period', fn ($q) => $q->where('partner_id', (int) $partnerId));
        }

        return $query->firstOrFail();
    }
}
