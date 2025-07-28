<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Crypt;

class PaymentSystem extends Model
{
    use HasFactory;

    protected $table = 'payment_systems';

    protected $fillable = [
        'partner_id',
        'name',
        'settings',
        'test_mode',

    ];


    protected $casts = [
        'settings' => 'array',
        'test_mode' => 'boolean',
    ];

    protected $hidden = ['settings'];


    // Сохранение settings в зашифрованном виде
    public function setSettingsAttribute($value)
    {
        // $value — это массив
        $json = json_encode($value);
        $this->attributes['settings'] = Crypt::encryptString($json);
    }

    // Геттер флага подключения
    public function getSettingsAttribute($value)
    {
        if (!$value) {
            return null;
        }
        $decrypted = Crypt::decryptString($value);
        return json_decode($decrypted, true);
    }

    public function partner()
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    public function getIsConnectedAttribute(): bool
    {
        if (!$this->settings || !is_array($this->settings)) {
            return false;
        }

        return match ($this->name) {
        'robokassa' => !empty($this->settings['merchant_login'])
    && !empty($this->settings['password1'])
    && !empty($this->settings['password2']),
        'tbank' => !empty($this->settings['tbank_account_id'])
    && !empty($this->settings['tbank_key']),
        default => false,
    };
}

}
