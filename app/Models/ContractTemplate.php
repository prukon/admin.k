<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContractTemplate extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_archived' => 'boolean',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ContractTemplateVersion::class)->orderByDesc('version');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(ContractTemplateVersion::class, 'current_version_id');
    }

    public function scopeForPartner($query, int $partnerId)
    {
        return $query->where('partner_id', $partnerId);
    }

    public function scopeActive($query)
    {
        return $query->where('is_archived', false);
    }

    public function isUsable(): bool
    {
        return !$this->is_archived && $this->current_version_id !== null;
    }
}
