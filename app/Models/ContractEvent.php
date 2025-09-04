<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractEvent extends Model
{
    protected $fillable = ['contract_id','type','payload_json'];

    public function contract(): BelongsTo {
        return $this->belongsTo(Contract::class);
    }
}
