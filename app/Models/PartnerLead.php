<?php

namespace App\Models;

use App\Enums\PartnerLeadStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PartnerLead extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'partner_leads';

    protected $guarded = [];

    protected $casts = [
        'status' => PartnerLeadStatus::class,
    ];
}
