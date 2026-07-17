# Architektura i cykl życia

## Składniki

| Komponent | Odpowiedzialność |
| --- | --- |
| `sWorker` | Rejestr logowania pracownika, klasa, aktywne/ukryte, pozycja, ustawienia JSON |
| `TaskInterface` | minimalna metadane/pracownik kontraktowy UI |
| `BaseWorker` | konfiguracja, harmonogramy, tworzenie zadań, wysyłka akcji, postęp i finalizacja |
| `sTaskModel` | trwałe zadanie, status, meta/wynik, znaczniki czasu i zakresy |
| `TaskWorker` | Tworzenie zaplanowanych zadań i kolejne wykonywanie gotowej kolejki |
| `TaskProgress` | Postęp na żywo tylko z dołączeniem w `storage/stask/{id}.log` |
| `WorkerDiscovery` | wyszukaj konkretne klasy `TaskInterface` w Composer classmap |
| `WorkerService` | Instancje Workerów Resolution, Validation i Cache |
| `SupervisorService` | Serializowana inspekcja/start/restart procesów długoterminowych |
| EvoUI + Livewire | tabele menedżerskie, filtry, okna modalne i postęp ankietowania HTTP |

## Warunki

- **Worker** to klasa PHP, która wie, jak wykonać jedną lub więcej akcji.
- **Zadanie** — trwała próba wykonania akcji określonego identyfikatora pracownika.
- **Action** to struna podobna do `make` lub `sync_stock`; `BaseWorker` zamienia go w `taskMake()` lub `taskSyncStock()`.
- **Schedule** to JSON w `s_workers.settings.schedule`, który `stask:worker` obsługuje kolejkowe zadanie.
- **Progress** — zmienny snapshot/historia w pliku `.log`; Pole `s_tasks.progress` nie jest automatycznie synchronizowane przez każdy `pushProgress()`.
- **Metadane (`meta`)** — znormalizowane parametry wejściowe zadania, cast model `array`.
- **Wynik** — końcowy ładunek lub ścieżka zarejestrowana przez pracownika; Typ bazy danych `LONGTEXT`, model odlewany `array`.
- **Message** to krótki, utrzymujący się stan/błąd w `s_tasks.message`; Historia na żywo jest przechowywana osobno.

## Stworzenie

Istnieją dwa główne sposoby:

1. `sTask::create($identifier, $action, $data, $priority, $userId)`.
2. `$worker->createTask($action, $options)` w `BaseWorker`.

Zarówno normalizują meta poprzez rekurencyjne sortowanie kluczy asocjacyjnych, jak i szukają aktywnego duplikatu przez `identifier + action + normalized meta`. Status aktywny `10`, `30`, `50`.

Chroni to tylko przed identycznymi aktywnymi rekordami. Nie jest to globalna blokada rozproszona i nie zastępuje operacji biznesowych.

## Egzekucja

`stask:worker` postępuje zgodnie z następującymi krokami:

1. odczytuje wszystkich aktywnych pracowników;
2. dla włączonych harmonogramów tworzy brakujące zadania uzupełniające lub wykonuje przejście nadzorcze;
3. Wybiera wszystkie zadania w kolejce, gdzie `start_at IS NULL OR start_at <= now()`;
4. Dla każdego zadania znajduje się Aktywny Rejestr Pracownika i Klasa;
5. sprawdzanie `TaskInterface`;
6. stawia status `running`, `start_at = now()`, wzrasta `attempts`;
7. powoduje `invokeAction()`;
8. Jeśli pracownik nie zakończył zadania, ustala `finished` automatycznie;
9. Z wyjątkiem ustawia `failed` i rejestruje postęp błędów;
10. Gdy nie ma aktywnych zadań, usuwa pliki postępów starsze niż 24 godziny.

Obecne polecenie ładuje wszystkie gotowe wiersze w kolejce bez limitu wsadowego i przetwarza je sekwencyjnie. Zaplanuj objętość tak, aby jeden przejazd crona nie zamarł na czas nieskończony.

## Status

| Kod | Stała | Tekst | Znaczenie |
| ---: | --- | --- | --- |
| 10 | `TASK_STATUS_QUEUED` | `pending` | Czas oczekiwania/Przepustka dla pracowników |
| 30 | `TASK_STATUS_PREPARING` | `preparing` | stan pośredni dla kodu aplikacji |
| 50 | `TASK_STATUS_RUNNING` | `running` | Akcja jest aktywna lub uznawana za aktywną |
| 80 | `TASK_STATUS_FINISHED` | `completed` | Udane zwycięstwo w finale |
| 100 | `TASK_STATUS_FAILED` | `failed` | Błąd lub zatrzymanie awaryjne |

Nie ma osobnego, utrzymującego się statusu anulowania. `isFinished()` zwraca `true` tylko dla `80` i `100`.

## Znaczniki czasu i czas trwania

- `created_at` — kiedy utworzono rekord.
- `start_at` to planowany czas przed wykonaniem, a po `markAsRunning()` faktyczny początek.
- `finished_at` — finalizacja.
- `updated_at` jest ostatnią zmianą do zapisu.
- `duration` accessor — sekundy od `start_at` do `finished_at`; dla zadania bieżącego, od `start_at` do obecnego czasu.

Ze względu na wartość podwójnej `start_at` , nadchodzące zadanie w kolejce pokazuje zaplanowany start, a ukończone zadanie pokazuje rzeczywisty zestaw startowy przy starcie.

## Spróbuj jeszcze raz

`markAsRunning()` rośnie `attempts`. `canRetry()` prawda dla nieudanego zadania, o ile `attempts < max_attempts`. Jednak standardowy `TaskWorker` nie zwraca automatycznie nieudanego zadania w kolejce i nie posiada osobnego polecenia powtórki. Polityka powtórek musi być wdrożona przez integratora lub pracownika. Nie obiecuję użytkownikom automatycznych nawrotów zaraz po `max_attempts = 3`.

## Postęp i live UI

`pushProgress()` dodaje pojedynczy ciąg rozdzielony piszczówką do `.log`. Obserwator JavaScript:

- startuje w odstępach 1,2 sekundy;
- zwiększa opóźnienie do 25 sekund, jeśli migawka się nie zmienia;
- sprawdza co 5 sekund w zakładce ukryte/niewidzialne;
- nie wykonuje równoległych żądań;
- zatrzymuje się w `finished`, `failed`, `completed` lub po pięciu awariach sieci;
- po ostatecznym statusie prosi Livewire o odświeżenie powierzchni.

To jest ankieta HTTP, a nie transport push.
