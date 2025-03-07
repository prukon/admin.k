<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserField extends Model
{
    use HasFactory;

    protected $table = 'user_fields';

    protected $fillable = [
        'name',
        'slug',
        'field_type',
        'permissions', // Добавьте это
    ];

    protected $casts = [
        'permissions' => 'array', // Преобразование JSON в массив
    ];

    public function userFieldValues()
    {
        return $this->hasMany(UserFieldValue::class, 'field_id');
    }

    public function values()
    {
        return $this->hasMany(UserFieldValue::class, 'field_id');
    }
}
