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
        'signed_at'       => 'datetime',
        'fill_expires_at' => 'datetime',
        'filled_data'     => 'array',
    ];

    public const CREATION_MODE_PDF      = 'pdf';
    public const CREATION_MODE_TEMPLATE = 'template';

    public const FILL_TTL_DAYS = 7;

    /** Срок, в течение которого родитель может исправить данные после формирования PDF. */
    public const CLIENT_EDIT_FILLED_DATA_TTL_DAYS = 30;

    // Статусы
    public const STATUS_DRAFT   = 'draft';
    public const STATUS_AWAITING_CLIENT_FILL = 'awaiting_client_fill';
    public const STATUS_GENERATING_PDF = 'generating_pdf';
    public const STATUS_SENT    = 'sent';
    public const STATUS_OPENED  = 'opened';
    public const STATUS_SIGNED  = 'signed';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_REVOKED = 'revoked';
    public const STATUS_FAILED  = 'failed';

    public static array $STATUS_RU = [
        self::STATUS_DRAFT   => 'Черновик',
        self::STATUS_AWAITING_CLIENT_FILL => 'Ожидает заполнения клиентом',
        self::STATUS_GENERATING_PDF       => 'Формируется PDF',
        self::STATUS_SENT    => 'Отправлено',
        self::STATUS_OPENED  => 'Открыто',
        self::STATUS_SIGNED  => 'Подписано',
        self::STATUS_EXPIRED => 'Истёк срок',
        self::STATUS_REVOKED => 'Отозвано',
        self::STATUS_FAILED  => 'Ошибка',
    ];

    public static array $STATUS_BADGE = [
        self::STATUS_DRAFT   => 'bg-secondary',
        self::STATUS_AWAITING_CLIENT_FILL => 'bg-primary',
        self::STATUS_GENERATING_PDF       => 'bg-info text-dark',
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

    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(ContractTemplateVersion::class, 'contract_template_version_id');
    }

    public function isPdfMode(): bool
    {
        return ($this->creation_mode ?? self::CREATION_MODE_PDF) === self::CREATION_MODE_PDF;
    }

    public function isTemplateMode(): bool
    {
        return $this->creation_mode === self::CREATION_MODE_TEMPLATE;
    }

    public function canAdminSendSms(): bool
    {
        return $this->isPdfMode() && $this->status === self::STATUS_DRAFT;
    }

    public function canRevokeWithRefund(): bool
    {
        return $this->isTemplateMode()
            && $this->status === self::STATUS_AWAITING_CLIENT_FILL;
    }

    public function isFillExpired(): bool
    {
        return $this->fill_expires_at !== null && $this->fill_expires_at->isPast();
    }

    public function isGeneratingPdf(): bool
    {
        return $this->isTemplateMode()
            && $this->status === self::STATUS_GENERATING_PDF
            && !$this->isFillExpired();
    }

    public function canClientFill(): bool
    {
        return $this->isTemplateMode()
            && $this->status === self::STATUS_AWAITING_CLIENT_FILL
            && !$this->isFillExpired();
    }

    /**
     * @return array<string, string>
     */
    public function clientFormFieldValues(): array
    {
        $data = is_array($this->filled_data) ? $this->filled_data : [];
        $values = [];

        foreach ($data as $key => $value) {
            if (!is_string($key) || str_starts_with($key, '_')) {
                continue;
            }
            $values[$key] = trim((string) $value);
        }

        return $values;
    }

    public function pdfGenerationError(): ?string
    {
        $data = is_array($this->filled_data) ? $this->filled_data : [];
        $message = trim((string) ($data['_generation_error'] ?? ''));

        return $message !== '' ? $message : null;
    }

    /**
     * Удаляет сообщение о неудачной фоновой генерации PDF из filled_data.
     */
    public function clearPdfGenerationError(): bool
    {
        $data = is_array($this->filled_data) ? $this->filled_data : [];
        if (!array_key_exists('_generation_error', $data)) {
            return false;
        }

        unset($data['_generation_error']);
        $this->filled_data = $data;
        $this->save();

        return true;
    }

    public function canClientSign(): bool
    {
        return $this->isTemplateMode()
            && $this->status === self::STATUS_DRAFT
            && !empty($this->source_pdf_path)
            && !$this->isGeneratingPdf();
    }

    public function isClientEditExpired(): bool
    {
        if ($this->created_at === null) {
            return true;
        }

        return $this->created_at->copy()->addDays(self::CLIENT_EDIT_FILLED_DATA_TTL_DAYS)->isPast();
    }

    public function canClientEditFilledData(): bool
    {
        return $this->isTemplateMode()
            && $this->status === self::STATUS_DRAFT
            && !empty($this->source_pdf_path)
            && !$this->isGeneratingPdf()
            && empty($this->provider_doc_id)
            && !$this->isClientEditExpired();
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
