<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Traits\Filterable;
use App\Notifications\ResetPasswordNotification;
//use App\Models\Role;


class   User extends Authenticatable

{
    use HasApiTokens, HasFactory, Notifiable;
    use Filterable;
    use SoftDeletes;


    protected $table = 'users'; //явное указание к какой таблице в БД привязана модель
    protected $guarded = []; //разрешение на изменение данных в таблице
    protected $dates = ['deleted_at'];



    protected $hidden = [
        'password',
        'remember_token',
    ];


    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];


    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function fieldValues()
    {
        return $this->hasMany(UserFieldValue::class, 'user_id');
    }

    public function fields()
    {
        return $this->belongsToMany(UserField::class, 'user_field_values', 'user_id', 'field_id')
            ->withPivot('value')
            ->withTimestamps();
    }

//    Восстановление пароля через емаил
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new ResetPasswordNotification($token));
    }

//Связь многие-ко-многим с партнёрами.
    public function partners()
    {
        return $this->belongsToMany(Partner::class, 'partner_user');
    }

// Пример метода для удобного добавления партнёра к пользователю.
    public function attachPartner($partnerId)
    {
        return $this->partners()->attach($partnerId);
    }

//     Пример метода для синхронизации партнёров (заменяет текущие связи новыми).

    public function syncPartners(array $partnerIds)
    {
        return $this->partners()->sync($partnerIds);
    }
    public function partner()
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }


    // Если у пользователя может быть несколько ролей (через pivot user_role):
//    public function roles(): BelongsToMany
//    {
//        return $this->belongsToMany(Role::class, 'user_role');
//    }

    /**
     * Проверяем, есть ли у пользователя право $permissionName
     */
//    public function hasPermission(string $permissionName): bool
//    {
//        // Перебираем все роли, если у роли есть нужное право, возвращаем true
//        foreach ($this->roles as $role) {
//            if ($role->permissions->contains('name', $permissionName)) {
//                return true;
//            }
//        }
//
//        return false;
//    }

//    public function role()
//    {
//        return $this->belongsTo(Role::class, 'role_id');
//    }



//    public function role(): BelongsTo
//    {
//        // Если таблица roles имеет PK = id,
//        // а в таблице users есть FK = role_id
//        return $this->belongsTo(Role::class, 'role_id');
//    }

    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }



//    public function hasPermission(string $permissionName): bool
//    {
//        return $this->role
//            && $this->role->permissions->contains('name', $permissionName);
//    }

    public function hasPermission(string $permissionName): bool
    {
        // Если у пользователя нет связанной роли (null), то прав нет
        if (!$this->role) {
            return false;
        }

        // Убеждаемся, что модель Role подгрузила permissions (pivot: permission_role)
        // и проверяем наличие нужного permission
        return $this->role->permissions->contains('name', $permissionName);
    }

}

