<?php

namespace App\Listeners;

use App\Models\OutgoingEmailLog;
use App\Services\PartnerContext;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Throwable;

/**
 * Слушатель исходящих писем (Mail и Notifications через mail-канал).
 *
 * - На MessageSending создаём запись со статусом 'sending', заполняем поля
 *   из Symfony\Component\Mime\Email и кладём в заголовок письма
 *   X-Outgoing-Email-Log-Id, чтобы найти запись на MessageSent.
 * - На MessageSent проставляем статус 'sent' и sent_at.
 *
 * Источник partner_id (по приоритету):
 *   1) заголовок X-Partner-Id (Mailable/Notification сам его проставил),
 *   2) PartnerContext — если письмо отправлено синхронно из web-запроса,
 *   3) null.
 *
 * Об ошибках доставки (статус 'failed') в первом релизе НЕ заботимся:
 *   запись остаётся в 'sending'. Возможный failed-путь — отдельная задача.
 */
class LogOutgoingEmail
{
    private const HEADER_LOG_ID    = 'X-Outgoing-Email-Log-Id';
    private const HEADER_PARTNER   = 'X-Partner-Id';

    private PartnerContext $partnerContext;

    public function __construct(PartnerContext $partnerContext)
    {
        $this->partnerContext = $partnerContext;
    }

    /**
     * Хук на Illuminate\Mail\Events\MessageSending.
     */
    public function sending(MessageSending $event): void
    {
        try {
            $message = $event->message;
            if (! $message instanceof Email) {
                return;
            }

            $partnerId = $this->extractPartnerId($message);

            $log = OutgoingEmailLog::create([
                'partner_id'             => $partnerId,
                'status'                 => OutgoingEmailLog::STATUS_SENDING,
                'queue'                  => $this->extractEventString($event, 'queue'),
                'database_queue_job_id'  => $this->extractDatabaseQueueJobId(),
                'from_address'           => $this->firstAddress($message->getFrom())?->getAddress(),
                'from_name'              => $this->firstAddress($message->getFrom())?->getName() ?: null,
                'reply_to'               => $this->addressesToArray($message->getReplyTo()),
                'to_addresses'           => $this->addressesToArray($message->getTo()),
                'to_summary'             => $this->summarizeAddresses($message->getTo(), 1024),
                'cc_addresses'           => $this->addressesToArray($message->getCc()),
                'bcc_addresses'          => $this->addressesToArray($message->getBcc()),
                'subject'                => $this->trimToLength((string) $message->getSubject(), 998),
                'html_body'              => $message->getHtmlBody(),
                'text_body'              => $message->getTextBody(),
                'notification_class'     => $this->extractEventString($event, 'data.__laravel_notification'),
                'laravel_notification_id'=> $this->extractEventString($event, 'data.__laravel_notification_id'),
                'notifiable_type'        => null,
                'notifiable_id'          => null,
                'mailable_class'         => $this->extractMailableClass($event),
                'attachments'            => $this->collectAttachmentsSummary($message),
                'send_attempts'          => 1,
                'sent_at'                => null,
                'failed_at'              => null,
                'error_message'          => null,
            ]);

            $headers = $message->getHeaders();
            if (! $headers->has(self::HEADER_LOG_ID)) {
                $headers->addTextHeader(self::HEADER_LOG_ID, (string) $log->id);
            }
        } catch (Throwable $e) {
            Log::warning('[LogOutgoingEmail.sending] failed to log outgoing email', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Хук на Illuminate\Mail\Events\MessageSent.
     */
    public function sent(MessageSent $event): void
    {
        try {
            $message = $event->message;
            if (! $message instanceof Email) {
                return;
            }

            $logId = $this->extractLogIdFromHeaders($message);
            if ($logId === null) {
                return;
            }

            OutgoingEmailLog::query()
                ->whereKey($logId)
                ->update([
                    'status'  => OutgoingEmailLog::STATUS_SENT,
                    'sent_at' => now(),
                ]);
        } catch (Throwable $e) {
            Log::warning('[LogOutgoingEmail.sent] failed to mark outgoing email as sent', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function extractPartnerId(Email $message): ?int
    {
        $headers = $message->getHeaders();

        if ($headers->has(self::HEADER_PARTNER)) {
            $raw = trim((string) $headers->get(self::HEADER_PARTNER)?->getBodyAsString());
            if ($raw !== '' && ctype_digit($raw)) {
                $val = (int) $raw;
                if ($val > 0) {
                    return $val;
                }
            }
        }

        try {
            $fromContext = $this->partnerContext->partnerId();
            if ($fromContext) {
                return (int) $fromContext;
            }
        } catch (Throwable) {
            // PartnerContext может быть недоступен в контексте воркера — игнорируем.
        }

        return null;
    }

    private function extractLogIdFromHeaders(Email $message): ?int
    {
        $headers = $message->getHeaders();
        if (! $headers->has(self::HEADER_LOG_ID)) {
            return null;
        }
        $raw = trim((string) $headers->get(self::HEADER_LOG_ID)?->getBodyAsString());
        if ($raw === '' || ! ctype_digit($raw)) {
            return null;
        }
        return (int) $raw;
    }

    /**
     * Извлекаем mailable_class из event.data, если событие порождено Mailable
     * (Illuminate ставит туда ['__laravel_mailable' => Class::class] или подобное).
     */
    private function extractMailableClass(MessageSending|MessageSent $event): ?string
    {
        $data = (array) ($event->data ?? []);
        foreach (['__laravel_mailable', 'mailable_class', 'mailable'] as $k) {
            if (isset($data[$k]) && is_string($data[$k]) && $data[$k] !== '') {
                return $data[$k];
            }
        }
        return null;
    }

    /**
     * Универсальное извлечение по «dot»-пути из MessageSending/MessageSent::$data.
     */
    private function extractEventString(MessageSending|MessageSent $event, string $key): ?string
    {
        $data = (array) ($event->data ?? []);
        if (! str_contains($key, '.')) {
            $val = $data[$key] ?? null;
            return $this->stringifyOrNull($val);
        }

        $parts = explode('.', $key);
        $cur   = $data;
        foreach ($parts as $p) {
            if (is_array($cur) && array_key_exists($p, $cur)) {
                $cur = $cur[$p];
                continue;
            }
            return null;
        }
        return $this->stringifyOrNull($cur);
    }

    private function stringifyOrNull(mixed $val): ?string
    {
        if ($val === null || $val === '') {
            return null;
        }
        if (is_scalar($val)) {
            return (string) $val;
        }
        return null;
    }

    /**
     * Текущий job_id из БД-очереди, если письмо отправляется в рамках обработки джоба.
     * Возвращает null в синхронном (web/CLI) контексте.
     */
    private function extractDatabaseQueueJobId(): ?int
    {
        try {
            // Если контейнер не разрешает Job — это не очередь.
            if (! app()->bound(\Illuminate\Contracts\Queue\Job::class)) {
                return null;
            }
            /** @var \Illuminate\Contracts\Queue\Job|null $job */
            $job = app(\Illuminate\Contracts\Queue\Job::class);
            if (! $job) {
                return null;
            }
            $jobId = $job->getJobId();
            if ($jobId === null || $jobId === '' || ! ctype_digit((string) $jobId)) {
                return null;
            }
            return (int) $jobId;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  iterable<int, Address>|null  $addresses
     * @return array<int, array{address: string, name: string|null}>|null
     */
    private function addressesToArray(?iterable $addresses): ?array
    {
        if ($addresses === null) {
            return null;
        }
        $out = [];
        foreach ($addresses as $a) {
            if (! $a instanceof Address) {
                continue;
            }
            $out[] = [
                'address' => $a->getAddress(),
                'name'    => $a->getName() !== '' ? $a->getName() : null,
            ];
        }
        return $out !== [] ? $out : null;
    }

    /**
     * @param  iterable<int, Address>|null  $addresses
     */
    private function summarizeAddresses(?iterable $addresses, int $maxLen): ?string
    {
        if ($addresses === null) {
            return null;
        }
        $parts = [];
        foreach ($addresses as $a) {
            if (! $a instanceof Address) {
                continue;
            }
            $name = $a->getName();
            $parts[] = $name !== '' ? sprintf('%s <%s>', $name, $a->getAddress()) : $a->getAddress();
        }
        if ($parts === []) {
            return null;
        }
        return $this->trimToLength(implode(', ', $parts), $maxLen);
    }

    /**
     * @param  iterable<int, Address>|null  $addresses
     */
    private function firstAddress(?iterable $addresses): ?Address
    {
        if ($addresses === null) {
            return null;
        }
        foreach ($addresses as $a) {
            if ($a instanceof Address) {
                return $a;
            }
        }
        return null;
    }

    /**
     * Список вложений в виде [['filename' => string, 'content_type' => string|null]].
     *
     * @return array<int, array{filename: string|null, content_type: string|null}>|null
     */
    private function collectAttachmentsSummary(Email $message): ?array
    {
        $atts = $message->getAttachments();
        if ($atts === []) {
            return null;
        }
        $out = [];
        foreach ($atts as $part) {
            $filename = method_exists($part, 'getFilename') ? $part->getFilename() : null;
            $contentType = null;
            try {
                if (method_exists($part, 'getContentType')) {
                    $contentType = (string) $part->getContentType();
                } elseif (method_exists($part, 'getMediaType') && method_exists($part, 'getMediaSubtype')) {
                    $contentType = $part->getMediaType().'/'.$part->getMediaSubtype();
                }
            } catch (Throwable) {
                $contentType = null;
            }
            $out[] = [
                'filename'     => $filename ?: null,
                'content_type' => $contentType ?: null,
            ];
        }
        return $out !== [] ? $out : null;
    }

    private function trimToLength(string $value, int $max): string
    {
        if ($max <= 0) {
            return '';
        }
        if (mb_strlen($value) <= $max) {
            return $value;
        }
        return mb_substr($value, 0, $max);
    }
}
