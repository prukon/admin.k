<?php

namespace App\Models;

use App\Services\PartnerContext;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ParentProfile extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'parents';

    protected $guarded = [];

    protected $appends = ['full_name'];

    protected $casts = [
        'partner_id' => 'int',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    /**
     * Ученики (users.role = user), привязанные к этому родителю.
     */
    public function students(): HasMany
    {
        return $this->hasMany(User::class, 'parent_id');
    }

    public function getFullNameAttribute(): string
    {
        return trim(collect([
            $this->lastname,
            $this->firstname,
            $this->middlename,
        ])->filter()->implode(' '));
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
