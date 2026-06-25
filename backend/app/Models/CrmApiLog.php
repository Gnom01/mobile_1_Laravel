<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Audit log of every HTTP call made to the CRM API.
 * Tokens and sensitive user fields must never be written here.
 *
 * @property int         $id
 * @property string|null $guid
 * @property string      $endpoint
 * @property string      $method
 * @property array|null  $request_json
 * @property array|null  $response_json
 * @property int|null    $http_status
 * @property int|null    $duration_ms
 * @property string|null $error_message
 */
class CrmApiLog extends Model
{
    protected $table = 'crm_api_logs';

    protected $guarded = [];

    protected $casts = [
        'request_json'  => 'array',
        'response_json' => 'array',
    ];
}
