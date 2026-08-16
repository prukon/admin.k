<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InAppNotificationRecipient extends Model
{
    protected $table = 'in_app_notification_recipients';

    protected $guarded = [];

    public function notification(): BelongsTo
    {
        return $this->belongsTo(InAppNotification::class, 'in_app_notification_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
