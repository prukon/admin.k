<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Thread extends Model
{
    use SoftDeletes;

    protected $table = 'threads';

    protected $fillable = [
        'subject',       // название группы/диалога
        'is_group',      // tinyint(1) / boolean
        'avatar_url',    // опционально: URL аватара группы
    ];

    protected $casts = [
        'is_group' => 'boolean',
    ];

    // чтобы сразу приходили удобные поля во фронт JSON
    protected $appends = [
        'title',
        'member_count',
        'avatar_url', // да, дублируем аксессор поверх поля для единого интерфейса
    ];

    /**
     * Участники треда как пользователи через пивот participants (thread_id, user_id, [deleted_at]).
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'participants', 'thread_id', 'user_id')
            ->withTimestamps()
            ->whereNull('participants.deleted_at'); // если participants использует soft deletes
    }

    /**
     * Сообщения треда.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'thread_id');
    }

    /**
     * Последнее сообщение (для превью).
     * Требует Laravel 8+ (latestOfMany).
     */
    public function lastMessage(): HasOne
    {
        return $this->hasOne(Message::class, 'thread_id')->latestOfMany('id');
    }

    /**
     * Название для фронта.
     * Для групп — subject, для 1-1 можно оставить subject или подставить имя собеседника (если нужно — доработай).
     */
    public function getTitleAttribute(): string
    {
        // если нужно динамически для 1-1 показывать имя второго участника:
        // if (!$this->is_group && $this->relationLoaded('users') && $this->users->count() === 2) {
        //     $other = $this->users->firstWhere('id', '!=', auth()->id());
        //     if ($other) return $other->name;
        // }
        return $this->attributes['subject'] ?? 'Диалог';
    }

    /**
     * Кол-во участников.
     */
    public function getMemberCountAttribute(): int
    {
        if ($this->relationLoaded('users')) {
            return $this->users->count();
        }
        return (int) $this->users()->count();
    }

    /**
     * Аватар группы (или дефолт).
     * Если хочешь строить URL через Storage, замени на Storage::url($this->attributes['avatar_url'])
     */
    public function getAvatarUrlAttribute(): string
    {
        $val = $this->attributes['avatar_url'] ?? null;
        return $val ?: '/img/default-avatar.png';
    }

    /**
     * Идентификаторы получателей, исключая конкретного user_id (обычно — отправителя).
     */
    public function recipientIdsExcept(int $userId): array
    {
        return $this->users()
            ->where('users.id', '!=', $userId)
            ->pluck('users.id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * Удобный скоуп: подгрузить резюме треда для списка (users count + lastMessage).
     */
    public function scopeWithSummary($q)
    {
        return $q->withCount(['users as member_count'])
            ->with(['lastMessage:id,thread_id,user_id,body,created_at']);
    }
}
