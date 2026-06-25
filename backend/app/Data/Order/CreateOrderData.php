<?php

namespace App\Data\Order;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Input DTO for OrderApplicationService::createOrder().
 * Carries everything the front-end sends plus the resolved user context.
 */
final class CreateOrderData
{
    /** @var string */
    public $guid;

    /** @var int — authenticated user (payer / logged-in parent) */
    public $userId;

    /** @var int */
    public $payerUserId;

    /** @var int|null — CRM UsersID of the course participant, resolved from the GUID sent by Flutter */
    public $participantUsersId;

    /** @var array */
    public $payload;

    public function __construct(string $guid, int $userId, int $payerUserId, ?int $participantUsersId, array $payload)
    {
        $this->guid               = $guid;
        $this->userId             = $userId;
        $this->payerUserId        = $payerUserId;
        $this->participantUsersId = $participantUsersId;
        $this->payload            = $payload;
    }

    public static function fromArray(array $data, int $authUserId): self
    {
        // usersID arrives as a GUID (UUID string) from Flutter.
        // Resolve it to the integer CRM UsersID (participant, not the payer).
        $participantUsersId = null;
        if (!empty($data['usersID'])) {
            if (is_string($data['usersID']) && !is_numeric($data['usersID'])) {
                $resolved = DB::table('users')->where('guid', $data['usersID'])->value('UsersID');
                if (!$resolved) {
                    Log::warning('CreateOrderData: could not resolve usersID GUID to UsersID', [
                        'guid'    => $data['guid'] ?? null,
                        'usersID' => $data['usersID'],
                    ]); 
                }
                $participantUsersId = $resolved ? (int) $resolved : null;
            } else {
                $participantUsersId = (int) $data['usersID'];
            }
        }

        return new self(
            $data['guid'],
            $authUserId,
            (int) ($data['payerUserId'] ?? $authUserId),
            $participantUsersId,
            $data
        );
    }
}