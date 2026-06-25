<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected $primaryKey = 'UsersID';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'int';

    protected $fillable = [
        'UsersID',
        'LastName',
        'FirstName',
        'Login',
        'Email',
        'Password',
    ];

    protected $hidden = [
        'Password',
        'remember_token',
    ];

    /** Alias so code using ->id keeps working */
    public function getIdAttribute(): int
    {
        return $this->UsersID;
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
        ];
    }
}
