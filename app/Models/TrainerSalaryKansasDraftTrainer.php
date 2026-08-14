<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainerSalaryKansasDraftTrainer extends Model
{
    protected $table = 'trainer_salary_kansas_draft_trainers';

    protected $guarded = [];

    protected $casts = [
        'trainer_salary_draft_line_id' => 'int',
        'rate_per_training_cents' => 'integer',
        'base_premium_cents' => 'integer',
    ];

    public function draftLine(): BelongsTo
    {
        return $this->belongsTo(TrainerSalaryDraftLine::class, 'trainer_salary_draft_line_id');
    }
}
