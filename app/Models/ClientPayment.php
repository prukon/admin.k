<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientPayment extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Имя таблицы в базе данных.
     *
     * @var string
     */
    protected $table = 'client_payments';

    /**
     * Атрибуты, доступные для массового заполнения.
     *
     * @var array
     */
//    protected $fillable = [
//        'client_id',
//        'user_id',
//        'payment_id',
//        'amount',
//        'payment_date',
//        'payment_method',
//        'payment_status',
//    ];
    protected $guarded = []; //разрешение на изменение данных в таблице


    /**
     * Кастинг атрибутов модели.
     *
     * @var array
     */
    protected $casts = [
        'payment_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Отношение с моделью Client.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Отношение с моделью User.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
