<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainerSalaryKansasSnapshotGroup extends Model
{
    protected $table = 'trainer_salary_kansas_snapshot_groups';

    protected $guarded = [];

    protected $casts = [
        'trainer_salary_snapshot_id' => 'int',
        'team_id' => 'int',
        'trainings_count' => 'int',
        'students_visited_sum' => 'int',
        'fact_avg_tenths' => 'int',
        'base_avg_tenths' => 'int',
        'diff_tenths' => 'int',
        'premium_cents' => 'integer',
        'pay_per_training_cents' => 'integer',
        'group_total_cents' => 'integer',
    ];

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(TrainerSalarySnapshot::class, 'trainer_salary_snapshot_id');
    }
}
