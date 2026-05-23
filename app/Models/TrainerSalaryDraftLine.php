<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainerSalaryDraftLine extends Model
{
    protected $table = 'trainer_salary_draft_lines';

    protected $guarded = [];

    protected $casts = [
        'trainer_salary_period_id' => 'int',
        'trainer_profile_id' => 'int',
        'base_salary' => 'decimal:2',
        'rate_per_training' => 'decimal:2',
        'trainings_count' => 'int',
        'trainings_amount' => 'decimal:2',
        'bonuses' => 'decimal:2',
        'deductions' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(TrainerSalaryPeriod::class, 'trainer_salary_period_id');
    }

    public function trainerProfile(): BelongsTo
    {
        return $this->belongsTo(TrainerProfile::class, 'trainer_profile_id');
    }
}
