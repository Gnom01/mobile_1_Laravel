<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsersPaymentsSchedule extends Model
{
    protected $table = 'userspaymentsschedules';
    protected $primaryKey = 'id';
    public $incrementing = true;
    public $timestamps = false;
    protected $guarded = [];

    protected $casts = [
        'paymentDate' => 'date',
        'lastPaymentDate' => 'date',
        'whenInserted' => 'datetime',
        'whenUpdated' => 'datetime',
        'paymentAmount' => 'decimal:2',
        'amountPaid' => 'decimal:2',
        'amountTransferred' => 'decimal:2',
        'amountCorrected' => 'decimal:2',
        'price' => 'decimal:2',
    ];
}
