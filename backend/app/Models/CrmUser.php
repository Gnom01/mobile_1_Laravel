<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class CrmUser extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'users';
    protected $primaryKey = 'UsersID';
    public $timestamps = false;
    protected $guarded = [];
    protected $hidden = [];
    protected $casts = [
        'WhenInserted' => 'datetime',
        'WhenUpdated' => 'datetime',
        'ActivationDate' => 'datetime',
        'WhenStatusUpdated' => 'datetime',
        'PassResetExpiration' => 'datetime',
        'DateOfBirdth' => 'date',
    ];

    /**
     * Get the password for the user.
     */
    public function getAuthPassword()
    {
        return $this->Password;
    }

    /**
     * Get the column name for the "email".
     */
    public function getEmailForPasswordReset()
    {
        return $this->Email;
    }
}
