<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PartnerAccess extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'partner_accesses';
    // protected $primaryKey = 'id'; // Можно опустить, так как 'id' — значение по умолчанию

    protected $fillable = [
        'partner_payment_id',
        'start_date',
        'end_date',
        'is_active',
    ];

    protected $dates = ['start_date', 'end_date', 'deleted_at'];

    // Связь с моделью PartnerPayment
    public function payment()
    {
        return $this->belongsTo(PartnerPayment::class, 'payment_id', 'payment_id');
    }
}
