# CRM -> Mobile Sync Standard

## Cel

Każda nowa tabela synchronizowana z CRM do bazy mobile musi przejść przez descriptor, dry-run, testy i monitoring. Nie dopisujemy już tabeli wyłącznie przez skopiowanie joba.

## Jak działa sync

Full sync pobiera rekordy stronami po ID:

```text
lastSyncedId -> CRM endpoint -> upsert do mobile -> zapis last_synced_id
```

Incremental sync pobiera rekordy po datach:

```text
whenUpdated >= last_sync_at - buffer
OR whenInserted >= last_sync_at - buffer
```

`last_sync_at` jest osobny per resource w `sync_states`. Historia każdego przebiegu jest w `sync_run_logs`, a błędne rekordy w `sync_record_failures`.

## Checklist Dodania Tabeli

Dla każdej tabeli obowiązkowo określ:

- `resource`: nazwa synca, np. `coursesheadings`
- źródłowa tabela CRM
- docelowa tabela mobile
- primary key w mobile
- pole ID do full sync
- pola dat do incremental sync: standardowo `whenUpdated` i `whenInserted`
- czy tabela ma `cancelled`, `hidden`, `active`, status lub inne soft delete
- wymagane kolumny
- nullable kolumny
- kolumny dat, które mogą przyjść jako `''`, `(null)`, `0000-00-00`, `00.00.0000`
- mapowanie CRM -> mobile
- transformacje typów
- walidacje
- strategię `updateOrCreate`
- test full sync
- test incremental sync
- test brudnej daty
- test required field
- test braku duplikatu

## Descriptor

Nową tabelę dodaj do `config/crm_sync.php`:

```php
'new_resource' => [
    'job' => App\Jobs\PullNewResourceJob::class,
    'source_table' => 'NewCrmTable',
    'target_table' => 'new_resource',
    'endpoint' => '/CrmToMobileSync/getNewResourceMobile',
    'model' => App\Models\NewResource::class,
    'primary_key' => 'newresourceid',
    'api_primary_key' => 'newresourceid',
    'full_sync_id_field' => 'newresourceid',
    'incremental_fields' => ['whenupdated', 'wheninserted'],
    'timestamp_field' => 'whenupdated',
    'date_columns' => ['validfromdate', 'validtodate', 'wheninserted', 'whenupdated'],
    'nullable_columns' => ['validfromdate', 'validtodate'],
    'required_columns' => ['newresourceid'],
    'soft_delete_columns' => ['cancelled'],
    'field_mapping' => 'raw',
    'page_size' => 1000,
    'extra_params' => ['current_LocalizationsID' => '0'],
    'mode' => 'full_then_incremental',
],
```

## Skeleton

1. Migracja: utwórz tabelę mobile z PK, `wheninserted`, `whenupdated`, nullable datami i indeksami.
2. Model: ustaw `$table`, `$primaryKey`, `$guarded = []`, `$timestamps = false`.
3. CRM endpoint: obsłuż `lastSyncedId`, `updatedSince`, `insertedSince`, `pageSize`, `page`.
4. Job: wywołaj `CrmSyncService->sync()` z descriptor-compatible config.
5. Descriptor: dodaj pełny wpis do `config/crm_sync.php`.
6. Test: dodaj przypadki z checklisty.

## Dry Run

```bash
php artisan crm:sync new_resource --dry-run --sample=10
```

Dry-run pobiera próbkę z CRM, waliduje schemat mobile, pokazuje mapowanie, wykrywa brudne daty i puste required fields. Nie zapisuje danych.

## Uruchomienie

```bash
php artisan crm:sync new_resource
php artisan crm:sync new_resource --full
php artisan crm:sync
```

## Monitoring

```bash
php artisan crm:sync:status
php artisan crm:sync:compare new_resource
php artisan crm:sync:compare --all
```

SQL:

```sql
SELECT * FROM sync_states ORDER BY updated_at DESC;

SELECT resource, mode, status, started_at, finished_at,
       fetched_count, processed_count, failed_count, error_message
FROM sync_run_logs
ORDER BY id DESC
LIMIT 100;

SELECT resource, record_id, field, original_value, error_message
FROM sync_record_failures
ORDER BY id DESC
LIMIT 100;
```

## Diagnostyka Brakujących Rekordów

```sql
SELECT s.*
FROM sync_states s
WHERE s.resource = 'new_resource';

SELECT MAX(newresourceid), MAX(whenupdated), COUNT(*)
FROM new_resource;
```

Po stronie CRM sprawdź:

```sql
SELECT COUNT(*), MAX(NewResourceID), MAX(whenUpdated)
FROM NewCrmTable;

SELECT *
FROM NewCrmTable
WHERE whenUpdated >= '2026-05-18 00:00:00'
   OR whenInserted >= '2026-05-18 00:00:00';
```

## Zasady Bezpieczeństwa

- Jeden błędny rekord nie zatrzymuje tabeli.
- Każdy błąd rekordu trafia do `sync_record_failures`.
- `last_sync_at` zapisujemy per tabela i z buforem czasowym.
- Brudne daty normalizujemy przed zapisem.
- Przed pierwszym syncem odpalamy dry-run.
- Nowa tabela bez descriptora nie przechodzi standardu.
