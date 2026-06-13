<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class District extends Model
{
    use HasFactory;

    protected $table = 'districts';

    protected $guarded = [];

    protected $casts = [
        'partner_id' => 'int',
        'is_enabled' => 'bool',
        'sort_order' => 'int',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class, 'district_id');
    }

    public function schoolLeads(): HasMany
    {
        return $this->hasMany(SchoolLead::class, 'district_id');
    }
}
