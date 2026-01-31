<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
     public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_general_ci';

           $table->unsignedInteger('UsersID')->primary();

            $table->string('LastName', 100);
            $table->string('FirstName', 100)->default('');
            $table->string('Login', 100)->default('');
            $table->string('Email', 255)->default('');
            $table->string('Password', 100)->default('');
            $table->smallInteger('PassLenght')->default(0);
            $table->unsignedInteger('RolesID')->default(1);
            $table->unsignedInteger('ClientsID')->default(0);
            $table->integer('UserStatus')->default(1);
            $table->string('Hash', 50)->default('');
            $table->integer('Active')->default(0);
            $table->dateTime('ActivationDate')->nullable();
            $table->integer('NumberOfLogins')->default(0);
            $table->string('PassResetToken', 255)->default('');
            $table->dateTime('PassResetExpiration')->default('1999-12-31 00:00:00');

            $table->string('JobTitle', 255)->default('');
            $table->string('Phone', 25)->default('');
            $table->string('Room', 25)->default('');
            $table->string('WebSite', 255)->default('');
            $table->tinyInteger('Newsletter')->default(0);
            $table->string('RequestedCompanyName', 255)->default('');
            $table->string('Description', 255)->default('');
            $table->tinyInteger('Cancelled')->default(0);

            $table->dateTime('WhenInserted')->useCurrent();
            $table->unsignedInteger('WhoInserted_UsersID')->default(0);
            $table->dateTime('WhenUpdated')->useCurrent();
            $table->unsignedInteger('WhoUpdated_UsersID')->default(0);

            $table->unsignedInteger('Default_LocalizationsID')->default(0);
            $table->date('DateOfBirdth')->nullable();
            $table->string('BirthPlace', 50)->default('');

            $table->string('Street', 100)->default('');
            $table->string('Building', 15)->default('');
            $table->string('Flat', 15)->default('');
            $table->string('City', 50)->default('');
            $table->string('PostalCode', 15)->default('');
            $table->string('PostPlace', 10)->default('');

            $table->smallInteger('VoivodeshipDVID')->default(0);
            $table->string('District', 50)->default('');
            $table->string('Comunity', 50)->default('');
            $table->smallInteger('GenderDVID')->default(0);

            $table->string('MemberCardNumber', 50)->default('');
            $table->string('IdentityNumber', 50)->default('');
            $table->string('Pesel', 15)->default('');

            $table->tinyInteger('PersonalDataProcessingConsent')->default(0);
            $table->tinyInteger('consentReceiveSmsEmailPhone')->default(0);
            $table->tinyInteger('marketingAgreement')->default(0);

            $table->smallInteger('PaymentMethodsDVID')->default(0);
            $table->integer('Parent_UsersID')->nullable();

            $table->string('FileName', 255)->default('');
            $table->string('FileExtension', 10)->default('');
            $table->string('bankAccount', 255)->default('');

            // generated columns (STORED)
            $table->string('address', 255)->storedAs(
                "concat(`Street`,' ',(case when ((`Building` <> '') AND (`Flat` <> '')) THEN CONCAT(`Building`, '/', `Flat`) ELSE CONCAT(`Building`, `Flat`) END))"
            );

            $table->string('fullName', 255)->storedAs(
                "CONCAT(`LastName`, ' ', `FirstName`)"
            );

            $table->text('globalFilerUsers')->storedAs(
                "CONCAT(`FirstName`, ' ', `LastName`, ' ', `FirstName`, ' ', `Phone`, ' ', `Email`, ' ', `MemberCardNumber`)"
            );

            $table->tinyInteger('isMainAccount')->default(1);
            $table->integer('transferID')->nullable();
            $table->integer('transfer_opiekunowieID')->nullable();
            $table->tinyInteger('Adult')->nullable();
            $table->dateTime('WhenStatusUpdated')->nullable();
            $table->tinyInteger('changePassword')->default(0);
            $table->tinyInteger('entryFee')->default(0);

            // indexy / unique
            $table->unique(['transferID', 'UsersID'], 'UK_users');
            $table->unique(['LastName', 'FirstName', 'Cancelled', 'UsersID'], 'UK_users_LastFirstName');
            $table->unique(['address', 'Cancelled', 'UsersID'], 'Users_Address');
            $table->unique(['IdentityNumber', 'Cancelled', 'UsersID'], 'Users_IdentityNumber');
            $table->unique(['MemberCardNumber', 'Cancelled', 'UsersID'], 'Users_MemberCardNumber');
            $table->unique(['Phone', 'FirstName', 'Cancelled', 'UsersID'], 'Users_Phone');

            $table->unique(['transferID'], 'UK_users_transferID');
            $table->index(['Email', 'Phone', 'FirstName', 'Cancelled'], 'Users_Email');
            $table->fullText('globalFilerUsers', 'Users_globalFilerUsers');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
