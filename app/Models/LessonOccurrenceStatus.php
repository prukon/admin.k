<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonOccurrenceStatus extends Model
{
    protected $table = 'lesson_occurrence_statuses';

    protected $guarded = [];

    protected $casts = [
        'partner_id' => 'int',
        'sort_order' => 'int',
        'is_system' => 'bool',
        'is_active' => 'bool',
        'consumes_lesson' => 'bool',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }
}
