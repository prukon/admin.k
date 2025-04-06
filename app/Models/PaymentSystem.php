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



    public function setSettingsAttribute($value)
    {
        // $value — это массив
        $json = json_encode($value);
        $this->attributes['settings'] = Crypt::encryptString($json);
    }

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
}
