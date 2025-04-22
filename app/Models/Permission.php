<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany; // <- вот это


class Permission extends Model
{
    use HasFactory;

    protected $table = 'permissions';

    protected $fillable = [
        'name',
        'description',
        'sort_order'
    ];

    protected $casts = [
        'is_visible' => 'boolean',
    ];

    // Связь многие-ко-многим с Role
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'permission_role',
            'permission_id',
            'role_id'
        )->withTimestamps();
    }
    // app/Models/Permission.php

    public function scopeVisibleForRoles($query, $roleIds)
    {
        return $query->where('is_visible', true)
            ->whereHas('roles', function ($q) use ($roleIds) {
                $q->whereIn('roles.id', $roleIds);
            })
            ->with(['roles' => function ($q) use ($roleIds) {
                $q->whereIn('roles.id', $roleIds);
            }]);
    }

}
