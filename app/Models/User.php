<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Traits\Filterable;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

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
        'birthday' => 'date',  // преобразует в Carbon\Carbon
        'parent_id' => 'integer',
        'is_individual_traits' => 'boolean',
        'is_on_medical_register' => 'boolean',
        'is_with_disability' => 'boolean',

        //2FA
        'two_factor_enabled' => 'boolean',
        'two_factor_expires_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'has_used_school_schedule_trial' => 'boolean',
    ];

    protected $appends = ['full_name', 'parent_full_name'];


    public $timestamps = true;

    public function getBirthdayForFormAttribute(): ?string
    {
        return $this->birthday ?->format('Y-m-d');
    }

    public function team()
    {
        // если поле в таблице users называется team_id
        return $this->belongsTo(Team::class, 'team_id');
    }

    /**
     * Родитель ученика (справочник parents). Только для role = user.
     */
    public function parentProfile(): BelongsTo
    {
        return $this->belongsTo(ParentProfile::class, 'parent_id');
    }

    public function schoolLead()
    {
        return $this->hasOne(SchoolLead::class);
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
        if (!$this->email) {
            Log::info('Password reset skipped: user has no email', [
                'user_id' => $this->id,
            ]);
            return;
        }
        $this->notify(new ResetPasswordNotification($token));
    }

    /**
     * Mail routing for Laravel Notifications.
     * Если email отсутствует — mail-уведомления не отправляем (и не падаем).
     */
    public function routeNotificationForMail($notification = null): ?string
    {
        return $this->email ?: null;
    }

    public function partner()
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    public function lessonPackageAssignments()
    {
        return $this->hasMany(\App\Models\UserLessonPackage::class, 'user_id');
    }

    public function trainerProfile()
    {
        return $this->hasOne(TrainerProfile::class, 'user_id');
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
        // Склеиваем фамилию и имя с пробелом, убираем лишние
        return trim(collect([$this->lastname, $this->name])->filter()->implode(' '));
    }

    public function getParentFullNameAttribute(): string
    {
        if ($this->parent_id) {
            $profile = $this->relationLoaded('parentProfile')
                ? $this->parentProfile
                : $this->parentProfile()->first();

            if ($profile) {
                $fromProfile = trim($profile->full_name);
                if ($fromProfile !== '') {
                    return $fromProfile;
                }
            }
        }

        return '';
    }

    /**
     * Поля родителя для формы личного кабинета.
     *
     * @return array{
     *     parent_lastname: ?string,
     *     parent_firstname: ?string,
     *     parent_middlename: ?string
     * }
     */
    public function accountParentFormFields(): array
    {
        $this->loadMissing('parentProfile');
        $profile = $this->parentProfile;

        if ($profile) {
            return [
                'parent_lastname'   => $profile->lastname,
                'parent_firstname'  => $profile->firstname,
                'parent_middlename' => $profile->middlename,
            ];
        }

        return [
            'parent_lastname'   => null,
            'parent_firstname'  => null,
            'parent_middlename' => null,
        ];
    }

    /**
     * Поля родителя для форм админки (из таблицы parents).
     *
     * @return array{
     *     parent_id: ?int,
     *     parent_lastname: ?string,
     *     parent_firstname: ?string,
     *     parent_middlename: ?string
     * }
     */
    public function parentFormFields(): array
    {
        $profile = $this->relationLoaded('parentProfile')
            ? $this->parentProfile
            : ($this->parent_id ? $this->parentProfile()->first() : null);

        if ($profile) {
            return [
                'parent_id'         => (int) $profile->id,
                'parent_lastname'   => $profile->lastname,
                'parent_firstname'  => $profile->firstname,
                'parent_middlename' => $profile->middlename,
            ];
        }

        return [
            'parent_id'         => $this->parent_id ? (int) $this->parent_id : null,
            'parent_lastname'   => null,
            'parent_firstname'  => null,
            'parent_middlename' => null,
        ];
    }


    private function toE164(?string $v): ?string {
        if (!$v) return null;
        $d = preg_replace('/\D+/', '', $v);
        if (strlen($d) === 11 && $d[0] === '8') $d = '7'.substr($d,1);
        if (strlen($d) === 10) $d = '7'.$d;
        return $d ? '+'.$d : null;
    }

    public function setPhoneAttribute($v): void
    {
        $this->attributes['phone'] = $this->toE164($v);
    }

    public function setTwoFactorPhonePendingAttribute($v): void
    {
        $this->attributes['two_factor_phone_pending'] = $this->toE164($v);
    }

}

