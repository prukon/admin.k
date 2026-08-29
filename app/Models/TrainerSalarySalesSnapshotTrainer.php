<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainerSalarySalesSnapshotTrainer extends Model
{
    protected $table = 'trainer_salary_sales_snapshot_trainers';

    protected $guarded = [];

    protected $casts = [
        'trainer_salary_snapshot_id' => 'int',
        'sales_percent' => 'int',
        'paid_months_cents' => 'integer',
        'paid_packages_cents' => 'integer',
        'sales_base_cents' => 'integer',
        'commission_cents' => 'integer',
    ];

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(TrainerSalarySnapshot::class, 'trainer_salary_snapshot_id');
    }
}
