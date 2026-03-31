<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserTableSetting extends Model
{
    protected $table = 'user_table_settings';
    protected $guarded = [];

    protected $casts = [
        'columns' => 'array', // 👈 важно, чтобы columns автоматически превращался в массив
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
