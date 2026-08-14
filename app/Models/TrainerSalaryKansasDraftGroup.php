<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainerSalaryKansasDraftGroup extends Model
{
    protected $table = 'trainer_salary_kansas_draft_groups';

    protected $guarded = [];

    protected $casts = [
        'trainer_salary_draft_line_id' => 'int',
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

    public function draftLine(): BelongsTo
    {
        return $this->belongsTo(TrainerSalaryDraftLine::class, 'trainer_salary_draft_line_id');
    }
}
