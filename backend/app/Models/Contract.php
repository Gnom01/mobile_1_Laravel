<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contract extends Model
{
    protected $table = 'contracts';
    protected $primaryKey = 'contractsID';
    public $incrementing = false;
    protected $keyType = 'int';
    public $timestamps = false;
    protected $guarded = [];

    protected $casts = [
        'contracConclusionDate' => 'date',
        'contractPeriodFrom' => 'date',
        'contractPeriodTo' => 'date',
        'expirationDate' => 'datetime',
        'whenInserted' => 'datetime',
        'whenUpdated' => 'datetime',
        'contractAmount' => 'decimal:2',
        'installmentValueZero' => 'decimal:2',
        'entryFee' => 'decimal:2',
        'sumOfInitialCharges' => 'decimal:2',
        'monthlyInstallment' => 'decimal:2',
    ];
}
