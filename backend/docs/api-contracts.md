# GET /api/contracts/{parentGuid} — Lista umów użytkownika

Zwraca wszystkie aktywne umowy dla wskazanego użytkownika **oraz jego osób powiązanych**
(dzieci/podopiecznych). Dla każdej umowy zwracany jest zagnieżdżony harmonogram rat (`installments`).

---

## Nagłówki (wymagane)

```
Authorization: Bearer <sanctum_token>
Accept: application/json
```

---

## Parametr URL

| Parametr     | Typ    | Opis                                     |
|--------------|--------|------------------------------------------|
| `parentGuid` | string | GUID zalogowanego użytkownika (UUID v4)  |

---

## Odpowiedź (200 OK)

```json
{
  "success": true,
  "body": [
    {
      "contractsID": 71080,
      "parent_ContractsID": 71098,
      "sellingParent_ContractsID": 0,
      "usersID": 4,
      "contracts_UsersID": 4,
      "payer_UsersID": 4,
      "contractSygnature": "4043/BL",
      "contractsTypesDVID": 1,
      "contractStatusesDVID": 5,
      "contractStatusName": "Nieaktywna",
      "contractsPatternsID": 1,
      "contractPatternName": "Umowa",
      "productsID": 32930,
      "productName": "PŁATNOŚĆ CYKLICZNA (MIESIĘCZNIE)",
      "paymentName": "ratalna",
      "courseHeadingName": "BLUE-M1",
      "coursesHeadingsID": 14346,
      "contracConclusionDate": "2026-05-11",
      "contractPeriodFrom": "2026-01-01",
      "contractPeriodTo": "2026-06-28",
      "contractPeriodFromOld": "2026-01-01",
      "contractAmount": 1353.85,
      "entryFee": 99,
      "localizationsID": 99,
      "groupLocation": 99,
      "localizationsName": "Warszawa - Blue City",
      "durationInMinutesDVID": 0,
      "cancelled": 0,
      "note": "",
      "expirationDate": "2026-01-02 00:00:00",
      "userFirstName": "Piotr",
      "userLastName": "Test Strózik",
      "fullNameEDS": "Test Strózik Piotr",
      "contractForUser": "Test Strózik Piotr",
      "userAddress": "Żwirki i Wigury 99a/1",
      "userPostCode": "09-855",
      "userCity": "T-test",
      "userIdentityNumber": "",
      "userPESEL": "64090918166",
      "userPhone": "502072626",
      "userEmail": "multibrend01@gmail.com",
      "payerFirstName": "Piotr",
      "payerLastName": "Test Strózik",
      "payerName": "Test Strózik Piotr",
      "payerAddress": "Żwirki i Wigury 99a/1",
      "payerPostCode": "09-855",
      "payerCity": "T-test",
      "payerIdentityNumber": "",
      "payerPESEL": "64090918166",
      "payerPhone": "502072626",
      "payerEmail": "multibrend01@gmail.com",
      "dateOfBirdth": "1964-09-09",
      "paymentTypesDVID": 2,
      "productsLevel2DVID": 1,
      "productsLevel3DVID": 2,
      "durationMin": 75,
      "whenUpdated": "2026-05-12",
      "usersPaymentsSchedulesID": null,
      "installments": {
        "status": "200",
        "message": "",
        "recordCount": 6,
        "body": [
          {
            "positionName": "BLUE-M1 od 2026-01-01 do 2026-01-31",
            "paymentDate": "2026-05-11",
            "instalmentNumber": 1,
            "paymentAmount": 153.85,
            "month_no": 5,
            "monthName": "Maj",
            "fullNameEDS": "",
            "paymentTypesDVID": 0,
            "contractsID": 0,
            "parent_ContractsID": 0,
            "sellingParent_ContractsID": 0,
            "contracts_UsersID": 0,
            "contractPeriodFrom": "",
            "contractSygnature": "",
            "productsID": 0,
            "entryFee": 0,
            "contractsTypesDVID": 0,
            "contracConclusionDate": "",
            "contractAmount": 0,
            "contractStatusesDVID": 0,
            "contractStatusName": "",
            "contractsPatternsID": 0,
            "courseHeadingName": "",
            "coursesHeadingsID": 0,
            "contractPatternName": "",
            "productName": "",
            "paymentName": "",
            "contractPeriodTo": "",
            "usersID": 0,
            "dateOfBirdth": "",
            "userPhone": "",
            "installments": "",
            "userEmail": "",
            "durationInMinutesDVID": 0,
            "userFirstName": "",
            "userLastName": "",
            "userAddress": "",
            "userPostCode": "",
            "userCity": "",
            "userIdentityNumber": "",
            "userPESEL": "",
            "payer_UsersID": 0,
            "payerFirstName": "",
            "payerLastName": "",
            "payerPostCode": "",
            "payerAddress": "",
            "payerIdentityNumber": "",
            "payerCity": "",
            "payerPhone": "",
            "payerEmail": "",
            "payerPESEL": "",
            "localizationsID": 0,
            "contractPeriodFromOld": "",
            "localizationsName": "",
            "productsLevel2DVID": 0,
            "productsLevel3DVID": 0,
            "note": "",
            "contractHeader": "",
            "groupLocation": 0,
            "productsLevel2": 0,
            "productsLevel3": 0,
            "durationMin": "",
            "frequency": "",
            "debt": 0,
            "contractForUser": "",
            "cancelled": 0,
            "payerName": "",
            "whenUpdated": ""
          }
        ]
      }
    }
  ]
}
```

---

## Błędy

| HTTP | `error`          | Opis                                              |
|------|------------------|---------------------------------------------------|
| 401  | `UNAUTHORIZED`   | Brak lub nieprawidłowy token Sanctum              |
| 403  | `FORBIDDEN`      | `parentGuid` nie należy do zalogowanego usera ani jego rodziny |
| 404  | `USER_NOT_FOUND` | Nie znaleziono użytkownika o podanym GUID         |

```json
{ "success": false, "error": "FORBIDDEN" }
```

---

## Logika pobierania

- Kontrakty zwracane są dla: **target user** (parentGuid) + wszystkich jego **powiązanych UsersID**
  (`usersrelations` gdzie `Parent_UsersID = targetUser.UsersID`)
- Filtr: `contracts.cancelled = 0`
- `installments.body` — harmonogram rat z tabeli `userspaymentsschedules` filtrowany po `contractsID`
- Kolejność: `contractsID DESC`

---

## Dart — przykład wywołania

```dart
import 'dart:convert';
import 'package:http/http.dart' as http;

class ContractService {
  final String baseUrl;
  final String token;

  ContractService({required this.baseUrl, required this.token});

  Future<List<ContractModel>> getContracts(String parentGuid) async {
    final uri = Uri.parse('$baseUrl/api/contracts/$parentGuid');

    final response = await http.get(
      uri,
      headers: {
        'Authorization': 'Bearer $token',
        'Accept': 'application/json',
      },
    );

    if (response.statusCode == 200) {
      final data = jsonDecode(response.body);
      final List<dynamic> body = data['body'] ?? [];
      return body.map((e) => ContractModel.fromJson(e)).toList();
    }

    if (response.statusCode == 403) {
      throw Exception('Brak dostępu do danych tego użytkownika');
    }

    throw Exception('Błąd pobierania umów: ${response.statusCode}');
  }
}
```

---

## Dart — model danych

```dart
class ContractModel {
  final int contractsID;
  final int parentContractsID;
  final int usersID;
  final int payerUsersID;
  final String contractSygnature;
  final int contractStatusesDVID;
  final String contractStatusName;
  final String productName;
  final String paymentName;
  final String courseHeadingName;
  final int coursesHeadingsID;
  final String? contracConclusionDate;
  final String? contractPeriodFrom;
  final String? contractPeriodTo;
  final double contractAmount;
  final double entryFee;
  final int localizationsID;
  final String localizationsName;
  final String fullNameEDS;
  final String userFirstName;
  final String userLastName;
  final String userPhone;
  final String userEmail;
  final String payerFirstName;
  final String payerLastName;
  final String payerPhone;
  final String payerEmail;
  final String? dateOfBirdth;
  final String? whenUpdated;
  final ContractInstallments installments;

  const ContractModel({
    required this.contractsID,
    required this.parentContractsID,
    required this.usersID,
    required this.payerUsersID,
    required this.contractSygnature,
    required this.contractStatusesDVID,
    required this.contractStatusName,
    required this.productName,
    required this.paymentName,
    required this.courseHeadingName,
    required this.coursesHeadingsID,
    this.contracConclusionDate,
    this.contractPeriodFrom,
    this.contractPeriodTo,
    required this.contractAmount,
    required this.entryFee,
    required this.localizationsID,
    required this.localizationsName,
    required this.fullNameEDS,
    required this.userFirstName,
    required this.userLastName,
    required this.userPhone,
    required this.userEmail,
    required this.payerFirstName,
    required this.payerLastName,
    required this.payerPhone,
    required this.payerEmail,
    this.dateOfBirdth,
    this.whenUpdated,
    required this.installments,
  });

  factory ContractModel.fromJson(Map<String, dynamic> json) {
    return ContractModel(
      contractsID:         json['contractsID']         as int,
      parentContractsID:   json['parent_ContractsID']  as int,
      usersID:             json['usersID']             as int,
      payerUsersID:        json['payer_UsersID']       as int,
      contractSygnature:   json['contractSygnature']   as String,
      contractStatusesDVID: json['contractStatusesDVID'] as int,
      contractStatusName:  json['contractStatusName']  as String? ?? '',
      productName:         json['productName']         as String,
      paymentName:         json['paymentName']         as String,
      courseHeadingName:   json['courseHeadingName']   as String,
      coursesHeadingsID:   json['coursesHeadingsID']   as int,
      contracConclusionDate: json['contracConclusionDate'] as String?,
      contractPeriodFrom:  json['contractPeriodFrom']  as String?,
      contractPeriodTo:    json['contractPeriodTo']    as String?,
      contractAmount:      (json['contractAmount'] as num).toDouble(),
      entryFee:            (json['entryFee']       as num).toDouble(),
      localizationsID:     json['localizationsID'] as int,
      localizationsName:   json['localizationsName'] as String? ?? '',
      fullNameEDS:         json['fullNameEDS']      as String? ?? '',
      userFirstName:       json['userFirstName']    as String,
      userLastName:        json['userLastName']     as String,
      userPhone:           json['userPhone']        as String,
      userEmail:           json['userEmail']        as String,
      payerFirstName:      json['payerFirstName']   as String,
      payerLastName:       json['payerLastName']    as String,
      payerPhone:          json['payerPhone']       as String,
      payerEmail:          json['payerEmail']       as String,
      dateOfBirdth:        json['dateOfBirdth']     as String?,
      whenUpdated:         json['whenUpdated']      as String?,
      installments: ContractInstallments.fromJson(
        json['installments'] as Map<String, dynamic>,
      ),
    );
  }
}

class ContractInstallments {
  final String status;
  final int recordCount;
  final List<InstallmentItem> body;

  const ContractInstallments({
    required this.status,
    required this.recordCount,
    required this.body,
  });

  factory ContractInstallments.fromJson(Map<String, dynamic> json) {
    final rawBody = json['body'];
    final List<InstallmentItem> items = (rawBody is List)
        ? rawBody.map((e) => InstallmentItem.fromJson(e as Map<String, dynamic>)).toList()
        : [];
    return ContractInstallments(
      status:      json['status']      as String? ?? '',
      recordCount: json['recordCount'] as int? ?? 0,
      body:        items,
    );
  }
}

class InstallmentItem {
  final String positionName;
  final String paymentDate;
  final int instalmentNumber;
  final double paymentAmount;
  final int monthNo;
  final String monthName;

  const InstallmentItem({
    required this.positionName,
    required this.paymentDate,
    required this.instalmentNumber,
    required this.paymentAmount,
    required this.monthNo,
    required this.monthName,
  });

  factory InstallmentItem.fromJson(Map<String, dynamic> json) {
    return InstallmentItem(
      positionName:     json['positionName']    as String,
      paymentDate:      json['paymentDate']     as String,
      instalmentNumber: json['instalmentNumber'] as int,
      paymentAmount:    (json['paymentAmount']  as num).toDouble(),
      monthNo:          json['month_no']        as int,
      monthName:        json['monthName']       as String,
    );
  }
}
```

---

## Uwagi implementacyjne

- Pola w `installments.body` inne niż `positionName`, `paymentDate`, `instalmentNumber`,
  `paymentAmount`, `month_no`, `monthName` mają wartości zerowe/puste — są zgodne ze strukturą
  CRM ale nie niosą danych; Flutter może je ignorować.
- `contractStatusName` może być `null` jeśli słownik nie zawiera wpisu dla danego `contractStatusesDVID`.
- `durationMin` to liczba minut trwania zajęć (np. `75`) lub `null`.
- `usersPaymentsSchedulesID` na poziomie kontraktu jest zawsze `null` — ID harmonogramu dostępne
  jest tylko w elementach `installments.body`.
