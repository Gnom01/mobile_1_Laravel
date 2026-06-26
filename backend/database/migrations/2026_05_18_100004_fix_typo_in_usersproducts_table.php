<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('usersproducts', 'priceliststemplatesposititionsid')) {
            return;
        }

        if (Schema::hasColumn('usersproducts', 'priceliststemplatespositionsid')) {
            return;
        }

        Schema::table('usersproducts', function (Blueprint $table) {
            $table->renameColumn('priceliststemplatesposititionsid', 'priceliststemplatespositionsid');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('usersproducts', 'priceliststemplatespositionsid')) {
            return;
        }

        if (Schema::hasColumn('usersproducts', 'priceliststemplatesposititionsid')) {
            return;
        }

        Schema::table('usersproducts', function (Blueprint $table) {
            $table->renameColumn('priceliststemplatespositionsid', 'priceliststemplatesposititionsid');
        });
    }
};
