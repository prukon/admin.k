<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Лог исходящих писем (Mail/Notifications).
 *
 * Заполняется автоматически листенером App\Listeners\LogOutgoingEmail
 * на событиях Illuminate\Mail\Events\MessageSending / MessageSent.
 */
class OutgoingEmailLog extends Model
{
    protected $table = 'outgoing_email_logs';

    public const STATUS_SENDING = 'sending';
    public const STATUS_SENT    = 'sent';
    public const STATUS_FAILED  = 'failed';

    protected $guarded = ['id'];

    protected $casts = [
        'reply_to'        => 'array',
        'to_addresses'    => 'array',
        'cc_addresses'    => 'array',
        'bcc_addresses'   => 'array',
        'attachments'     => 'array',
        'sent_at'         => 'datetime',
        'failed_at'       => 'datetime',
        'send_attempts'   => 'integer',
        'partner_id'      => 'integer',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }
}
