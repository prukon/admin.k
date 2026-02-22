<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlogAiGeneratedImage extends Model
{
    protected $table = 'blog_ai_generated_images';

    protected $fillable = [
        'blog_ai_generation_id',
        'blog_post_id',
        'kind',
        'aspect',
        'prompt',
        'alt',
        'status',
        'error_message',
        'output_format',
        'width',
        'height',
        'path',
        'previous_path',
        'cost_usd',
    ];

    protected $casts = [
        'cost_usd' => 'decimal:4',
    ];

    public function generation(): BelongsTo
    {
        return $this->belongsTo(BlogAiGeneration::class, 'blog_ai_generation_id');
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(BlogPost::class, 'blog_post_id');
    }
}

