<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainerSalaryKansasSnapshotTrainer extends Model
{
    protected $table = 'trainer_salary_kansas_snapshot_trainers';

    protected $guarded = [];

    protected $casts = [
        'trainer_salary_snapshot_id' => 'int',
        'rate_per_training_cents' => 'integer',
        'base_premium_cents' => 'integer',
        'premium_increment_cents' => 'integer',
    ];

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(TrainerSalarySnapshot::class, 'trainer_salary_snapshot_id');
    }
}
