<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerWalletInvoice extends Model
{
    protected $fillable = [
        'partner_id',
        'user_id',
        'legal_entity_id',
        'number',
        'amount_cents',
        'currency',
        'item_name',
        'payment_purpose',
        'vat_note',
        'issued_on',
        'buyer',
        'seller',
    ];

    protected $casts = [
        'amount_cents' => 'integer',
        'buyer' => 'array',
        'seller' => 'array',
        'issued_on' => 'date',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(PartnerLegalEntity::class, 'legal_entity_id');
    }
}
