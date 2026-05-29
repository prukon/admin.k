<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SportType extends Model
{
    use HasFactory;

    protected $table = 'sport_types';

    protected $guarded = [];

    protected $casts = [
        'partner_id' => 'int',
        'sort' => 'int',
        'is_enabled' => 'bool',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class, 'sport_type_id');
    }
}
