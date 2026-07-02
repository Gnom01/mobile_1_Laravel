<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RelationLinkRequest extends Model
{
    protected $table = 'relation_link_requests';

    protected $fillable = [
        'requester_users_id',
        'target_users_id',
        'otp_recipient_users_id',
        'otp_recipient_phone',
        'participant_relations_dvid',
        'code_hash',
        'expires_at',
        'attempts',
        'sent_message_id',
        'status',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];
}
