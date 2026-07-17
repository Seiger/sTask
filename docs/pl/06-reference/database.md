# Tabele bazy danych

Poniżej przedstawiono rzeczywiste migracje schematu gałęzi 2.x.

## `s_workers`

| Kolumna | Typ/atrybuty | Cel |
| --- | --- | --- |
| `id` | BIGINT PK | wewnętrzny identyfikator pracownika |
| `uuid` | UUID nullable unique | opcjonalna zewnętrzna tożsamość |
| `identifier` | string unique | Stabilny klucz routingu zadań |
| `scope` | domyślny ciąg `''` | grupowanie pakietów/modułów |
| `class` | ciąg | FQCN |
| `active` | domyślnie booleowska fałszywa | Scheduler/Run Gate |
| `position` | unsigned int domyślnie 0 | Kolejność interfejsu |
| `settings` | Domyślne wyrażenie pustej tablicy JSON | harmonogram/ustawienia niestandardowe |
| `hidden` | unsigned int domyślnie 0 | Widoczność interfejsu użytkownika |
| Znaczniki czasu | stworzony/zaktualizowany | Audyt |

Indeksy: unikalny UUID, unikalny identyfikator, plus identyfikator, zakres, aktywny, indeksy pozycji. Unikalny identyfikator już tworzy indeks; dodatkowy indeks jawny może być redundantny w zależności od bazy danych.

## `s_tasks`

| Kolumna | Typ/atrybuty | Cel |
| --- | --- | --- |
| `id` | BIGINT PK | ID zadania |
| `identifier` | ciąg | Klucz routingu pracowników |
| `action` | ciąg | Kod akcji |
| `status` | unsigned small int domyślnie 10 | Cykl życia |
| `message` | tekst do zerowania | Trwałe podsumowanie/błąd |
| `started_by` | unsigned int nullable | Manager User lub System |
| `meta` | longText nullable | metadane wejściowe |
| `result` | longText nullable | ładunek wyniku/ścieżka |
| `start_at` | znacznik czasu nullable | zaplanowany/faktyczny start |
| `finished_at` | znacznik czasu nullable | Ostatni raz |
| `attempts` | int domyślnie 0 | zwiększone podczas działania |
| `max_attempts` | int domyślnie 3 | Spróbuj ponownie metadanych |
| `priority` | domyślny ciąg normalny | kompatybilność/kolejka |
| `progress` | int domyślnie 0 | Postęp ciągły |
| Znaczniki czasu | stworzony/zaktualizowany | Audyt |

Indeksy: `(identifier, action)`, status, started_by, start_at, created_at, priorytet.

Nie ma klucza obcego od identyfikatora zadania do `s_workers.identifier`, więc historia przetrwała usunięcie rekordu pracownika. Relacja działa logicznie według identyfikatora.

Meta/result przedstawia model Eloquent jako tablicę; rzeczywista pamięć to długi tekst, nie natywny JSON.

## `s_supervisor_states`

| Kolumna | Typ/atrybuty | Cel |
| --- | --- | --- |
| `id` | BIGINT PK | ID wiersza stanu |
| `worker_id` | nieoznaczony BIGINT, indeksowany | właściciel `s_workers.id` bez FK |
| `identifier` | ciąg, indeksowany | zdenormalizowany klucz worker |
| `supervisor_key` | ciąg | Tożsamość procesu adaptacyjnego |
| `key_hash` | char(64) unique | sha256 ID pracownika + klucz |
| `state` | string(24), domyślnie zatrzymany, indeksowany | Stan cyklu życia |
| `pid` | unsigned BIGINT nullable | ID procesu |
| `heartbeat_at` | znacznik czasu nullable | ostatnie uderzenie serca |
| `supervisor_started_at` | znacznik czasu nullable | Początek procesu |
| `uptime_seconds` | unsigned BIGINT nullable | Uptime adaptera |
| `message` | tekst do zerowania | diagnostyka |
| `fingerprint` | char(64) nullable | Zedupowany odcisk palca |
| `last_transition_at` | znacznik czasu nullable | przejście stanu |
| `last_seen_at` | timestamp nullable, indeksowany | Ostatnia obserwacja planisty |
| `repeat_count` | unsigned int domyślnie 0 | powtarzające się liczenie odcisków palców |
| `launch_requested_at` | znacznik czasu nullable | Kursor Grace Startup |
| Znaczniki czasu | stworzony/zaktualizowany | Audyt rekordu |

### Cykl życia

`firstOrNew(key_hash)` gwarantuje jeden żyjący wiersz na każdy identyfikator pracownika + klucz stabilny. Każde przejście aktualizuje ostatnie widziane; Ten sam odcisk palca zwiększa liczbę powtórzeń, nowy odcisk resetuje ją do zera. Aktualizacje przejścia stanowego `last_transition_at`.

Zdrowy stan oczyszcza `launch_requested_at`. Inne statusy zachowują poprzednie lub obecne żądanie startu.

### Wzrost i oczyszczenie

Historia bicia serca nie gromadzi wierszy. Wzrost oznacza nowe identyfikatory pracowników lub klucze. Nie ma automatycznego zatrzymania, nie ma obcego klucza/kaskady.

Bezpieczne sprzątanie:

1. inwentaryzuje rzeczywiste pracowników i klucze adapterów;
2. Upewnij się, że Daemon ze Starym Kluczem nie zadziała;
3. archiwizować diagnostykę, jeśli jest to konieczne;
4. Usuń tylko dokładne identyfikatory/skróty osierocone.

Nie rób szerokiego `TRUNCATE` podczas aktywnego harmonogramu.

## Tabele zezwoleń

Migracja, jeśli istnieją tabele systemowe:

- znajduje/tworzy grupę `sTask`;
- Pozwolenie na Upsert-IT `stask` z `disabled = 0`;
- dodaje role_permissions dla roli `1`;
- w PostgreSQL jest w stanie przywrócić sekwencję po konflikcie wstawienia.

Migracja wyłącza owijacz transakcji Laravel, ponieważ PostgreSQL przerywa transakcję po nieudanych instrukcjach, a kod ma ścieżkę ponownego próbowania.

## Przenośność

Schema koncentruje się na MySQL/MariaDB/PostgreSQL/SQLite poprzez Laravel Schema Builder. Domyślne wyrażenie JSON `JSON_ARRAY()` jest wrażliwe na bazę danych; Uruchom testy migracji na docelowym silniku. Migracja uprawnień obsługuje sekwencję PostgreSQL osobno.
