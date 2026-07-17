# Diagnostyka

Zacznij od dowodów: odwołanie do pakietu, trasy, migracje, harmonogram, wiersz w bazie danych, plik postępów, dziennik aplikacji. Nie kończ wyłącznie odznaką UI.

## sTask nie jest widoczny w module Dokumentacja

1. Sprawdź fizyczny artefakt:

   ```bash
   test -f core/vendor/seiger/stask/docs/uk/README.md
   ```

2. Sprawdź zamek/źródło:

   ```bash
   cd core
   composer show seiger/stask --all
   ```

3. Upewnij się, że dDocs mają `scan_vendor_packages = 1`.
4. Wyczyść indeks/pamięć podręczną aplikacji.
5. Sprawdź metadane pakietu w `lang/{locale}/global.php`: `module_title`, `module_description`, `module_icon`.

dDocs automatycznie skanuje pakiety `seiger/*`. Nie dodawaj root dostawcy do `extra_docs_roots`: skonfigurowany root projektu może stać się zapisywalny w podglądaczu.

Jeśli Git repozytorium docuje, a `core/vendor` nie, problem leży w Composer lock/dist/deploy, a nie w Markdown index.

## dDocs zawiesza się do indeksowania

Sprawdź ślad stosu w pakiecie dokumentacji firm trzecich. Plik aliasu lokalnego, dostawca usług lub pamięć podręczna mogą się zawieść przed odczytem źródła sTask. To jest osobny dDocs/środowisko z defektem; sTask Docs nie potrafi naprawić czyjegoś bootstrapa.

## Moduł sTask nie jest widoczny

- `composer show seiger/stask`;
- dostawca jest obecny w Package Discovery;
- pozwolenie `stask` przypisaną rolę;
- migracje przeszły;
- pamięć podręczna menedżera zostaje wyczyszczona;
- rejestracja modułów/wtyczek jest realizowana przez instalator Evolution CMS.

Dostawca ma metodę rejestracji menedżera, ale faktyczne wpisanie modułu może być również zarządzane przez instalator/wtyczkę pakietu Evolution. Sprawdź rejestr modułu bazy danych i odkrywanie pakietów, zamiast ręcznie wywoływać metodę chronioną.

## Moduł bez stylów i JavaScriptu

```bash
cd core
php artisan stask:publish
php artisan cache:clear-full
```

W sieci przeglądarkowej sprawdź `stask-module.css`, `stask-module.js`, `stask.min.css`. Jeśli wdrożenie korzysta z systemu plików tylko do odczytu, zasoby muszą być publikowane podczas budowy.

## `stask:worker` nie chce się zapalić

```bash
php -v
php artisan list | grep stask
php artisan route:list --path=stask
```

Pakiet wymaga PHP 8.4. Sprawdź, czy CLI i FPM używają tej samej wersji, `.env`, rozszerzeń i uprawnień.

## Zadanie w kolejce i nie wykonane

Sprawdź:

```sql
SELECT id, identifier, action, status, start_at, created_at
FROM s_tasks
WHERE status IN (10, 30, 50)
ORDER BY id;
```

- cron faktycznie jest wykonywany;
- `start_at` nie w przyszłości;
- pracownik aktywny;
- klasa istnieje i implementuje `TaskInterface`;
- log aplikacji nie zawiera wyjątku dla rozdzielczości;
- nie ma zewnętrznego zamka, który byłby stale zajęty.

## Harmonogram nie tworzy zadania

- `settings.schedule.enabled = true`;
- typ nie `manual`;
- betoniarz ma `taskMake()`;
- nie ma niepełnego zadania tego identyfikatora;
- datetime raz w przyszłości;
- tygodniowy ma `days`;
- Regularne okno ważne i nie nocne;
- `stask:worker` mija co minutę.

## Pracownik się nie pojawia

```bash
composer dump-autoload
php artisan package:discover
```

Następnie odśwież rejestr. Klasa musi być konkretna i w mapie klas Composer. Klasa PSR-4, której Composer nie zoptymalizował w mapie klas, może nie zostać znaleziona przez obecną implementację discovery do autorytatywnego/zoptymalizowanego zrzutu.

Sprawdź `config/excluded_namespaces.php`: duże przestrzenie nazw frameworka są celowo pomijane.

## `WorkerClassNotFound` / `WorkerInvalidInterface`

- nazwa klasy w `s_workers.class` poprawna;
- automatyczne ładowanie jest aktualne;
- klasa nie abstrakcyjna;
- implementacja klas `TaskInterface`;
- konstruktor nie powoduje awarii z powodu zależności bazy danych/ustawień.

Nie klikaj czyść osieroconego podczas częściowego wdrożenia: rekord może zostać usunięty, gdy klasa jest tymczasowo niedostępna.

## Postęp na żywo 404

404 oznacza `storage/stask/{id}.log` że nie znaleziono. Zadanie może być nadal w kolejce lub zapis mógł cicho zawiódć.

```bash
ls -la core/storage/stask
```

Porównaj użytkownika/grupę dla FPM i Cron. Zobacz status bazy/komunikat oraz dziennik aplikacji.

## Postęp zamrożony, zadanie zakończone

Plik postępu tylko do dołączania i nie jest źródłem informacji o ostatecznym statusie bazy danych. Widz na żywo zatrzymuje się na końcowym ciągu na ostatniej linii. Jeśli niestandardowy pracownik sfinalizował bazę danych, ale nie wywołał `markFinished()`/ nie nagrał ostatniego migawki, interfejs użytkownika zaktualizuje się po odświeżeniu Livewire, ale plik może pozostać uruchomiony.

## Zatrzymanie awaryjne nie zatrzymało procesu

To jest oczekiwany limit: akcja umieszcza rekord DB tylko w pozycji failed. Znajdź proces za pomocą telemetrii infrastruktury, zatrzymaj go normalnie, sprawdź skutki uboczne, a następnie uruchom nowe zadanie.

## Pętla restartu nadzorcy

- Startup Grace jest zbyt mały;
- inspekcja nie rozpoznaje rozruchu;
- czas bicia serca jest krótszy niż rzeczywista częstotliwość;
- zmiany odcisków palców przy każdym przejściu poprzez znacznik czasu lub losowy tekst;
- proces również ponownie uruchamia systemd;
- Start/restart nie jest odłączany.

Odcisk palca musi być stabilny, aby postawić tę samą diagnozę.

## Państwo nadzorcze rośnie

```sql
SELECT worker_id, identifier, supervisor_key, COUNT(*)
FROM s_supervisor_states
GROUP BY worker_id, identifier, supervisor_key;
```

Unikalny `key_hash` uniemożliwia wzrost dla tej samej pary, ale nowy identyfikator pracownika lub klucz zmiennej tworzy nowy wiersz. Popraw stabilność klucza do sprzątania.

## Statystyki pokazują zerowy czas trwania/pamięć

Obecna implementacja agregacji zwraca zastępcze zero dla tych wskaźników. To nie oznacza zerowej konsumpcji. Używaj czasu trwania zadania w logach i zewnętrznych APM.

## ArtisanWorker blokuje komendę

Sprawdź `config/artisan_security.php`:

- niebezpieczne komendy są zabronione;
- wymagane potwierdzenie wymaga `confirm=true`;
- biała lista, jeśli nie jest pusta, pozwala tylko na wymienione wzorce;
- blokuje dodatkowe polecenia;
- Wymagana jest zgoda `run_artisan`.

Nie wyłączaj kontroli bezpieczeństwa w produkcji.
