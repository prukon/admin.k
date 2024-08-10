<?php

namespace App\Models;

use App\Models\Traits\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;
    use HasFactory;
    use Filterable;

    protected $guarded = []; //разрешение на изменение данных в таблице

    protected $fillable = [
        'date',
    ];

}
