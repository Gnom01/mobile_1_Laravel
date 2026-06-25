# Prompt Angular CRM - Dashboard Banners

Stworz w panelu CRM modul zarzadzania bannerami dashboardu aplikacji mobilnej EDS Flutter. Modul ma pozwalac marketingowi tworzyc, edytowac, ukrywac, usuwac i sortowac bannery widoczne na pulpicie aplikacji.

## Kontekst

- Frontend CRM: Angular 17+.
- Preferuj standalone components, chyba ze istniejacy CRM uzywa klasycznych modules.
- UI: Angular Material + CDK DragDrop.
- Backend Laravel wystawia endpointy CRM pod `/api/crm/dashboard/banners`.
- Nie uzywaj `/api/admin/dashboard/banners`, bo ten backend takiego kontraktu nie wystawia.
- Auth: requesty CRM maja isc z Bearer tokenem CRM, tak samo jak endpointy CRM push.
- Nie pokazuj i nie loguj Bearer tokenu w UI ani konsoli.

## API

Wszystkie requesty:

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

Odpowiedzi `GET`, `POST`, `PUT`, `PATCH` i reorder sa opakowane w `{ data: ... }`; serwis Angular ma mapowac je do modelu domenowego.

## Model TypeScript

```ts
export interface DashboardBanner {
  id: number;
  title: string;
  subtitle: string;
  description: string;
  color_start: string;
  color_end: string;
  action_type: 'offers' | 'payments' | 'schedule' | 'notifications' | 'url' | null;
  action_url: string | null;
  is_active: boolean;
  sort_order: number;
  created_at?: string;
  updated_at?: string;
}

export type CreateBannerDto = Omit<DashboardBanner, 'id' | 'created_at' | 'updated_at'>;
export type UpdateBannerDto = Partial<CreateBannerDto>;
```

## Service

Plik:

```txt
src/app/admin/dashboard-banners/services/dashboard-banner.service.ts
```

Base URL:

```ts
private readonly baseUrl = `${environment.apiUrl}/api/crm/dashboard/banners`;
```

Metody:

```ts
getAll(): Observable<DashboardBanner[]>;
create(dto: CreateBannerDto): Observable<DashboardBanner>;
update(id: number, dto: UpdateBannerDto): Observable<DashboardBanner>;
delete(id: number): Observable<void>;
reorder(items: { id: number; sort_order: number }[]): Observable<DashboardBanner[]>;
```

## BannerListComponent

Sciezka:

```txt
src/app/admin/dashboard-banners/banner-list/
```

Wymagania:

- Lista wszystkich bannerow sortowana po `sort_order`.
- Widok tabela lub lista kart, zgodnie z aktualnym stylem CRM.
- Kolumny: drag handle, podglad gradientu, title, subtitle, action_type, status, sort_order, akcje.
- Primary action: `Dodaj banner`.
- Akcje: edytuj, duplikuj, aktywuj/dezaktywuj, usun.
- Potwierdzenie przed usunieciem.
- Skeleton loader podczas pobierania.
- Snackbar po zapisie, usunieciu i zmianie kolejnosci.
- Obsluga bledow 401 i 422.
- Dezaktywowany banner ma byc przygaszony, ale nadal edytowalny.
- Na malym ekranie przejdz z tabeli na karty.

Mini podglad:

```html
<div
  class="banner-preview"
  [style.background]="'linear-gradient(135deg, ' + banner.color_start + ', ' + banner.color_end + ')'">
  <span class="banner-preview__label">{{ banner.title }}</span>
</div>
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

## BannerFormComponent

Sciezka:

```txt
src/app/admin/dashboard-banners/banner-form/
```

Reactive Forms:

```txt
title        required, maxLength(60)
subtitle     required, maxLength(80)
description  required, maxLength(200)
color_start  required, pattern /^#[0-9A-Fa-f]{6}$/
color_end    required, pattern /^#[0-9A-Fa-f]{6}$/
action_type  null | offers | payments | schedule | notifications | url
action_url   required only when action_type=url, valid URL
is_active    boolean
sort_order   integer, min 0, max 9999
```

Kontrolki:

- `mat-form-field` dla tekstow.
- `textarea` z licznikiem znakow dla `description`.
- Input HEX plus `input type="color"` dla `color_start` i `color_end`.
- `mat-select` dla `action_type`.
- `mat-slide-toggle` dla `is_active`.
- `input type="number"` dla `sort_order`.
- Pole `action_url` pokazuj tylko, gdy `action_type === 'url'`.

Podglad live:

- Pokazuj po prawej stronie formularza lub pod formularzem na mobile.
- Gradient z `color_start` i `color_end`.
- Wymiary desktop okolo `340px x 130px`.
- Tekst nie moze wychodzic poza podglad; dlugie opisy ogranicz do 2-3 linii.

## Routing

```txt
/dashboard-banners
/dashboard-banners/new
/dashboard-banners/:id/edit
```

Jesli CRM preferuje dialog zamiast osobnych stron, mozesz zostawic tylko `/dashboard-banners` i otwierac formularz w `MatDialog` albo drawerze.

Menu:

```txt
Sekcja: Marketing
Etykieta: Banery dashboardu
Ikona: campaign albo dashboard
```

## Acceptance Criteria

- Marketing moze dodac banner bez pracy programisty.
- Marketing moze ustawic kolejnosc przez drag-and-drop.
- `is_active=false` ukrywa banner w mobile API.
- `action_type=url` nie zapisuje sie bez poprawnego `action_url`.
- Kolejnosc w CRM odpowiada kolejnosci w aplikacji mobilnej.
- Panel dziala responsywnie.
- Bearer token nie pojawia sie w logach ani UI.
