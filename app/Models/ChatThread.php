<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChatThread extends Model
{
    use SoftDeletes;

    protected $table = 'threads';

    protected $fillable = [
        'subject',
        'is_group',
        'team_id',
        'last_message_id',
    ];

    protected $casts = [
        'is_group' => 'boolean',
    ];

    public function participants(): HasMany
    {
        return $this->hasMany(ChatParticipant::class, 'thread_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'thread_id');
    }

    public function lastMessage(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'last_message_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'participants', 'thread_id', 'user_id')
            ->withTimestamps()
            ->whereNull('participants.deleted_at');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    public function hasParticipant(int $userId): bool
    {
        return $this->participants()->where('user_id', $userId)->exists();
    }
}
