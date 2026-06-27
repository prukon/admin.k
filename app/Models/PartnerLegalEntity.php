<?php

namespace App\Models;

use App\Enums\PartnerLegalEntityBusinessType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PartnerLegalEntity extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'partner_legal_entities';

    protected $guarded = [];

    protected $casts = [
        'partner_id' => 'int',
        'business_type' => PartnerLegalEntityBusinessType::class,
        'ceo' => 'array',
        'is_default' => 'bool',
        'is_enabled' => 'bool',
        'taxation_system' => 'int',
        'vat' => 'int',
        'bank_details_version' => 'int',
        'registered_at' => 'datetime',
        'bank_details_last_updated_at' => 'datetime',
        'registration_verified_at' => 'datetime',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class, 'legal_entity_id');
    }

    /**
     * Зарегистрировано в sm-register (есть ShopCode).
     */
    public function getIsRegisteredAttribute(): bool
    {
        return trim((string) ($this->tinkoff_shop_code ?? '')) !== '';
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }

    public function scopeRegistered(Builder $query): Builder
    {
        return $query->whereNotNull('tinkoff_shop_code')
            ->where('tinkoff_shop_code', '!=', '');
    }

    public function scopeForPartner(Builder $query, int $partnerId): Builder
    {
        return $query->where('partner_id', $partnerId);
    }

    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }
}
