<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Services\PartnerContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrainerProfile extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'trainer_profiles';

    protected $guarded = [];

    protected $casts = [
        'partner_id' => 'int',
        'user_id' => 'int',
        'is_enabled' => 'bool',
        'sort_order' => 'int',
        'default_base_salary' => 'decimal:2',
        'default_rate_per_training' => 'decimal:2',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(
            Team::class,
            'team_trainer',
            'trainer_profile_id',
            'team_id',
        )->withTimestamps()->withPivot('partner_id');
    }

    /**
     * Route model binding: только профили текущего партнёра (404 для чужих).
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
