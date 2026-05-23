<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainerSalaryPeriod extends Model
{
    protected $table = 'trainer_salary_periods';

    protected $guarded = [];

    protected $casts = [
        'partner_id' => 'int',
        'year' => 'int',
        'month' => 'int',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    public function draftLines(): HasMany
    {
        return $this->hasMany(TrainerSalaryDraftLine::class, 'trainer_salary_period_id');
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(TrainerSalarySnapshot::class, 'trainer_salary_period_id');
    }
}
