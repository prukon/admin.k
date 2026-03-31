<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractSignRequest extends Model
{

    protected $guarded = [];
    protected $casts = [
        'meta' => 'array',
    ];

public static array $STATUS_RU = [
'created' => 'Создана',
'sent'    => 'Отправлена',
'failed'  => 'Ошибка',
'resent'  => 'Отправлена повторно',
];


public static array $STATUS_BADGE = [
'created' => 'bg-secondary',
'sent'    => 'bg-warning text-dark', // жёлтый
'resent'  => 'bg-warning text-dark', // жёлтый
'failed'  => 'bg-danger',            // красный
];

    public function getStatusRuAttribute(): string
    {
        return self::$STATUS_RU[$this->status] ?? ucfirst($this->status);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function getStatusBadgeClassAttribute(): string
    {
        $s = (string)$this->status;
        return self::$STATUS_BADGE[$s] ?? 'bg-secondary';
    }

    /** Опционально: удобно иметь готовое ФИО */
    public function getSignerFioAttribute(): string
    {
        return trim(preg_replace(
            '/\s+/', ' ',
            ($this->signer_lastname ?? '').' '.($this->signer_firstname ?? '').' '.($this->signer_middlename ?? '')
        )) ?: (string)($this->signer_name ?? '');
    }
}


