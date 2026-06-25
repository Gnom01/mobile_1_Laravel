<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int         $id
 * @property string      $guid
 * @property int|null    $user_id
 * @property int|null    $payer_user_id
 * @property string      $status
 * @property string      $payload_hash
 * @property array       $payload_json
 * @property array|null  $crm_response_json
 * @property int|null    $crm_contracts_id
 * @property int|null    $crm_users_products_id
 * @property int|null    $crm_payments_id
 * @property string|null $payment_session_id
 * @property string|null $payment_token
 * @property string|null $payment_url
 * @property string|null $error_message
 * @property int         $attempts
 * @property \Carbon\Carbon|null $locked_at
 * @property \Carbon\Carbon|null $processed_at
 * @property \Carbon\Carbon      $created_at
 * @property \Carbon\Carbon      $updated_at
 */
class OrderRequest extends Model
{
    // ─── Status constants ─────────────────────────────────────────────────────

    public const STATUS_PENDING           = 'pending';
    public const STATUS_PROCESSING        = 'processing';
    public const STATUS_CRM_SUCCESS       = 'crm_success';
    public const STATUS_CRM_FAILED        = 'crm_failed';
    public const STATUS_LOCAL_SYNCED      = 'local_synced';
    public const STATUS_LOCAL_SYNC_FAILED = 'local_sync_failed';

    /** How long (seconds) a processing lock is considered fresh. */
    public const PROCESSING_LOCK_TTL = 120;

    protected $table = 'order_requests';

    protected $guarded = [];

    protected $casts = [
        'payload_json'      => 'array',
        'crm_response_json' => 'array',
        'locked_at'         => 'datetime',
        'processed_at'      => 'datetime',
        'attempts'          => 'integer',
    ];

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function isTerminal(): bool
    {
        return in_array($this->status, [
            self::STATUS_CRM_FAILED,
            self::STATUS_LOCAL_SYNCED,
        ], true);
    }

    public function isAlreadySuccessful(): bool
    {
        return in_array($this->status, [
            self::STATUS_CRM_SUCCESS,
            self::STATUS_LOCAL_SYNCED,
        ], true);
    }

    public function isProcessingFresh(): bool
    {
        return $this->status === self::STATUS_PROCESSING
            && $this->locked_at !== null
            && $this->locked_at->diffInSeconds(now()) < self::PROCESSING_LOCK_TTL;
    }
}
