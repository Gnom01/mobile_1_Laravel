# POST /api/orders — Endpoint tworzenia zamówienia

## Nagłówki (wymagane)

```
Authorization: Bearer <sanctum_token>
Content-Type: application/json
Accept: application/json
```

Token Sanctum uzyskujesz przez `POST /api/login`.

---

## Request body (JSON)

```json
{
  "guid": "550e8400-e29b-41d4-a716-446655440000",
  "payerUserId": 12345,

  "rawSelectedPricing": {
    "productsID": 24768,
    "priceListsTemplatesPositionsID": 464,
    "amount": 4500.00,
    "unitAmount": 450.00,
    "paymentTypesDVID": 2,
    "periodsOfValidityDVID": 1,
    "numberOfUnitsAccount": 10,
    "paymentShedule": [
      {
        "countNumber": 1,
        "paymentDate": "2026-05-13",
        "paymentPositionPrice": 492.19,
        "paymentPositionPriceDiscount": 450.00,
        "isVoid": 0,
        "periodFromDate": "2026-05-01",
        "periodToDate": "2026-05-31",
        "discountCash": 42.19,
        "discountProcent": 9.38,
        "discountValue": { "7": 42.19 },
        "discountFromDate": { "7": "2026-05-01" }
      }
    ]
  },

  "rawCourseData": {
    "coursesHeadingsID": 9439,
    "courseHeadingName": "B-Kuba"
  },

  "payerUser": {
    "firstName": "Jan",
    "lastName": "Kowalski",
    "phone": "500000000",
    "email": "jan@example.com"
  },

  "installments": [
    {
      "amount": 1,
      "paymentDate": "2026-05-13",
      "paymentPositionPrice": 492.19,
      "paymentPositionPriceDiscount": 450.00,
      "discountCash": 42.19,
      "discountProcent": 9.38,
      "periodFromDate": "2026-05-01",
      "periodToDate": "2026-05-31",
      "paymentMonth": "2026-05-01",
      "discountValue": { "7": 42.19 },
      "discountFromDate": { "7": "2026-05-01" }
    }
  ],

  "allInstallmentsPrice": 984.38,
  "entryFee": 0.00,
  "contractStartDate": "2026-05-01",
  "contractEndDate": "2026-06-28",

  "contractSignature": "Nr/XYZ/2026",
  "contractDate": "13-05-2026",

  "groupData": {
    "periodsOfValidityDVID": 1,
    "paymentTypesDVID": 2,
    "paymentDVIDName": "ratalna"
  },

  "payZero": {
    "installmentZero": 492.19,
    "amountZero": 450.00,
    "discountCashZero": 42.19,
    "installmentZeroAfterDiscount": 450.00
  },

  "courseData": {
    "clientsCyti": "Warszawa",
    "contractHeader": "EGURROLA DANCE STUDIO",
    "banckAccountNumber": "00 1234 5678",
    "courseHeadingName": "B-Kuba",
    "Frequency": "2",
    "DurationMin": "120"
  }
}
```

### Pola wymagane (walidacja serwerowa)

| Pole | Typ | Opis |
|---|---|---|
| `guid` | UUID v4 | Klucz idempotencji — wygeneruj raz per próba zapisu, powtórz przy retry |
| `rawSelectedPricing` | object | Wybrany wariant cenowy |
| `rawSelectedPricing.productsID` | int | ID produktu |
| `rawSelectedPricing.priceListsTemplatesPositionsID` | int | ID pozycji cennika |
| `rawSelectedPricing.amount` | decimal | Pełna cena kursu |
| `rawSelectedPricing.unitAmount` | decimal | Wartość raty miesięcznej |
| `rawSelectedPricing.paymentTypesDVID` | int | Typ płatności (słownik) |
| `rawSelectedPricing.periodsOfValidityDVID` | int | Okres ważności (słownik) |
| `rawCourseData` | object | Kontekst kursu |
| `rawCourseData.coursesHeadingsID` | int | ID nagłówka kursu |
| `payerUser` | object | Dane płatnika |
| `payerUser.firstName` | string | |
| `payerUser.lastName` | string | |
| `payerUser.phone` | string | |
| `payerUser.email` | email | |
| `installments` | array (min 1) | Wybrane raty |
| `installments[].paymentDate` | date (YYYY-MM-DD) | Data płatności raty |
| `installments[].amount` | numeric | Kwota |
| `allInstallmentsPrice` | decimal | Suma rat |
| `contractStartDate` | date | Start kontraktu |
| `contractEndDate` | date | Koniec kontraktu (po startDate) |

### Pola opcjonalne

| Pole | Typ | Opis |
|---|---|---|
| `payerUserId` | int | Nadpisuje płatnika (domyślnie = zalogowany user) |
| `entryFee` | decimal | Opłata wpisowa (0 gdy brak) |

### Idempotencja

`guid` to klucz idempotencji UUID v4. Zasady:
- **Generuj nowy UUID** dla każdej nowej próby złożenia zamówienia.
- **Ponów ten sam UUID** gdy sieć zawiedzie i nie wiesz czy serwer zapisał zamówienie.
- Jeśli ten sam `guid` z tym samym payloadem dotrze ponownie → serwer zwróci poprzedni wynik (HTTP 200).
- Jeśli ten sam `guid` z **innym** payloadem → serwer zwróci HTTP 409 `idempotency_conflict`.

---

## Odpowiedzi sukcesu

### HTTP 201 — Nowe zamówienie zapisane

```json
{
  "data": {
    "guid": "550e8400-e29b-41d4-a716-446655440000",
    "status": "local_synced",
    "crm_contracts_id": 12345,
    "crm_payments_id": 11111,
    "payment_token": "tok_abc123",
    "payment_url": "https://pay.egurrola-app.pl/token/tok_abc123",
    "was_already_processed": false
  }
}
```

### HTTP 200 — Zamówienie już istniało (idempotentny retry)

```json
{
  "data": {
    "guid": "550e8400-e29b-41d4-a716-446655440000",
    "status": "local_synced",
    "crm_contracts_id": 12345,
    "crm_payments_id": 11111,
    "payment_token": "tok_abc123",
    "payment_url": "https://pay.egurrola-app.pl/token/tok_abc123",
    "was_already_processed": true
  }
}
```

### Pole `status` — możliwe wartości

| Status | Znaczenie |
|---|---|
| `local_synced` | Zamówienie w CRM + lokalna synchronizacja OK — idealny przypadek |
| `local_sync_failed` | Zamówienie w CRM, synchronizacja lokalna jeszcze trwa (retry w tle) — nadal sukces dla aplikacji, `payment_url` jest dostępny |
| `crm_success` | Zamówienie w CRM, sync jeszcze w toku (rzadkie) |

**Przy `local_synced` i `local_sync_failed` aplikacja powinna otworzyć `payment_url`.**

---

## Odpowiedzi błędów

### HTTP 422 — Błąd walidacji requestu

```json
{
  "message": "The guid field is required.",
  "errors": {
    "guid": ["Pole guid jest wymagane."],
    "rawSelectedPricing.amount": ["The rawSelectedPricing.amount field is required."]
  }
}
```

Standardowa odpowiedź Laravel validation. Klucze `errors` odpowiadają polom requestu.

### HTTP 422 — CRM odrzucił zamówienie (błąd biznesowy)

```json
{
  "message": "Zamówienie odrzucone przez CRM.",
  "code": "crm_order_failed",
  "http_status": 400,
  "crm_errors": ["Kurs jest już pełny.", "Nieprawidłowy typ płatności."]
}
```

Błąd pochodzący z systemu CRM — np. kurs zajęty, niepoprawne dane cennika. **Nie retry automatycznie** — pokaż użytkownikowi `crm_errors`.

### HTTP 409 — Zamówienie w trakcie przetwarzania

```json
{
  "message": "Order 550e8400-... is currently being processed.",
  "code": "order_already_processing"
}
```

Ten sam `guid` jest aktualnie przetwarzany (lock 120s). **Retry po 5–10 sekundach** z tym samym `guid`.

### HTTP 409 — Konflikt idempotencji

```json
{
  "message": "Order 550e8400-... was already submitted with different data.",
  "code": "idempotency_conflict"
}
```

Ten sam `guid` był już wysłany z innym payloadem. **Nie retry** — wygeneruj nowy `guid`.

### HTTP 503 — Serwis CRM niedostępny

```json
{
  "message": "Serwis zamówień jest chwilowo niedostępny. Spróbuj ponownie.",
  "code": "crm_integration_error"
}
```

Problem techniczny po stronie CRM (5xx, timeout). **Retry z tym samym `guid`** po 30s.

### HTTP 401 — Brak lub wygasły token

```json
{
  "message": "Unauthenticated."
}
```

Token Sanctum wygasł lub brak nagłówka `Authorization`. Odśwież token przez `/api/login`.

---

## Logika retry w Flutter (zalecana)

```dart
// Pseudokod — przykładowy algorytm
final guid = Uuid().v4();               // generuj JEDEN raz per zamówienie

for (int attempt = 1; attempt <= 3; attempt++) {
  final response = await api.postOrder(guid: guid, payload: payload);

  switch (response.statusCode) {
    case 201:
    case 200:
      // sukces — otwórz payment_url
      openBrowser(response.data['payment_url']);
      return;

    case 409 when response.code == 'order_already_processing':
      // poczekaj i spróbuj ponownie Z TYM SAMYM guid
      await Future.delayed(Duration(seconds: 5 * attempt));
      continue;

    case 503:
      // poczekaj dłużej i spróbuj Z TYM SAMYM guid
      if (attempt < 3) {
        await Future.delayed(Duration(seconds: 30));
        continue;
      }
      showError('Serwis niedostępny. Spróbuj za chwilę.');
      return;

    case 422 when response.code == 'crm_order_failed':
      // błąd biznesowy — pokaż crm_errors, NIE retry
      showError(response.crmErrors.join('\n'));
      return;

    case 409 when response.code == 'idempotency_conflict':
      // NIE retry z tym guid — loguj błąd
      reportError('Idempotency conflict: $guid');
      return;

    default:
      showError('Nieznany błąd: ${response.statusCode}');
      return;
  }
}
```

---

## Przykład curl

```bash
curl -X POST https://api.egurrola-app.pl/api/orders \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{
    "guid": "550e8400-e29b-41d4-a716-446655440000",
    "rawSelectedPricing": {
      "productsID": 24768,
      "priceListsTemplatesPositionsID": 464,
      "amount": 4500.00,
      "unitAmount": 450.00,
      "paymentTypesDVID": 2,
      "periodsOfValidityDVID": 1
    },
    "rawCourseData": { "coursesHeadingsID": 9439 },
    "payerUser": {
      "firstName": "Jan", "lastName": "Kowalski",
      "phone": "500000000", "email": "jan@example.com"
    },
    "installments": [
      { "amount": 1, "paymentDate": "2026-05-13" }
    ],
    "allInstallmentsPrice": 984.38,
    "contractStartDate": "2026-05-01",
    "contractEndDate": "2026-06-28"
  }'
```
