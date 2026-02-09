<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Enums\ContactSubmissionStatus;

 
class ContactSubmission extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'contact_submissions';
    protected $guarded = []; //разрешение на изменение данных в таблице}


    protected $casts = [
        'status' => ContactSubmissionStatus::class,
    ];
}
