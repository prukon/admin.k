<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentNotificationDispatch extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    protected $table = 'payment_notification_dispatches';

    protected $guarded = [];

    protected $casts = [
        'partner_id'                     => 'integer',
        'payment_notification_rule_id'   => 'integer',
        'users_price_id'                 => 'integer',
        'outgoing_email_log_id'          => 'integer',
        'trigger_date'                   => 'date',
        'sent_at'                        => 'datetime',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(PaymentNotificationRule::class, 'payment_notification_rule_id');
    }

    public function userPrice(): BelongsTo
    {
        return $this->belongsTo(UserPrice::class, 'users_price_id');
    }

    public function outgoingEmailLog(): BelongsTo
    {
        return $this->belongsTo(OutgoingEmailLog::class, 'outgoing_email_log_id');
    }
}
