<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PushNotificationRecipient extends Model
{
    protected $guarded = [];

    protected $casts = [
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'opened_at' => 'datetime',
    ];

    public function notification()
    {
        return $this->belongsTo(PushNotification::class, 'push_notification_id');
    }

    public function deviceToken()
    {
        return $this->belongsTo(DeviceToken::class);
    }
}
