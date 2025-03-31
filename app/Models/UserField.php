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
        'permissions_id' // новый вариант (JSON)

    ];

    protected $casts = [
        'permissions' => 'array', // Преобразование JSON в массив
        'permissions_id' => 'array', // Так Laravel будет автоматически

    ];

    public function userFieldValues()
    {
        return $this->hasMany(UserFieldValue::class, 'field_id');
    }

    public function values()
    {
        return $this->hasMany(UserFieldValue::class, 'field_id');
    }

    public function getRolesAttribute()
    {
        if (! $this->permissions_id) {
            return collect();
        }

        return Role::whereIn('id', $this->permissions_id)->get();
    }
}
