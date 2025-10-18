<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TinkoffCommissionRule extends Model
{
    protected $fillable = ['partner_id','method','percent','min_fixed','is_enabled'];
}
