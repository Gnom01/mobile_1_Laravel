<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Zapis akceptacji Regulaminu aplikacji przez użytkownika:
 * wersja dokumentu, data i sposób akceptacji (rozliczalność RODO).
 */
class TermsAcceptance extends Model
{
    protected $table = 'terms_acceptances';

    protected $fillable = [
        'UsersID',
        'document_version',
        'method',
        'accepted_at',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
    ];
}
