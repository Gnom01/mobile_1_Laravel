<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE coursesheadings
                MODIFY StartingDate DATE NULL,
                MODIFY ClosingDate DATE NULL,
                MODIFY ExpirationDate DATE NULL,
                MODIFY PaymentDate DATE NULL
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE coursesheadings
                MODIFY StartingDate DATE NOT NULL,
                MODIFY ClosingDate DATE NOT NULL,
                MODIFY ExpirationDate DATE NOT NULL,
                MODIFY PaymentDate DATE NULL
        ");
    }
};
