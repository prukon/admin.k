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

    protected static function booted()
    {
        static::saving(function ($m) {
            \Log::debug('PaymentSystem@saving', [
                'id'      => $m->id,
                'dirty'   => $m->getDirty(),   // какие поля реально помечены как изменённые
                'exists'  => $m->exists,
            ]);
        });

        static::saved(function ($m) {
            \Log::debug('PaymentSystem@saved', [
                'id'      => $m->id,
                'dirty'   => $m->getDirty(),   // после save обычно пусто
            ]);
        });

        static::updating(function ($m) {
            \Log::debug('PaymentSystem@updating', ['id'=>$m->id, 'dirty'=>$m->getDirty()]);
        });

        static::updated(function ($m) {
            \Log::debug('PaymentSystem@updated', ['id'=>$m->id]);
        });
    }


    // Сохранение settings в зашифрованном виде
    public function setSettingsAttribute2($value)
    {
        // $value — это массив
        $json = json_encode($value);
        $this->attributes['settings'] = Crypt::encryptString($json);
    }

    public function setSettingsAttribute3($value)
    {
        $json = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value;
        $this->attributes['settings'] = Crypt::encryptString($json);
    }

    public function setSettingsAttribute($value)
    {
        $json = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string) $value;
        $enc  = \Illuminate\Support\Facades\Crypt::encryptString($json);

        \Log::debug('PaymentSystem@setSettingsAttribute', [
            'len_json' => strlen($json),
            'len_enc'  => strlen($enc),
            'sample'   => substr($enc, 0, 32), // хвост не логируем
        ]);

        $this->attributes['settings'] = $enc;
    }

    // Геттер флага подключения
    public function getSettingsAttribute2($value)
    {
        if (!$value) {
            return null;
        }
        $decrypted = Crypt::decryptString($value);
        return json_decode($decrypted, true);
    }

    public function getSettingsAttribute($value)
    {
        if (empty($value)) return [];
        try {
            $json = Crypt::decryptString($value);
            $arr = json_decode($json, true);
            if (is_array($arr)) return $arr;
        } catch (DecryptException $e) {
            $arr = json_decode($value, true); // вдруг лежит чистый JSON
            if (is_array($arr)) return $arr;
            \Log::error('PaymentSystem settings decrypt failed', ['id' => $this->id]);
        }
        return [];
    }


    public function partner()
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    public function getIsConnectedAttribute2()
    {
        if (!$this->settings || !is_array($this->settings)) {
            return false;
        }

        return match($this->name){
        'robokassa' => !empty($this->settings['merchant_login'])
    && !empty($this->settings['password1'])
    && !empty($this->settings['password2']),
        'tbank' => !empty($this->settings['tbank_account_id'])
    && !empty($this->settings['tbank_key']),
        default => false,
    };
}

    public function getIsConnectedAttribute()
    {
        $s = $this->settings;
        return !empty($s['merchant_login']) && !empty($s['password1']);
    }
}
