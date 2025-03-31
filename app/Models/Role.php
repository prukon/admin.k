<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    protected $table = 'roles';

    protected $fillable = [
        'name',
        'label',
    ];

    protected static function booted()
    {
        static::deleting(function ($role) {
            // При удалении данной роли всем пользователям устанавливаем role_id = 2
            // (либо роль, которая у вас обозначена «по умолчанию»).
            User::where('role_id', $role->id)->update(['role_id' => 2]);
        });
    }

    // Связь многие-ко-многим с Permission
    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'permission_role');
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
