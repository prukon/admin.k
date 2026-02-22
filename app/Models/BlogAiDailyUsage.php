<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlogAiDailyUsage extends Model
{
    protected $table = 'blog_ai_daily_usages';

    protected $fillable = [
        'date',
        'reserved_usd',
        'spent_usd',
        'reserved_input_tokens',
        'reserved_output_tokens',
        'spent_input_tokens',
        'spent_output_tokens',
        'requests_count',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'reserved_usd' => 'decimal:4',
        'spent_usd' => 'decimal:4',
    ];
}

