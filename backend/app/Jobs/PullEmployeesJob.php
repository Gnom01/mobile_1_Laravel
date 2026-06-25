<?php

namespace App\Jobs;

use App\Models\Employee;
use App\Services\CrmSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PullEmployeesJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 3600;

    public function handle(CrmSyncService $syncService): void
    {
        $syncService->sync([
            'resource'      => 'employees',
            'endpoint'      => '/CrmToMobileSync/getEmployeesData',
            'model'         => Employee::class,
            'primaryKey'    => 'EmployeesID',
            'apiPrimaryKey' => 'EmployeesID',
            'pageSize'      => 500,
            'responseKey'   => 'body',
              'extraParams' => [
                'current_LocalizationsID' => '0',
            ],

            'fieldMap' => function (array $r) use ($syncService): array {
                return [
                    'EmployeeStatusesDVID'  => (int)($r['EmployeeStatusesDVID'] ?? 0),
                    'WebsiteStatusDVID'     => (int)($r['WebsiteStatusDVID'] ?? 0),
                    'LastName'              => (string)($r['LastName'] ?? ''),
                    'FirstName'             => (string)($r['FirstName'] ?? ''),
                    'SecondName'            => (string)($r['SecondName'] ?? ''),
                    'FamilyName'            => (string)($r['FamilyName'] ?? ''),
                    'FatherName'            => (string)($r['FatherName'] ?? ''),
                    'MotherName'            => (string)($r['MotherName'] ?? ''),
                    'DateOfBirdth'          => $syncService->validateDate($r['DateOfBirdth'] ?? '', null),
                    'BirthPlace'            => (string)($r['BirthPlace'] ?? ''),
                    'PESEL'                 => (string)($r['PESEL'] ?? ''),
                    'NIP'                   => (string)($r['NIP'] ?? ''),
                    'IdentityNumber'        => (string)($r['IdentityNumber'] ?? ''),
                    'PassportNumber'        => (string)($r['PassportNumber'] ?? ''),
                    'Country'               => (string)($r['Country'] ?? ''),
                    'Street'                => (string)($r['Street'] ?? ''),
                    'Building'              => (string)($r['Building'] ?? ''),
                    'Flat'                  => (string)($r['Flat'] ?? ''),
                    'City'                  => (string)($r['City'] ?? ''),
                    'PostalCode'            => (string)($r['PostalCode'] ?? ''),
                    'PostPlace'             => (string)($r['PostPlace'] ?? ''),
                    'VoivodeshipDVID'       => (int)($r['VoivodeshipDVID'] ?? 0),
                    'District'              => (string)($r['District'] ?? ''),
                    'Community'             => (string)($r['Community'] ?? ''),
                    'Citizenship'           => (string)($r['Citizenship'] ?? ''),
                    'Nationality'           => (string)($r['Nationality'] ?? ''),
                    'Forigner'              => (int)($r['Forigner'] ?? 0),
                    'GenderDVID'            => (int)($r['GenderDVID'] ?? 0),
                    'TaxOfficeName'         => (string)($r['TaxOfficeName'] ?? ''),
                    'TaxOfficePostCode'     => (string)($r['TaxOfficePostCode'] ?? ''),
                    'TaxOfficeCity'         => (string)($r['TaxOfficeCity'] ?? ''),
                    'TaxOfficeAddress'      => (string)($r['TaxOfficeAddress'] ?? ''),
                    'BanckAccountNumber'    => (string)($r['BanckAccountNumber'] ?? ''),
                    'Phone'                 => (string)($r['Phone'] ?? ''),
                    'Email'                 => (string)($r['Email'] ?? ''),
                    'StartDateCooperation'  => $syncService->validateDate($r['StartDateCooperation'] ?? '', null),
                    'EndDateCooperation'    => $syncService->validateDate($r['EndDateCooperation'] ?? '', null),
                    'ProfileActivities'     => (string)($r['ProfileActivities'] ?? ''),
                    'Description'           => (string)($r['Description'] ?? ''),
                    'Cancelled'             => (int)($r['Cancelled'] ?? 0),
                    'WhenInserted'          => $syncService->validateDate($r['WhenInserted'] ?? '', null),
                    'WhoInserted_UsersID'   => (int)($r['WhoInserted_UsersID'] ?? 0),
                    'WhenUpdated'           => $syncService->validateDate($r['WhenUpdated'] ?? '', null),
                    'WhoUpdated_UsersID'    => (int)($r['WhoUpdated_UsersID'] ?? 0),
                    'LocalizationsID'       => (int)($r['LocalizationsID'] ?? 0),
                    'FileName'              => (string)($r['FileName'] ?? ''),
                    'FileExtension'         => (string)($r['FileExtension'] ?? ''),
                    'UsersID'               => (int)($r['UsersID'] ?? 0),
                    'PositionsDVID'         => (int)($r['PositionsDVID'] ?? 0),
                    'fullName'              => (string)($r['fullName'] ?? ''),
                ];
            },
        ]);
    }
}