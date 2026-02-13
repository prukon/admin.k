<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Contracts\Encryption\DecryptException;

class PaymentSystem extends Model
{
    use HasFactory;

    protected $table = 'payment_systems';
    protected $guarded = [];


//    protected $casts = [
//        'test_mode' => 'boolean',
//    ];

    protected $casts = [
//        'settings' => 'array',
        'test_mode' => 'boolean',
        'is_enabled' => 'boolean',
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

            Log::warning('PaymentSystem settings decrypt failed', [
                'id'         => $this->id,
                'name'       => $this->name,
                'partner_id' => $this->partner_id,
                'msg'        => $e->getMessage(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('PaymentSystem settings decrypt throwable', [
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

        // ВАЖНО: критерии "подключено" зависят от конкретного провайдера.
        // Держим логику здесь, чтобы UI и бизнес-код были консистентны.
        switch ((string) $this->name) {
            case 'robokassa':
                return !empty($s['merchant_login']) && !empty($s['password1']);

            case 'tbank':
                // Для корректной работы мультирасчётов нам нужны ключи как для приема платежа (eacq),
                // так и для выплаты партнёру (e2c).
                return !empty($s['terminal_key'])
                    && !empty($s['token_password'])
                    && !empty($s['e2c_terminal_key'])
                    && !empty($s['e2c_token_password']);

            default:
                return false;
        }
    }
}
