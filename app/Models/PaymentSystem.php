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

    public function getSettingsAttribute($value)
    {
        if (empty($value)) return [];

        try {
            $json = Crypt::decryptString($value);
            $arr  = json_decode($json, true);
            if (is_array($arr)) return $arr;
        } catch (DecryptException $e) {
            // fallback: вдруг лежит чистый JSON
            $arr = json_decode($value, true);
            if (is_array($arr)) return $arr;

            \Log::warning('PaymentSystem settings decrypt failed', [
                'id' => $this->id,
                'name' => $this->name,
                'partner_id' => $this->partner_id,
            ]);
        }
        return [];
    }

    public function partner()
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }


    public function getIsConnectedAttribute()
    {
        $s = $this->settings;
        return !empty($s['merchant_login']) && !empty($s['password1']);
    }
}
