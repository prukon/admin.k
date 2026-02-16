<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SocialNetwork extends Model
{
    use HasFactory;

    protected $table = 'social_networks';

    protected $guarded = [];

    protected $casts = [
        'is_enabled' => 'boolean',
        'sort' => 'integer',
    ];

    public function partnerLinks()
    {
        return $this->hasMany(PartnerSocialLink::class, 'social_network_id');
    }
}

