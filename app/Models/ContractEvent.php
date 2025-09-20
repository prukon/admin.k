<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractEvent extends Model
{
    protected $fillable = [
        'contract_id',
        'type',
        'payload_json',
        'author_id'        // ← добавили
    ];

    // ContractEvent.php
public static array $TYPE_RU = [
'created'             => 'Создан',
'sent'                => 'Отправлено',
'failed'              => 'Ошибка',
'status_sync'         => 'Синхронизация статуса',
'email_sent'          => 'Отправлено на email',
'signed_pdf_saved'    => 'Подписанный файл сохранён',
'revoke_not_supported'=> 'Отзыв не поддерживается',
'resend'              => 'Повторная отправка',
'resend_failed'       => 'Повторная отправка — ошибка',
'failed_status'       => 'Ошибка получения статуса',
'document_opened'     => 'Документ открыт (вебхук)',
'document_signed'     => 'Документ подписан (вебхук)',
'unknown'             => 'Неизвестное событие',
'webhook_document_signed'=> 'Ответ от провайдера подписан',
'webhook_document_opened'=> 'Ответ от провайдера открыт',
];

    public function getTypeRuAttribute(): string
    {
        return self::$TYPE_RU[$this->type] ?? $this->type;
    }

    public function contract(): BelongsTo {
        return $this->belongsTo(Contract::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    // Удобный аксессор для вывода Фамилия Имя
    public function getAuthorFioAttribute(): string
    {
        $u = $this->author;
        if (!$u) return 'Система';
        $last = trim((string)($u->lastname ?? ''));
        $name = trim((string)($u->name ?? ''));
        $fio = trim($last.' '.$name);
        return $fio !== '' ? $fio : ($u->email ?? 'Пользователь');
    }
}
