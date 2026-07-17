# Interfejs menedżera

Moduł posiada pięć zakładek Livewire: **Panel**, **Zadania**, **Pracownicy**, **Logi**, **Statystyki**. Przełączanie następuje bez całkowitego restartu ramki menedżera. Zakładkę początkową można przekazać parametrowi zapytania `get`; Nieznana wartość jest zastępowana przez `dashboard`.

## Dostęp

Powłoka modułu i szczegóły zadań mogą być otwierane przez użytkownika menedżera z uprawnieniami `stask`. Wszystkie trasy HTTP znajdują się w grupie middleware `mgr`. Niektóre endpointy akcji polegają wyłącznie na `mgr` middleware i nie dzwonią `hasPermission('stask')` wielokrotnie, więc nie publikuj `/stask/*` poza uwierzytelnieniem menedżera.

## Wspólne reguły tabel EvoUI

Tabele **Zadania**, **Robotnicy**, **Logi** wsparcie:

- wyszukiwanie;
- na stronę: 15, 30, 50, 100, 200 (domyślnie 30);
- widok tabeli/listy;
- sortowanie tylko według kolumn oznaczonych `sortable` w presetach;
- filtry wielowyborcze i zakresowe dat;
- akcje rzędowe;
- podwójne kliknięcie we wszystkich widokach ciągów znaków: w panelu, w Zadaniach i Logach otwiera się szczegóły zadań, a w Pracownikach — edycja modalna.

Na serwerze stosuje się wyszukiwanie i filtry. Widok listy zmienia widok, a nie zbiór danych.

## Panel

![sTask Dashboard z kluczowymi metrykami](../../assets/screenshots/stask-dashboard.png)

### Karty

Panel pokazuje liczby w kolejce, uruchomione, zakończone, nieudane, pracownicy oraz aktywni pracownicy. Następnie następują ostatnie zadania oraz, jeśli są dostępne, osobna tabela ostatnich błędów.

### Ostatnie zadania

Kolumny: ID, Pracownik, Akcja, Status, Postęp, Początek Wykonania, Akcje. Dla aktywnego zadania ciąg otrzymuje URL postępu i okresowo `stask-module.js` odczytuje migawkę bez historii logów (`include_log=0`).

Podwójne kliknięcie lub kliknięcie ikony `eye` otwiera modal z głównymi polami, dziennikiem zadań, meta i wynikiem. Tylko do czytania treści.

### Postęp na żywo

Pasek/wartości postępu i komunikat są aktualizowane za pomocą ankiety HTTP. Bezpieczne wsparcie dla Markdown: backticks, pogrubienie, przekreślenie, podkreślenie. HTML najpierw ucieka. Po zakończeniu końcowego Livewire odświeża panel raz.

## Zadania

![sTask Table](../../assets/screenshots/stask-tasks.png)

### Szukaj

Wyszukiwania według numeru ID, `identifier`, `action` `message`.

### Filtry

- **Worker** to lista przeszukiwalna z `worker->title` ludzkimi, a nie surowymi identyfikatorami.
- **Akcja** — odrębne akcje z bazy danych.
- **Status** — w kolejce, przygotowanie, uruchomienie, zakończenie, niepowodzenie.
- **User** — użytkownicy menedżerski, którzy już wykonali zadania, plus `system` dla `started_by IS NULL OR <= 0`.
- **Zakres tworzenia** — granice obejmujące od początku dnia do końca dnia.

Priorytet i Próby nie są odpowiednimi kolumnami ani filtrami tej zakładki. Pozostają polami runtime/schematu dla kompatybilności, ale nie są udokumentowane jako kontrola interfejsu.

### Kolumny

| Kolumna | Znaczenie |
| --- | --- |
| ID | `#id`; najnowsze pierwsze dla domyślnego |
| Pracownik | Zlokalizowany/ludzki tytuł lub identyfikator awanżowy |
| Akcja | Kod akcji |
| Status | Numeryczna Odznaka Statusowa |
| Postęp | `0–100%`; Aktywny wiersz można aktualizować na żywo |
| Przez Running | nazwa użytkownika lub `system` |
| Wiadomości | utrzymywana wiadomość z bezpiecznym renderowaniem Markdown |
| Początek wykonania | `start_at`; Dla przyszłego zadania kolejkowego jest to zaplanowany czas |
| Zakończone | `finished_at` |

### Działania

- `eye` — szczegóły modalne.
- `player-eject` — awaryjne zatrzymanie dla kolejki/przygotowania/uruchomienia.

Zatrzymanie awaryjne pokazuje status niepowodzenia, `finished_at = now()` oraz komunikat "Zadanie zostało zatrzymane z awarią". Nie kończy on **procesu PHP/OS**. Jeśli proces będzie działał dalej, nadal może zmienić dane lub plik postępów.

Podwójne kliknięcie linii otwiera modal szczegółów.

## Pracownicy

![Rejestr pracowników z harmonogramami i dostępnością przełożonych](../../assets/screenshots/stask-workers.png)

### Wyszukiwanie i filtry

Wyszukiwanie: identyfikator, zakres, klasa. Filtry:

- aktywny/nieaktywny;
- klasa dostępna/brakująca;
- widoczne/ukryte.

### Kolumny

- Identyfikator.
- Worker — tytuł z instancją klasy.
- Opis — fragment do 96 znaków.
- Harmonogram — chip; Dla zdrowego przełożonego pokazuje się Uptime po `niceEta()`.
- Liczba zadań — `niceCount()` (zwarta zlokalizowana liczba).
- Ostatnia akcja.
- Ostatni przebieg — znacznik czasu ostatniego rekordu zadania.

Ukryta jest flaga widoczności menedżera; Rekord pracownika nie jest usuwany. Nieaktywny pracownik nie może zostać uruchomiony, a harmonogramista go pomija.

### Działania paska narzędzi i wierszy

- `database-cog` — odkrywaj + skanuj ponownie + czyść osierocone + czyść pamięć podręczną pracowników.
- `player-play` — rozpoczyna wybrany/pracownik wiersza tylko wtedy, gdy istnieje aktywna, istnieje klasa i jest `taskMake()`.
- `edit` — ustawienia modalne.
- `power` — aktywny przełącznik.
- `eye/eye-off` — przełącznik widoczności.

Rejestr odświeżania może usuwać rekordy, których klasa już nie istnieje. Przed wejściem do produkcji sprawdź, czy Composer autoload jest zakończony i wdrożenie nie jest w stanie pośrednim.

### Pracownik Modalny

Tylko do odczytu: tytuł, identyfikator, zakres, klasa, opis. Edytowalne: aktywne, ukryte, pozycjonowane, harmonogramowe, dodatkowe ustawienia JSON ładunek danych.

Dodatkowy JSON nie powinien zawierać klucza `schedule`: podczas zapisu jest on wyodrębniany i zastępowany wartościami formularza. Nieprawidłowy JSON nie jest przechowywany; Dostawca zostawia poprzednie niestandardowe ustawienia.

Dla zajęć zdolnych do prowadzenia nadzoru modal również pokazuje:

- klucz;
- odznaka stanowa;
- PID;
- bicie serca;
- czas pracy (`niceEta`);
- najnowsza diagnoza;
- Ostatnie przejście.

Opcja supervisor jest ukryta, jeśli klasa nie implementuje `SupervisorWorkerInterface`.

## Logi

![Dziennik postępów zadań](../../assets/screenshots/stask-logs.png)

To nie jest osobna tabela logów: zakładka odczytuje `s_tasks` i pokazuje historię zadań bardziej szczegółowo.

### Wyszukiwanie i filtry

Wyszukiwanie: ID, identyfikator, akcja, wiadomość. Filtry: tytuł pracownika, akcja, status, użytkownik w tym `system`, zakres dat utworzenia.

### Kolumny

ID-link, tytuł pracownika, identyfikator, akcja, status, postęp, rozpoczęte przez, utworzone, start, zakończone, aktualizowane, **Czas pracy**.

**Runtime** używa czasu trwania zadania: dla końcowego zadania `finished_at - start_at`, dla aktywnego zadania `now - start_at`. Formatowanie wykonuje `niceEta()`. Klucz tłumaczenia nazw jest współdzielony z nadzorcą dostępności, ale tutaj jest to czas trwania zadania.

ID otwiera osobną stronę szczegółów. Podwójne kliknięcie otwiera modal tylko do odczytu z klasą komunikatu, meta, rezultatu i pracownika.

## Statystyki

![sStatystyki zadań za ostatnie 24 godziny](../../assets/screenshots/stask-statistics.png)

Pokazuje karty wydajności za ostatnie 24 godziny, alerty i statystyki pamięci podręcznej pracowników.

Realne na temat obecnej implementacji:

- liczba zadań;
- wskaźnik sukcesu/błędów w rejestrach statusu;
- grupowanie pracowników;
- trafienia, pudła, eksmisje, wskaźnik trafień, rozmiar cache;
- Czyszczenie pamięci podręcznej pracowników.

Ograniczenia: `MetricsService` jak dotąd zwraca `0` średniego czasu trwania, średniej pamięci i całkowitego czasu wykonania po agregacji z rekordów zadań; Common Errors to również pusty zastępczy. Nie używaj tych wartości jako produkcyjnego SLI bez zewnętrznej telemetrii.

`niceSize()` jest używany dla czytelnych dla człowieka wartości pamięci, gdzie istnieje rzeczywista wartość bajtu; `niceCount()` — dla hrabiów; `niceEta()` na sekundy/czas trwania.

## Bezpieczeństwo danych

Meta, wynik, komunikat i dziennik postępów są widoczne dla użytkowników zarządzających z dostępem do modułu. Nie udostępniaj haseł, tokenów API, łańcuchów sesji ani danych osobowych, chyba że musisz je pokazać operatorowi. Endpointy przesyłania/pobierania mają walidację specyficzną dla pracownika, ale pracownik musi sprawdzić typ, rozmiar i zawartość pliku.
