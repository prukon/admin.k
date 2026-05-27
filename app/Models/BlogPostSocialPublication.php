<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlogPostSocialPublication extends Model
{
    public const PLATFORM_VK = 'vk';

    public const STATUS_PENDING_COVER = 'pending_cover';
    public const STATUS_PENDING_SCHEDULE = 'pending_schedule';
    public const STATUS_PENDING = 'pending';
    public const STATUS_PUBLISHING = 'publishing';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'blog_post_id',
        'platform',
        'status',
        'external_post_id',
        'vk_message_snapshot',
        'error_message',
        'attempts',
        'published_at',
    ];

    protected $casts = [
        'attempts' => 'int',
        'published_at' => 'datetime',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(BlogPost::class, 'blog_post_id');
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [
            self::STATUS_PUBLISHED,
            self::STATUS_SKIPPED,
        ], true);
    }
}
