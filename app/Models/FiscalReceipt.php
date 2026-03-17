<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FiscalReceipt extends Model
{
    use HasFactory;

    protected $table = 'fiscal_receipts';

    protected $guarded = []; 

    protected $casts = [
        'amount' => 'decimal:2',
        'queued_at' => 'datetime',
        'processed_at' => 'datetime',
        'failed_at' => 'datetime',
        'receipt_datetime' => 'datetime',
    ];

    public const PROVIDER_CLOUDKASSIR = 'cloudkassir';

    public const TYPE_INCOME = 'income';
    public const TYPE_INCOME_RETURN = 'income_return';

    public const STATUS_PENDING = 'pending';
    public const STATUS_QUEUED = 'queued';
    public const STATUS_PROCESSED = 'processed';
    public const STATUS_ERROR = 'error';

    public function partner()
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    public function paymentIntent()
    {
        return $this->belongsTo(PaymentIntent::class, 'payment_intent_id');
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    public function payable()
    {
        return $this->belongsTo(Payable::class, 'payable_id');
    }

    public function scopeIncome($query)
    {
        return $query->where('type', self::TYPE_INCOME);
    }

    public function scopeIncomeReturn($query)
    {
        return $query->where('type', self::TYPE_INCOME_RETURN);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeQueued($query)
    {
        return $query->where('status', self::STATUS_QUEUED);
    }

    public function scopeProcessed($query)
    {
        return $query->where('status', self::STATUS_PROCESSED);
    }

    public function scopeErrored($query)
    {
        return $query->where('status', self::STATUS_ERROR);
    }
}   