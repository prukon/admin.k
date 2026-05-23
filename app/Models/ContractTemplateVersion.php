<?php

namespace App\Models;

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

    public function defaultEmailSubject(): string
    {
        return $this->email_subject ?: 'Договор: требуется заполнение и подписание';
    }

    public function defaultEmailBodyHtml(): string
    {
        if ($this->email_body_html) {
            return (string) $this->email_body_html;
        }

        return '<p>Здравствуйте!</p>'
            . '<p>В личном кабинете доступен договор для заполнения и подписания.</p>'
            . '<p>Перейдите в раздел «Мои документы» в учётной записи.</p>';
    }
}
