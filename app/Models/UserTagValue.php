<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserTagValue extends Model
{
    use HasFactory;

    protected $table = 'user_tag_values';

    protected $fillable = [
        'user_id',
        'tag_id',
        'value',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tag()
    {
        return $this->belongsTo(Tag::class);
    }
}
