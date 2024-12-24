<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Traits\Filterable;


class User extends Authenticatable

{
    use HasApiTokens, HasFactory, Notifiable;
    use Filterable;
    use SoftDeletes;


    protected $table = 'users'; //явное указание к какой таблице в БД привязана модель
    protected $guarded = []; //разрешение на изменение данных в таблице
    protected $dates = ['deleted_at'];


    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */



    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
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

    public function tagValues()
    {
        return $this->hasMany(UserTagValue::class, 'user_id');
    }
}

