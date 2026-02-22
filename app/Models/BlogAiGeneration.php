<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlogAiGeneration extends Model
{
    protected $table = 'blog_ai_generations';

    protected $fillable = [
        'user_id',
        'blog_post_id',
        'blog_ai_generated_image_id',
        'blog_category_id',
        'action',
        'status',
        'budget_date',
        'progress',
        'phase',
        'want_cover_image',
        'inline_images_count',
        'prompt_user',
        'prompt_template_snapshot',
        'model',
        'max_output_tokens',
        'request_payload',
        'response_raw',
        'response_json',
        'usage_input_tokens',
        'usage_output_tokens',
        'cost_usd',
        'cost_text_usd',
        'cost_images_usd',
        'cost_total_usd',
        'reserved_usd',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_json' => 'array',
        'budget_date' => 'date:Y-m-d',
        'want_cover_image' => 'bool',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'cost_usd' => 'decimal:4',
        'cost_text_usd' => 'decimal:4',
        'cost_images_usd' => 'decimal:4',
        'cost_total_usd' => 'decimal:4',
        'reserved_usd' => 'decimal:4',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(BlogPost::class, 'blog_post_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class, 'blog_category_id');
    }
}

