<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    use HasFactory;

    protected $table = 'permissions';

    protected $fillable = [
        'name',
        'description',
        'sort_order'
    ];

    // Связь многие-ко-многим с Role
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'permission_role');
    }
}
