<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractEvent extends Model
{
    protected $guarded = [];

    public const PROVIDER_SEND_NOT_CONFIRMED = 'Провайдер не подтвердил отправку SMS.';

    /** @var list<string> */
    public const FAILURE_TYPES = ['failed', 'resend_failed'];

    public static array $TYPE_RU = [
        'created'             => 'Создан',
        'balance_charged'     => 'Списание с баланса',
        'balance_refunded'    => 'Возврат на баланс',
        'client_invited_to_fill' => 'Клиент приглашён заполнить договор',
        'client_invite_email_failed' => 'Ошибка email-уведомления клиенту',
        'pdf_generated_by_client' => 'PDF сформирован клиентом',
        'revoked'             => 'Отозван',
        'sent'                => 'Отправлено СМС',
        'failed'              => 'Ошибка',
        'status_sync'         => 'Синхронизация статуса',
        'email_sent'          => 'Отправлено на email',
        'signed_pdf_saved'    => 'Подписанный файл сохранён',
        'signed_after_revoke' => 'Подписанный файл получен после аннулирования',
        'revoke_not_supported'=> 'Отзыв не поддерживается',
        'resend'              => 'Повторная отправка СМС',
        'resend_failed'       => 'Повторная отправка СМС — ошибка',
        'failed_status'       => 'Ошибка получения статуса',
        'document_opened'     => 'Документ открыт (вебхук)',
        'document_signed'     => 'Документ подписан (вебхук)',
        'unknown'             => 'Неизвестное событие',
        'webhook_document_signed'=> 'Ответ от провайдера: "Договор подписан"',
        'webhook_document_opened'=> 'Ответ от провайдера: "СМС открыта"',
    ];

    public function getTypeRuAttribute(): string
    {
        return self::$TYPE_RU[$this->type] ?? $this->type;
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function userFacingMessage(): ?string
    {
        return self::messageFromPayloadJson($this->payload_json);
    }

    public static function messageFromPayloadJson(?string $json): ?string
    {
        $payload = json_decode((string) $json, true);
        if (!is_array($payload)) {
            return null;
        }

        return self::firstMessage($payload);
    }

    /**
     * @param  array<string, mixed>  $res  ответ SignatureProvider::send / resend
     */
    public static function fromProviderSendResult(array $res): string
    {
        return self::firstMessage(['res' => $res])
            ?? self::firstMessage($res)
            ?? self::PROVIDER_SEND_NOT_CONFIRMED;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function firstMessage(array $payload): ?string
    {
        $candidates = [
            $payload['message'] ?? null,
            $payload['error'] ?? null,
            data_get($payload, 'res.raw.message'),
            data_get($payload, 'res.message'),
            data_get($payload, 'resp.message'),
        ];

        foreach ($candidates as $candidate) {
            if (!is_string($candidate)) {
                continue;
            }
            $text = trim($candidate);
            if ($text !== '') {
                return $text;
            }
        }

        return null;
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function getAuthorFioAttribute(): string
    {
        $u = $this->author;
        if (!$u) {
            return 'Система';
        }
        $last = trim((string) ($u->lastname ?? ''));
        $name = trim((string) ($u->name ?? ''));
        $fio = trim($last.' '.$name);

        return $fio !== '' ? $fio : ($u->email ?? 'Пользователь');
    }
}
