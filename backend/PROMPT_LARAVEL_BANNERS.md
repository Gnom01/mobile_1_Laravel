# Prompt Laravel - Dashboard Banners API

Dodaj obsluge bannerow dashboardu aplikacji mobilnej. Mobile pobiera tylko aktywne bannery, a CRM zarzadza pelna lista przez endpointy chronione Bearer tokenem CRM.

## Mobile API

```txt
GET /api/mobile/dashboard/banners
Authorization: Bearer <sanctum_token_uzytkownika>
```

Mobile zwraca tylko rekordy `is_active=true`, sortowane po `sort_order`, potem `id`.

Odpowiedz:

```json
{
  "data": [
    {
      "id": 1,
      "title": "LETNIE OBOZY 2026",
      "subtitle": "Zarezerwuj turnus juz teraz!",
      "description": "Profesjonalni instruktorzy, duzo tanca i wydarzenia.",
      "color_start": "#C40233",
      "color_end": "#E20613",
      "action_type": "offers",
      "action_url": null,
      "is_active": true,
      "sort_order": 10,
      "created_at": "2026-06-03T10:00:00.000000Z",
      "updated_at": "2026-06-03T10:00:00.000000Z"
    }
  ]
}
```

## CRM API

Kazdy endpoint CRM wymaga:

```txt
Authorization: Bearer <CRM_PUSH_API_TOKEN>
```

Endpointy:

```txt
GET    /api/crm/dashboard/banners
POST   /api/crm/dashboard/banners
PUT    /api/crm/dashboard/banners/{id}
PATCH  /api/crm/dashboard/banners/{id}
DELETE /api/crm/dashboard/banners/{id}
PATCH  /api/crm/dashboard/banners/reorder
```

Walidacja `POST`:

```txt
title        required string max:60
subtitle     required string max:80
description  required string max:200
color_start  required hex #RRGGBB
color_end    required hex #RRGGBB
action_type  nullable in: offers,payments,schedule,notifications,url
action_url   nullable url, required gdy action_type=url
is_active    optional boolean
sort_order   optional integer 0..9999
```

Walidacja `PUT` i `PATCH`:

```txt
title        optional string max:60
subtitle     optional string max:80
description  optional string max:200
color_start  optional hex #RRGGBB
color_end    optional hex #RRGGBB
action_type  nullable in: offers,payments,schedule,notifications,url
action_url   nullable url, required gdy action_type=url
is_active    optional boolean
sort_order   optional integer 0..9999
```

Reorder payload:

```json
{
  "items": [
    { "id": 1, "sort_order": 10 },
    { "id": 2, "sort_order": 20 }
  ]
}
```

## Uwagi

- Nie uzywaj `/api/admin/dashboard/banners`; w tym backendzie kontrakt CRM jest pod `/api/crm/dashboard/banners`.
- Nie mieszaj bannerow dashboardu z push notifications.
- `is_active=false` ukrywa banner przed mobile API.
- `action_type=url` wymaga poprawnego `action_url`.
