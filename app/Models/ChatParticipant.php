<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChatParticipant extends Model
{
    use SoftDeletes;

    protected $table = 'participants';

    protected $fillable = [
        'thread_id',
        'user_id',
        'last_read',
    ];

    protected $casts = [
        'last_read' => 'datetime',
    ];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(ChatThread::class, 'thread_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
