<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Bidirectional mapping between a local projection row and its CRM counterpart.
 *
 * @property int         $id
 * @property string      $guid
 * @property string      $local_table
 * @property int         $local_id
 * @property string      $crm_table
 * @property int         $crm_id
 * @property \Carbon\Carbon|null $last_synced_at
 */
class CrmObjectMapping extends Model
{
    protected $table = 'crm_object_mappings';

    protected $guarded = [];

    protected $casts = [
        'last_synced_at' => 'datetime',
    ];
}
