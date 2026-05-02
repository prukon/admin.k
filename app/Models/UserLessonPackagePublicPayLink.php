<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserLessonPackagePublicPayLink extends Model
{
    protected $table = 'user_lesson_package_public_pay_links';

    protected $guarded = [];

    protected $casts = [
        'expires_at' => 'datetime',
        'user_lesson_package_id' => 'int',
        'partner_id' => 'int',
        'payment_intent_id' => 'int',
        'payable_id' => 'int',
    ];

    public function userLessonPackage(): BelongsTo
    {
        return $this->belongsTo(UserLessonPackage::class, 'user_lesson_package_id');
    }
}
