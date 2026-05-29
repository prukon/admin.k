<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartnerWidget extends Model
{
    protected $table = 'partner_widgets';

    protected $guarded = [];

    protected $casts = [
        'is_active'         => 'boolean',
        'is_landing_active' => 'boolean',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function schoolLeads(): HasMany
    {
        return $this->hasMany(SchoolLead::class);
    }
}
