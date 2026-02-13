<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class TinkoffCommissionRule extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function partner()
    {
        return $this->belongsTo(\App\Models\Partner::class, 'partner_id');
    }
}
