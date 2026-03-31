<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PartnerPayment extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'partner_payments';
    // protected $primaryKey = 'id'; // Можно опустить, так как 'id' — значение по умолчанию

    protected $fillable = [
        'payment_id',
        'partner_id',
        'user_id',
        'amount',
        'payment_date',
        'payment_method',
        'payment_status',
        'description',
    ];

//    protected $dates = ['payment_date', 'deleted_at'];


    protected $casts = [
        'payment_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];


    // Связь с моделью Partner
//    public function partner()
//    {
//        return $this->belongsTo(Partner::class, 'partner_id');
//    }

    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }


    // Связь с моделью User
    public function user()
    {
        return $this->belongsTo(User::class);
    }



    // Связь с моделью PartnerAccess
    public function accesses()
    {
        return $this->hasMany(PartnerAccess::class, 'payment_id', 'payment_id');
    }
}
