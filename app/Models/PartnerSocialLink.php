<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PartnerSocialLink extends Model
{
    use HasFactory;

    protected $table = 'partner_social_links';

    protected $guarded = [];

    protected $casts = [
        'is_enabled' => 'boolean',
        'sort' => 'integer',
    ];

    public function partner()
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    public function socialNetwork()
    {
        return $this->belongsTo(SocialNetwork::class, 'social_network_id');
    }
}

