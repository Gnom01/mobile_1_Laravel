<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsersPaymentsSchedule extends Model
{
    protected $table = 'userspaymentsschedules';
    protected $primaryKey = 'usersPaymentsSchedulesID';
    public $incrementing = false;
    protected $keyType = 'int';
    public $timestamps = false;
    protected $guarded = [];

    protected $casts = [
        'paymentDate' => 'date',
        'lastPaymentDate' => 'date',
        'whenInserted' => 'datetime',
        'orderValue' => 'integer', // Assuming orderValue is an attribute that needs casting
        'leftToPaid' => 'decimal:2', // Casting the leftToPaid attribute
        'whenUpdated' => 'datetime',
        'paymentAmount' => 'decimal:2',
        'amountPaid' => 'decimal:2',
        'amountTransferred' => 'decimal:2',
        'amountCorrected' => 'decimal:2',
        'price' => 'decimal:2',
    ];
}
