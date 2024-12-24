<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    use HasFactory;

    protected $table = 'tags';

    protected $fillable = [
        'name',
        'slug',
        'field_type',
    ];

    public function userTagValues()
    {
        return $this->hasMany(UserTagValue::class, 'tag_id');
    }}
