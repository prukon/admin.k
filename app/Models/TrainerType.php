<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\PartnerContext;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrainerType extends Model
{
    use HasFactory;

    public const SYSTEM_DEFAULT_NAME = 'Главный тренер';

    protected $table = 'trainer_types';

    protected $guarded = [];

    protected $casts = [
        'partner_id' => 'int',
        'sort_order' => 'int',
        'is_enabled' => 'bool',
        'is_system' => 'bool',
        'rate_per_training_cents' => 'integer',
        'base_premium_cents' => 'integer',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    public function trainerProfiles(): HasMany
    {
        return $this->hasMany(TrainerProfile::class, 'trainer_type_id');
    }

    /**
     * Route model binding: только типы текущего партнёра (404 для чужих).
     */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $partnerId = app(PartnerContext::class)->partnerId();
        $query = static::query()->whereKey($value);

        if ($partnerId) {
            $query->where('partner_id', (int) $partnerId);
        }

        return $query->firstOrFail();
    }
}
