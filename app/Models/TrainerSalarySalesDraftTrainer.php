<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainerSalarySalesDraftTrainer extends Model
{
    protected $table = 'trainer_salary_sales_draft_trainers';

    protected $guarded = [];

    protected $casts = [
        'trainer_salary_draft_line_id' => 'int',
        'sales_percent' => 'int',
        'paid_months_cents' => 'integer',
        'paid_packages_cents' => 'integer',
        'sales_base_cents' => 'integer',
        'commission_cents' => 'integer',
    ];

    public function draftLine(): BelongsTo
    {
        return $this->belongsTo(TrainerSalaryDraftLine::class, 'trainer_salary_draft_line_id');
    }
}
