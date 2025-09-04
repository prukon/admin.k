<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractSignRequest extends Model
{
    protected $fillable = [
        'contract_id','signer_name','signer_phone','ttl_hours',
        'provider_request_id','status','meta'
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function contract(): BelongsTo {
        return $this->belongsTo(Contract::class);
    }
}
