<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Relations\HasOne;

class Contract extends Model
{
    protected $guarded = []; //разрешение на изменение данных в таблице}


    protected $casts = [
        'signed_at' => 'datetime',
    ];

    // Статусы
    public const STATUS_DRAFT   = 'draft';
    public const STATUS_SENT    = 'sent';
    public const STATUS_OPENED  = 'opened';
    public const STATUS_SIGNED  = 'signed';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_REVOKED = 'revoked';
    public const STATUS_FAILED  = 'failed';

    public static array $STATUS_RU = [
        self::STATUS_DRAFT   => 'Черновик',
        self::STATUS_SENT    => 'Отправлено',
        self::STATUS_OPENED  => 'Открыто',
        self::STATUS_SIGNED  => 'Подписано',
        self::STATUS_EXPIRED => 'Истёк срок',
        self::STATUS_REVOKED => 'Отозвано',
        self::STATUS_FAILED  => 'Ошибка',
    ];

    public static array $STATUS_BADGE = [
        self::STATUS_DRAFT   => 'bg-secondary',
        self::STATUS_SENT    => 'bg-warning text-dark',
        self::STATUS_OPENED  => 'bg-info',
        self::STATUS_SIGNED  => 'bg-success',
        self::STATUS_EXPIRED => 'bg-dark',
        self::STATUS_REVOKED => 'bg-secondary',
        self::STATUS_FAILED  => 'bg-danger',
    ];

    // ===== Связи =====
    public function signRequests(): HasMany
    {
        return $this->hasMany(ContractSignRequest::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ContractEvent::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'group_id');
    }

    public function lastSignRequest(): HasOne
    {
        return $this->hasOne(ContractSignRequest::class)->latestOfMany();
    }

    // ===== Аксессоры =====
    public function getStatusRuAttribute(): string
    {
        return self::$STATUS_RU[$this->status] ?? ucfirst($this->status);
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return self::$STATUS_BADGE[$this->status] ?? 'bg-secondary';
    }

    public function getStudentFullNameAttribute(): string
    {
        return trim(($this->user->lastname ?? '') . ' ' . ($this->user->name ?? ''));
    }

    public function getSignerNameAttribute(): ?string
    {
        return $this->lastSignRequest?->signer_name;
    }

    public function getGroupTitleAttribute(): string
    {
        return $this->team?->title ?? '—';
    }
}
