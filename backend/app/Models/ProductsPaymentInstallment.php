<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductsPaymentInstallment extends Model
{
    protected $table = 'productspaymentinstallments';
    protected $primaryKey = 'productspaymentinstallmentsid';
    public $incrementing = false;
    protected $keyType = 'int';
    public $timestamps = false;
    protected $guarded = [];
}
