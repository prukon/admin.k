<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contract extends Model
{
    protected $fillable = [
        'school_id', 'user_id', 'group_id', 'source_pdf_path', 'source_sha256',
        'provider', 'provider_doc_id', 'status', 'signed_pdf_path', 'signed_at'
    ];

    protected $casts = [
        'signed_at' => 'datetime',
    ];

    // Статусы
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SENT = 'sent';
    public const STATUS_OPENED = 'opened';
    public const STATUS_SIGNED = 'signed';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_REVOKED = 'revoked';
    public const STATUS_FAILED = 'failed';

public static array $STATUS_RU = [self::STATUS_DRAFT => 'Черновик',
self::STATUS_SENT => 'Отправлено',
self::STATUS_OPENED => 'Открыто',
self::STATUS_SIGNED => 'Подписано',
self::STATUS_EXPIRED => 'Истёк срок',
self::STATUS_REVOKED => 'Отозвано',
self::STATUS_FAILED => 'Ошибка'];


public static array $STATUS_BADGE = [
self::STATUS_DRAFT   => 'bg-secondary',
self::STATUS_SENT    => 'bg-warning text-dark', // жёлтый
self::STATUS_OPENED  => 'bg-info',
self::STATUS_SIGNED  => 'bg-success',           // зелёный
self::STATUS_EXPIRED => 'bg-dark',
self::STATUS_REVOKED => 'bg-secondary',
self::STATUS_FAILED  => 'bg-danger',            // красный
];

    // Связи
    public function signRequests(): HasMany
    {
        return $this->hasMany(ContractSignRequest::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ContractEvent::class);
    }

    public function getStatusRuAttribute(): string
    {
        return self::$STATUS_RU[$this->status] ?? ucfirst($this->status);
    }

    public function getStatusBadgeClassAttribute(): string
    {
        $s = (string)$this->status;
        return self::$STATUS_BADGE[$s] ?? 'bg-secondary';
    }
}
