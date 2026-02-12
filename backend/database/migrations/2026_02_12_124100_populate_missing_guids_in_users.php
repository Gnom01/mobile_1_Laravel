<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('users')->whereNull('guid')->orWhere('guid', '')->orderBy('UsersID')->chunk(100, function ($users) {
            foreach ($users as $user) {
                DB::table('users')
                    ->where('UsersID', $user->UsersID)
                    ->update(['guid' => (string) Str::uuid()]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No easy way to reverse this without losing data
    }
};
