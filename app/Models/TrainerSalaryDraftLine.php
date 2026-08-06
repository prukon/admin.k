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
        'base_salary_cents' => 'integer',
        'rate_per_training_cents' => 'integer',
        'trainings_count' => 'int',
        'trainings_amount_cents' => 'integer',
        'bonuses_cents' => 'integer',
        'deductions_cents' => 'integer',
        'total_cents' => 'integer',
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
