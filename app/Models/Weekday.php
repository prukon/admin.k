<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Weekday extends Model
{
    use HasFactory;


    public function teams()
    {
        return $this->belongsToMany(Team::class, 'team_weekdays','weekday_id', 'team_id' );
    }
}
