<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Contracts\Encryption\DecryptException;

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
        'test_mode' => 'boolean',
    ];

    /**
     * Мутатор: шифруем массив настроек
     */
    public function setSettingsAttribute($value)
    {
        if (empty($value)) {
            $this->attributes['settings'] = null;
            return;
        }

        $json = is_array($value)
            ? json_encode($value, JSON_UNESCAPED_UNICODE)
            : (string)$value;

        $this->attributes['settings'] = Crypt::encryptString($json);
    }

    /**
     * Аксессор: всегда возвращаем массив
     */
    public function getSettingsAttribute($value)
    {
        if (empty($value)) {
            return [];
        }

        // пробуем расшифровать
        try {
            $json = Crypt::decryptString($value);
            $arr = json_decode($json, true);
            if (is_array($arr)) {
                return $arr;
            }
        } catch (DecryptException $e) {
            // возможно, там хранится чистый JSON (исторические данные)
            $arr = json_decode($value, true);
            if (is_array($arr)) {
                return $arr;
            }

            \Log::warning('PaymentSystem settings decrypt failed', [
                'id'         => $this->id,
                'name'       => $this->name,
                'partner_id' => $this->partner_id,
                'msg'        => $e->getMessage(),
            ]);
        } catch (\Throwable $e) {
            \Log::warning('PaymentSystem settings decrypt throwable', [
                'id'         => $this->id,
                'name'       => $this->name,
                'partner_id' => $this->partner_id,
                'msg'        => $e->getMessage(),
            ]);
        }

        // fallback
        return [];
    }

    /**
     * Признак, что система подключена (например для UI).
     */
    public function getIsConnectedAttribute()
    {
        $s = $this->settings;
        return !empty($s['merchant_login']) && !empty($s['password1']);
    }
}
