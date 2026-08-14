<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainerSalaryKansasGroupBaseline extends Model
{
    protected $table = 'trainer_salary_kansas_group_baselines';

    protected $guarded = [];

    protected $casts = [
        'trainer_salary_period_id' => 'int',
        'team_id' => 'int',
        'base_avg_students_tenths' => 'int',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(TrainerSalaryPeriod::class, 'trainer_salary_period_id');
    }
}
