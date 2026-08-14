<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainerSalaryKansasPeriodSetting extends Model
{
    protected $table = 'trainer_salary_kansas_period_settings';

    protected $guarded = [];

    protected $casts = [
        'trainer_salary_period_id' => 'int',
        'premium_increment_cents' => 'integer',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(TrainerSalaryPeriod::class, 'trainer_salary_period_id');
    }
}
