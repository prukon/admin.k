<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    use HasFactory;

    protected $table = 'roles';

    protected $guarded = [];

    protected $casts = [
        'is_sistem' => 'boolean',
        'order_by' => 'integer',
        'is_visible' => 'boolean',

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
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            Permission::class,
            'permission_role',
            'role_id',
            'permission_id'
        )->withTimestamps();
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }


    public function scopeVisibleForPartner($query, $partnerId)
    {
        return $query->where('is_visible', true)
            ->where(function ($q) use ($partnerId) {
                $q->whereNull('partner_id')
                    ->orWhere('partner_id', $partnerId);
            });
    }

    public function scopeForDisplay2($query, $partnerId, $isSuperadmin = false)
    {
        return $query->with('permissions')
            ->where(function ($q) use ($partnerId) {
                $q->where('is_sistem', 1) // системные роли
                ->orWhere(function ($q2) use ($partnerId) {
                    $q2->where('is_sistem', 0)
                        ->where('partner_id', $partnerId);
                });
            })
            ->when(!$isSuperadmin, function ($q) {
                $q->where('is_visible', true);
            });
    }

    public function scopeForDisplay($query, int $partnerId,  $isSuperadmin = false)
    {
        return $query->with('permissions')
            // Только роли этого партнёра
            ->where('partner_id', $partnerId)
            // Если не суперадмин — ещё и фильтрация по видимости
            ->when(! $isSuperadmin, function ( $q) {
                $q->where('is_visible', true);
            });
    }
 
    public function scopeForDisplay3( $query,  $isSuperadmin = false)
    {
        return $query->with('permissions')
            // если не суперадмин, показываем только видимые
            ->when(! $isSuperadmin, function ( $q) {
                $q->where('is_visible', true);
            })
            // упорядочим по полю order_by (если нужно)
            ->orderBy('order_by');
    }

    public function partners(): BelongsToMany
    {
        return $this->belongsToMany(
            Partner::class,
            'partner_role',
            'role_id',
            'partner_id'
        )->withTimestamps();
    }

    public function scopeForPartner(Builder $query, int $partnerId, bool $isSupervisor, bool $isSuperadmin): Builder
    {
        return $query->whereHas('partners', function (Builder $q) use ($partnerId) {
            $q->where('partners.id', $partnerId);
        })
            // скрытые роли (is_visible = 0) видит только супервайзер или суперадмин
            ->when(! ($isSupervisor || $isSuperadmin), function (Builder $q) {
                $q->where('is_visible', true);
            })
            ->orderBy('sort_order');
    }

    public function permissionsForPartner(int $partnerId)
    {
        return $this->belongsToMany(Permission::class, 'permission_role')
            ->withPivot('partner_id')
            ->withTimestamps()
            ->wherePivot('partner_id', $partnerId);
    }

    /**
     * Привязать право к роли в контексте партнёра.
     */
    public function givePermissionToForPartner($permissionId, int $partnerId)
    {
        $this->permissionsForPartner($partnerId)
            ->attach($permissionId, ['partner_id' => $partnerId]);
    }

    /**
     * Отвязать все права в контексте партнёра.
     */
    public function revokeAllPermissionsForPartner(int $partnerId)
    {
        \DB::table('permission_role')
            ->where('role_id', $this->id)
            ->where('partner_id', $partnerId)
            ->delete();
    }

}
