<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use HasFactory, SoftDeletes;


    /**
     * Атрибуты, доступные для массового присвоения.
     *
     * @var array
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
     * Атрибуты, которые должны быть скрыты для массивов.
     *
     * @var array
     */
    protected $hidden = [
        // Добавьте здесь поля, которые не должны отображаться, например, конфиденциальные данные
    ];

    /**
     * Типы кастинга для атрибутов модели.
     *
     * @var array
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}