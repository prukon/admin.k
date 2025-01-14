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


class User extends Authenticatable

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
}

