<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Enums\ContactSubmissionStatus;

class ContactSubmission extends Model
{
    use HasFactory, SoftDeletes;

        protected $table = 'contact_submissions';

        
    protected $fillable = [
        'name',
        'email',
        'phone',
        'website',
        'message',
        'status',
        'comment',
    ];

    protected $casts = [
        'status' => ContactSubmissionStatus::class,
    ];

    // Валидация статуса (пример на уровне модели, если будет програм., для mass-assign или update/dirty)
    // public static function boot()
    // {
    //     parent::boot();
    //     static::saving(function($model) {
    //         if ($model->status && !in_array($model->status, ContactSubmissionStatus::values(), true)) {
    //             throw new \InvalidArgumentException("Invalid status value given");
    //         }
    //     });
    // }
}
