<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Partner extends Model
{
    use SoftDeletes;

    protected $table = 'partners';

    protected $primaryKey = 'id';

    /**
     * Атрибуты, которые можно массово назначать.
     */
    protected $fillable = [
        'business_type',
        'title',
        'tax_id',
        'registration_number',
        'address',
        'phone',
        'email',
        'website',
    ];

    /**
     * Типы кастинга атрибутов.
     */
    protected $casts = [
        'business_type' => 'string',
    ];

    /**
     * Даты, которые должны быть обработаны как Carbon экземпляры.
     */
    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * Отношения с другими моделями.
     */

    // Пример отношения с платежами партнера
    public function payments()
    {
        return $this->hasMany(PartnerPayment::class, 'partner_id', 'id');
    }

    // Пример отношения с доступами партнера через платежи
    public function accesses()
    {
        return $this->hasManyThrough(
            PartnerAccess::class,
            PartnerPayment::class,
            'partner_id', // Foreign key on PartnerPayment table...
            'payment_id', // Foreign key on PartnerAccess table...
            'id', // Local key on Partner table...
            'payment_id' // Local key on PartnerPayment table...
        );
    }
}
