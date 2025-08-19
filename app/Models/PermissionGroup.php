<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PermissionGroup extends Model
{
    protected $fillable = [
        'slug', 'name', 'description', 'is_visible', 'sort_order'
    ];

    public function permissions()
    {
        return $this->hasMany(Permission::class, 'permission_group_id');
    }
}
