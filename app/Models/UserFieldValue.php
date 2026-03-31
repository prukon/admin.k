<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserFieldValue extends Model
{
    use HasFactory;

    protected $table = 'user_field_values';

    protected $fillable = [
        'user_id',
        'field_id',
        'value',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function field()
    {
//        return $this->belongsTo(UserField::class);
        return $this->belongsTo(UserField::class, 'field_id');

    }
}
