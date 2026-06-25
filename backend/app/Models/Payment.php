<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $table = 'payments';
    protected $primaryKey = 'paymentsID';
    public $incrementing = false;
    protected $keyType = 'int';
    public $timestamps = false;
    protected $guarded = [];

    protected $casts = [
        'paymentDate' => 'date',
        'whenInserted' => 'datetime',
        'whenUpdated' => 'datetime',
        'fiscalizedDate' => 'datetime',
        'rejectionDate' => 'datetime',
        'paymentAmount' => 'decimal:2',
    ];
}
