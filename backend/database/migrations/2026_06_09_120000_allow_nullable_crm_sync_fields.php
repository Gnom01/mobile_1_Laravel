<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE coursesheadings
                MODIFY ProductsLevel1DVID SMALLINT UNSIGNED NULL DEFAULT 0,
                MODIFY ProductsLevel2DVID SMALLINT UNSIGNED NULL DEFAULT 0,
                MODIFY ProductsLevel3DVID SMALLINT UNSIGNED NULL DEFAULT 0,
                MODIFY DimensionsPatternsID INT UNSIGNED NULL DEFAULT 0,
                MODIFY CourseHeadingName VARCHAR(255) NULL,
                MODIFY CourseHeadingShortName VARCHAR(50) NULL DEFAULT '',
                MODIFY MaxNumberOfPersons INT NULL DEFAULT 0,
                MODIFY NumberOfPersons INT NULL DEFAULT 0,
                MODIFY StartingDate DATE NULL,
                MODIFY ClosingDate DATE NULL,
                MODIFY ClosedGroup TINYINT NULL DEFAULT 0,
                MODIFY CourseStatusesDVID SMALLINT UNSIGNED NULL,
                MODIFY WebsiteStatusesDVID SMALLINT UNSIGNED NULL,
                MODIFY ExpirationDate DATE NULL,
                MODIFY CourseDurationInMinutesDVID SMALLINT UNSIGNED NULL DEFAULT 0,
                MODIFY CourseFrequencyPerWeek INT NULL DEFAULT 0,
                MODIFY CourseFrequencyDVID SMALLINT UNSIGNED NULL DEFAULT 0,
                MODIFY AccountNumber VARCHAR(50) NULL DEFAULT '',
                MODIFY Description VARCHAR(255) NULL DEFAULT '',
                MODIFY Cancelled TINYINT NULL DEFAULT 0,
                MODIFY WhenInserted DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
                MODIFY WhoInserted_UsersID INT UNSIGNED NULL DEFAULT 0,
                MODIFY WhenUpdated DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
                MODIFY WhoUpdated_UsersID INT UNSIGNED NULL DEFAULT 0,
                MODIFY LocalizationsID INT UNSIGNED NULL DEFAULT 0,
                MODIFY Parent_CoursesHeadingsID INT UNSIGNED NULL DEFAULT 0,
                MODIFY WorkshopCoursesTypesDVID SMALLINT UNSIGNED NULL DEFAULT 0,
                MODIFY Hidden TINYINT NULL DEFAULT 0,
                MODIFY EventCourseStatus TINYINT NULL DEFAULT 0,
                MODIFY PaymentDate DATE NULL
        ");

        DB::statement("
            ALTER TABLE userspaymentsschedules
                MODIFY usersID INT UNSIGNED NULL DEFAULT 0,
                MODIFY contractsID INT UNSIGNED NULL DEFAULT 0,
                MODIFY productsID INT UNSIGNED NULL DEFAULT 0,
                MODIFY coursesHeadingsID INT UNSIGNED NULL DEFAULT 0,
                MODIFY instalmentNumber SMALLINT UNSIGNED NULL DEFAULT 0,
                MODIFY contractInstalmentNumber SMALLINT UNSIGNED NULL DEFAULT 0,
                MODIFY voidInstalment TINYINT NULL DEFAULT 0,
                MODIFY positionName VARCHAR(255) NULL,
                MODIFY productAvailableFromDate VARCHAR(255) NULL,
                MODIFY productAvailableToDate VARCHAR(255) NULL DEFAULT '',
                MODIFY lessonsAreCounted TINYINT NULL DEFAULT 0,
                MODIFY lessonsRemainingForUse SMALLINT UNSIGNED NULL DEFAULT 0,
                MODIFY paymentDate DATE NULL,
                MODIFY paymentAmount DECIMAL(10, 2) NULL DEFAULT 0.00,
                MODIFY paymentStatusesDVID SMALLINT UNSIGNED NULL DEFAULT 1,
                MODIFY paymentMethodDVIDList VARCHAR(255) NULL DEFAULT '0',
                MODIFY amountPaid DECIMAL(10, 2) NULL DEFAULT 0.00,
                MODIFY amountTransferred DECIMAL(10, 2) NULL DEFAULT 0.00,
                MODIFY amountCorrected DECIMAL(10, 2) NULL DEFAULT 0.00,
                MODIFY comments VARCHAR(500) NULL,
                MODIFY localizationsID INT UNSIGNED NULL,
                MODIFY cancelled TINYINT NULL DEFAULT 0,
                MODIFY whenInserted DATETIME NULL,
                MODIFY whoInserted_UsersID INT UNSIGNED NULL DEFAULT 0,
                MODIFY whenUpdated DATETIME NULL,
                MODIFY whoUpdated_UsersID INT UNSIGNED NULL DEFAULT 0,
                MODIFY price DECIMAL(10, 2) NULL DEFAULT 0.00,
                MODIFY usersProductsID INT UNSIGNED NULL,
                MODIFY lastPaymentDate DATE NULL,
                MODIFY processesDVID SMALLINT NULL DEFAULT 0,
                MODIFY payer_UsersID INT UNSIGNED NULL DEFAULT 0,
                MODIFY paymentMethodDVID VARCHAR(255) NULL DEFAULT ''
        ");

    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE coursesheadings
                MODIFY ProductsLevel1DVID SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                MODIFY ProductsLevel2DVID SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                MODIFY ProductsLevel3DVID SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                MODIFY DimensionsPatternsID INT UNSIGNED NOT NULL DEFAULT 0,
                MODIFY CourseHeadingName VARCHAR(255) NOT NULL,
                MODIFY CourseHeadingShortName VARCHAR(50) NOT NULL DEFAULT '',
                MODIFY MaxNumberOfPersons INT NOT NULL DEFAULT 0,
                MODIFY NumberOfPersons INT NOT NULL DEFAULT 0,
                MODIFY StartingDate DATE NOT NULL,
                MODIFY ClosingDate DATE NOT NULL,
                MODIFY ClosedGroup TINYINT NOT NULL DEFAULT 0,
                MODIFY CourseStatusesDVID SMALLINT UNSIGNED NOT NULL,
                MODIFY WebsiteStatusesDVID SMALLINT UNSIGNED NOT NULL,
                MODIFY ExpirationDate DATE NOT NULL,
                MODIFY CourseDurationInMinutesDVID SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                MODIFY CourseFrequencyPerWeek INT NOT NULL DEFAULT 0,
                MODIFY CourseFrequencyDVID SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                MODIFY AccountNumber VARCHAR(50) NOT NULL DEFAULT '',
                MODIFY Description VARCHAR(255) NOT NULL DEFAULT '',
                MODIFY Cancelled TINYINT NOT NULL DEFAULT 0,
                MODIFY WhenInserted DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                MODIFY WhoInserted_UsersID INT UNSIGNED NOT NULL DEFAULT 0,
                MODIFY WhenUpdated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                MODIFY WhoUpdated_UsersID INT UNSIGNED NOT NULL DEFAULT 0,
                MODIFY LocalizationsID INT UNSIGNED NOT NULL DEFAULT 0,
                MODIFY Parent_CoursesHeadingsID INT UNSIGNED NOT NULL DEFAULT 0,
                MODIFY WorkshopCoursesTypesDVID SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                MODIFY Hidden TINYINT NOT NULL DEFAULT 0,
                MODIFY EventCourseStatus TINYINT DEFAULT 0,
                MODIFY PaymentDate DATE NULL
        ");

        DB::statement("
            ALTER TABLE userspaymentsschedules
                MODIFY usersID INT UNSIGNED NOT NULL DEFAULT 0,
                MODIFY contractsID INT UNSIGNED NOT NULL DEFAULT 0,
                MODIFY productsID INT UNSIGNED NOT NULL DEFAULT 0,
                MODIFY coursesHeadingsID INT UNSIGNED NOT NULL DEFAULT 0,
                MODIFY instalmentNumber SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                MODIFY contractInstalmentNumber SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                MODIFY voidInstalment TINYINT NOT NULL DEFAULT 0,
                MODIFY positionName VARCHAR(255) NOT NULL,
                MODIFY productAvailableFromDate VARCHAR(255) NOT NULL,
                MODIFY productAvailableToDate VARCHAR(255) NOT NULL DEFAULT '',
                MODIFY lessonsAreCounted TINYINT NOT NULL DEFAULT 0,
                MODIFY lessonsRemainingForUse SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                MODIFY paymentDate DATE NOT NULL,
                MODIFY paymentAmount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                MODIFY paymentStatusesDVID SMALLINT UNSIGNED NOT NULL DEFAULT 1,
                MODIFY paymentMethodDVIDList VARCHAR(255) NOT NULL DEFAULT '0',
                MODIFY amountPaid DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                MODIFY amountTransferred DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                MODIFY amountCorrected DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                MODIFY comments VARCHAR(500) NULL,
                MODIFY localizationsID INT UNSIGNED NOT NULL,
                MODIFY cancelled TINYINT NOT NULL DEFAULT 0,
                MODIFY whenInserted DATETIME NOT NULL,
                MODIFY whoInserted_UsersID INT UNSIGNED NOT NULL DEFAULT 0,
                MODIFY whenUpdated DATETIME NOT NULL,
                MODIFY whoUpdated_UsersID INT UNSIGNED NOT NULL DEFAULT 0,
                MODIFY price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                MODIFY usersProductsID INT UNSIGNED NOT NULL,
                MODIFY lastPaymentDate DATE NULL,
                MODIFY processesDVID SMALLINT NOT NULL DEFAULT 0,
                MODIFY payer_UsersID INT UNSIGNED NOT NULL DEFAULT 0,
                MODIFY paymentMethodDVID VARCHAR(255) NOT NULL DEFAULT ''
        ");

    }
};
