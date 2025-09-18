<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Traits\Filterable;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Support\Facades\DB;

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
        'birthday' => 'date',  // преобразует в Carbon\Carbon

        //2FA
        'two_factor_enabled' => 'boolean',
        'two_factor_expires_at' => 'datetime',
        'phone_verified_at' => 'datetime',
    ];

    protected $appends = ['full_name'];


    public $timestamps = true;

//    public function users()
//    {
//        // pivot: role_user (user_id, role_id)
//        return $this->belongsToMany(User::class, 'role_user', 'role_id', 'user_id');
//    }

    public function getBirthdayForFormAttribute(): ?string
    {
        return $this->birthday ?->format('Y-m-d');
    }

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

    //Восстановление пароля через емаил
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    //Связь многие-ко-многим с партнёрами.
    public function partners()
    {
        return $this->belongsToMany(Partner::class, 'partner_user');
    }

    //Пример метода для удобного добавления партнёра к пользователю.
    public function attachPartner($partnerId)
    {
        return $this->partners()->attach($partnerId);
    }

    //Пример метода для синхронизации партнёров (заменяет текущие связи новыми).
    public function syncPartners(array $partnerIds)
    {
        return $this->partners()->sync($partnerIds);
    }

    public function partner()
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    public function roles()
    {
        return $this->belongsToMany(
            Role::class,
            'role_user',   // имя pivot‑таблицы
            'user_id',     // FK User
            'role_id'      // FK Role
        )->withTimestamps();
    }

    public function hasRole(string $roleName): bool
    {
        return $this->role && $this->role->name === $roleName;
    }

    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function hasPermission(string $permissionName): bool
    {
        // 1) получаем текущего партнёра
        $partnerId = app('current_partner')->id;       // <<< CHANGED

        // 2) если у юзера нет роли — сразу false
        if (!$this->role) {
            return false;                             // <<< CHANGED
        }

        // 3) смотрим, есть ли у этой роли permission с нужным именем
        //    именно для данного партнёра
        return $this->role
            ->permissionsForPartner($partnerId)// <<< CHANGED
            ->where('name', $permissionName)
            ->exists();                        // <<< CHANGED
    }

//2FA
    public function generateTwoFactorCode()
    {
        $this->two_factor_code = random_int(100000, 999999);
        $this->two_factor_expires_at = now()->addMinutes(10);
        $this->save();
    }

    public function resetTwoFactorCode()
    {
        $this->two_factor_code = null;
        $this->two_factor_expires_at = null;
        $this->save();
    }

    public function getFullNameAttribute(): string
    {
        return trim(($this->lastname ?? '').' '.($this->name ?? ''));
    }


}

