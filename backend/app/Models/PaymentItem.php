<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentItem extends Model
{
    protected $table = 'paymentsitems';
    protected $primaryKey = 'paymentsItemsID';
    public $incrementing = false;
    protected $keyType = 'int';
    public $timestamps = false;
    protected $guarded = [];

    protected $casts = [
        'paymentDate' => 'date',
        'whenInserted' => 'datetime',
        'whenUpdated' => 'datetime',
        'productUnitPrice' => 'decimal:2',
        'productQuantity' => 'decimal:4',
        'paymentItemAmount' => 'decimal:2',
        'vatAmount' => 'decimal:2',
    ];
}
