<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contract extends Model
{
    protected $fillable = [
        'school_id','user_id','group_id','source_pdf_path','source_sha256',
        'provider','provider_doc_id','status','signed_pdf_path','signed_at'
    ];

    protected $casts = [
        'signed_at' => 'datetime',
    ];

    // Связи
    public function signRequests(): HasMany {
        return $this->hasMany(ContractSignRequest::class);
    }

    public function events(): HasMany {
        return $this->hasMany(ContractEvent::class);
    }

    // Статусы
    public const STATUS_DRAFT   = 'draft';
    public const STATUS_SENT    = 'sent';
    public const STATUS_OPENED  = 'opened';
    public const STATUS_SIGNED  = 'signed';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_REVOKED = 'revoked';
    public const STATUS_FAILED  = 'failed';
}
