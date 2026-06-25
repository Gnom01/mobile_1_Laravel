<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->unsignedInteger('EmployeesID')->primary();
            $table->unsignedSmallInteger('EmployeeStatusesDVID')->default(0);
            $table->unsignedSmallInteger('WebsiteStatusDVID')->default(0);
            $table->string('LastName', 50)->default('');
            $table->string('FirstName', 50)->default('');
            $table->string('SecondName', 50)->default('');
            $table->string('FamilyName', 50)->default('');
            $table->string('FatherName', 50)->default('');
            $table->string('MotherName', 50)->default('');
            $table->date('DateOfBirdth')->nullable();
            $table->string('BirthPlace', 50)->default('');
            $table->string('PESEL', 50)->default('');
            $table->string('NIP', 50)->default('');
            $table->string('IdentityNumber', 50)->default('');
            $table->string('PassportNumber', 50)->default('');
            $table->string('Country', 50)->default('');
            $table->string('Street', 50)->default('');
            $table->string('Building', 15)->default('');
            $table->string('Flat', 15)->default('');
            $table->string('City', 50)->default('');
            $table->string('PostalCode', 15)->default('');
            $table->string('PostPlace', 50)->default('');
            $table->unsignedSmallInteger('VoivodeshipDVID')->default(0);
            $table->string('District', 50)->default('');
            $table->string('Community', 50)->default('');
            $table->string('Citizenship', 60)->default('');
            $table->string('Nationality', 50)->default('');
            $table->tinyInteger('Forigner')->default(0);
            $table->unsignedSmallInteger('GenderDVID')->default(0);
            $table->string('TaxOfficeName', 100)->default('');
            $table->string('TaxOfficePostCode', 50)->default('');
            $table->string('TaxOfficeCity', 50)->default('');
            $table->string('TaxOfficeAddress', 50)->default('');
            $table->string('BanckAccountNumber', 50)->default('');
            $table->string('Phone', 25)->default('');
            $table->string('Email', 100)->default('');
            $table->date('StartDateCooperation')->nullable();
            $table->date('EndDateCooperation')->nullable();
            $table->string('ProfileActivities', 500)->default('');
            $table->string('Description', 4000)->default('');
            $table->tinyInteger('Cancelled')->default(0);
            $table->dateTime('WhenInserted')->nullable();
            $table->unsignedInteger('WhoInserted_UsersID')->default(0);
            $table->dateTime('WhenUpdated')->nullable();
            $table->unsignedInteger('WhoUpdated_UsersID')->default(0);
            $table->unsignedInteger('LocalizationsID')->default(0);
            $table->string('FileName', 500)->default('');
            $table->string('FileExtension', 10)->default('');
            $table->unsignedInteger('UsersID')->default(0);
            $table->unsignedSmallInteger('PositionsDVID')->default(0);
            $table->string('fullName', 255)->default('');

            $table->index('LocalizationsID');
            $table->index('WebsiteStatusDVID');
            $table->index('Cancelled');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
