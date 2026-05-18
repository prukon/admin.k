<?php

namespace App\Models;

use App\Enums\SchoolLeadStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SchoolLead extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'school_leads';

    protected $guarded = [];

    protected $casts = [
        'status' => SchoolLeadStatus::class,
        'consent_accepted_at' => 'datetime',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function partnerWidget(): BelongsTo
    {
        return $this->belongsTo(PartnerWidget::class);
    }
}
