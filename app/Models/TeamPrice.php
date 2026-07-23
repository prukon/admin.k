<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TeamPrice extends Model
{
    use HasFactory;

    protected $table = 'team_prices';

    protected $guarded = [];

    protected $casts = [
        'team_id' => 'integer',
        'lesson_package_id' => 'integer',
    ];

    public function team()
    {
        return $this->belongsTo(Team::class, 'team_id', 'id');
    }

    public function lessonPackage()
    {
        return $this->belongsTo(LessonPackage::class, 'lesson_package_id');
    }
}
