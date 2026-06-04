<?php

namespace App\Models;

use App\Services\Contracts\ContractTemplateEmailDefaults;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContractTemplateVersion extends Model
{
    protected $guarded = [];

    protected $casts = [
        'fields_schema' => 'array',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(ContractTemplate::class, 'contract_template_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class, 'contract_template_version_id');
    }

    public function resolvedEmailSubject(): string
    {
        $stored = trim((string) ($this->email_subject ?? ''));

        return $stored !== '' ? $stored : ContractTemplateEmailDefaults::subject();
    }

    public function resolvedEmailBodyHtml(): string
    {
        $stored = trim((string) ($this->email_body_html ?? ''));

        return $stored !== '' ? $stored : ContractTemplateEmailDefaults::bodyHtml();
    }

    /** @deprecated Use resolvedEmailSubject() — kept for backward compatibility in callers */
    public function defaultEmailSubject(): string
    {
        return $this->resolvedEmailSubject();
    }

    /** @deprecated Use resolvedEmailBodyHtml() */
    public function defaultEmailBodyHtml(): string
    {
        return $this->resolvedEmailBodyHtml();
    }
}
