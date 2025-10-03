<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use App\Models\Traits\Filterable;


class Partner extends Model
{
    use SoftDeletes;
    use Notifiable;
    use Filterable;


    protected $table = 'partners';

    protected $primaryKey = 'id';

    /**
     * Атрибуты, которые можно массово назначать.
     */
    protected $guarded = []; //разрешение на изменение данных в таблице}


    /**
     * Типы кастинга атрибутов.
     */
    protected $casts = [
        'business_type' => 'string',
        'wallet_balance' => 'decimal:2',
        'ceo' => 'array',
        'is_enabled' => 'boolean',

        'is_registered'       => 'boolean',   // GENERATED column
        'registered_at'       => 'datetime',
        'bank_details_last_updated_at' => 'datetime',

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

    public function users()
    {
        return $this->belongsToMany(User::class, 'partner_user');
    }

    /**
     * Пример метода создания/обновления партнёра.
     * Можно вызывать, например, Partner::store($data).
     */
    public static function store(array $data)
    {
        return self::create($data);
    }

    /**
     * Пример метода для назначения (привязки) нескольких пользователей к партнёру.
     */
    public function attachUsers(array $userIds)
    {
        return $this->users()->attach($userIds);
    }

    /**
     * Пример метода для синхронизации пользователей (удалит «лишние» связи).
     */
    public function syncUsers(array $userIds)
    {
        return $this->users()->sync($userIds);
    }

    public function walletTransactions() {
        return $this->hasMany(PartnerWalletTransaction::class);
    }

    // На случай, если где-то ещё хочется использовать геттер без поля в БД
    public function getIsRegisteredAttribute($value)
    {
        // если есть GENERATED — $value уже придёт. Если нет — подстрахуемся логикой:
        return $value ?? !empty($this->tinkoff_partner_id);
    }

    // Удобный скоуп выборки только зарегистрированных
    public function scopeRegistered($q)
    {
        return $q->where('is_registered', 1);
    }

    public function scopeNotRegistered($q)
    {
        return $q->where('is_registered', 0);
    }

}
