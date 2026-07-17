# Trasy, pliki postępów i pliki do pobrania

## Status API

Wszystkie poniższe trasy znajdują się w grupie menedżerów middleware `mgr`. To jest wewnętrzna powierzchnia HTTP menedżera, a nie publiczne API REST dla zewnętrznych klientów. Używaj uwierzytelniania sesyjnego i CSRF do POST.

## Trasy

| Metoda | Ścieżka | Nazwa trasy | Cel |
| --- | --- | --- | --- |
| GET | `/stask` | `sTask.index` | powłoka modułu |
| GET | `/stask/stats` | `sTask.stats` | hrabie |
| POST | `/stask/task` | `sTask.task.create` | Tworzenie zadania fasady |
| POST | `/stask/task/store` | `sTask.task.store` | alias create |
| GET | `/stask/task/{id}` | `sTask.task.show` | pełne szczegóły zadania |
| POST | `/stask/worker/{identifier}/run/{action}` | `sTask.worker.task.run` | Stwórz + Uruchom Pracownika |
| GET | `/stask/task/{id}/progress` | `sTask.task.progress` | Migawka/historia postępów |
| GET | `/stask/task/{id}/download` | `sTask.task.download` | Pobierz wynik |
| POST | `/stask/task/{id}/upload` | `sTask.task.upload` | przesyłanie zadaniowe |
| POST | `/stask/worker/{identifier}/upload` | `sTask.worker.upload` | Upload przed zadaniem |
| POST | `/stask/clean` | `sTask.clean` | usuń stare ukończone zadania |
| GET | `/stask/server-limits` | `sTask.serverLimits` | Limity przesyłania PHP |
| GET | `/stask/workers` | `sTask.workers` | odkryj i przekieruj |
| POST | `/stask/worker/clean-orphaned` | `sTask.worker.clean` | usuń brakujące klasy |
| POST | `/stask/worker/activate` | `sTask.worker.activate` | aktywuj według identyfikatora |
| POST | `/stask/worker/deactivate` | `sTask.worker.deactivate` | dezaktywacja według identyfikatora |
| GET | `/stask/performance/summary` | `sTask.performance.summary` | Podsumowanie metryk |
| GET | `/stask/performance/workers` | `sTask.performance.workers` | statystyki pracowników |
| GET | `/stask/performance/alerts` | `sTask.performance.alerts` | Alerty |
| GET | `/stask/cache/stats` | `sTask.cache.stats` | statystyki pamięci podręcznej pracowników |
| POST | `/stask/cache/clear` | `sTask.cache.clear` | wyczyść pamięć roboczą |

## Akcja startowa

```http
POST /stask/worker/search_index/run/make
Content-Type: application/json
X-CSRF-TOKEN: ...

{"batch_size":100,"force":false}
```

Kontroler przyjmuje zagnieżdżone `options` lub całe ciało, usuwa `_token` i `options`, rozwiązuje aktywnego pracownika i wywołuje `createTask()`.

Odpowiedź z powodzeniem:

```json
{"success":true,"id":123,"message":"Task created successfully"}
```

Kod HTTP może pozostać 200 nawet przy `success=false`; klient powinien sprawdzić flagę JSON.

Po tym, jak kontroler odpowiedzi używa `fastcgi_finish_request()` lub synchronicznego backupu, próbuje uruchomić `stask:worker`. Nie gwarantuje to osobnego procesu kolejkowego we wszystkich SAPI.

## Punkt końcowy postępu

```http
GET /stask/task/123/progress?include_log=0
Accept: application/json
```

Success:

```json
{
  "success": true,
  "code": 200,
  "id": 123,
  "status": "running",
  "progress": 42,
  "processed": 420,
  "total": 1000,
  "eta": "37s",
  "message": "Przetwarzam partię",
  "log_lines": []
}
```

Bez `include_log=0` endpoint dodaje ostatnie 50 wiadomości. Nieprawidłowy identyfikator zwraca 400; Brakujący plik postępów — 404.

## Format pliku

Ścieżka:

```text
core/storage/stask/{taskId}.log
```

Każda linia dostępna tylko do dołączania:

```text
status|progress|processed|total|eta|message
```

`readProgress()` czyta ostatnią poprawną linię; `readLog()` wyodrębnia wiadomość z ostatnich N wierszy. Celowa niepowiażdżka zapisu nie powoduje awarii zadania biznesowego, więc brak postępu na żywo nie dowodzi, że zadanie nie zostało ukończone.

## Sprzątanie

Gdy brakuje zadań w kolejce, przygotowania lub uruchomienia, `stask:worker` usuwa `*.json` starsze niż 24 godziny, a tymczasowe JSON starsze niż 10 godzin. Obecny `TaskProgress` faktycznie używa `*.log`, więc te pliki logów nie są automatycznie usuwane przez tę pętlę. Po uzgodnieniu z wymaganiami audytu ustalił osobną politykę retencji dla `storage/stask/*.log` .

## Przesyłaj/pobieraj

Controller ma normalne i fragmentowane ścieżki przesyłania, endpoint z limitem serwera oraz dedykowane dla pracownika rozszerzenia. Pliki są przechowywane pod `storage/stask/uploads`.

Zasady integratora:

- Nie polegaj tylko na rozszerzeniu;
- sprawdzenie MIME i faktycznego formatu w workerze;
- limit rozmiaru i liczby fragmentów;
- generowanie nazw plików po stronie serwera;
- nie dopuszczać przechodzenia ścieżek;
- usuwanie plików tymczasowych/wyników zgodnie z polityką przechowywania;
- Nie zwracaj ścieżki pobierania, dopóki plik nie istnieje i nie należy do zadania.

Dokładny ładunek przesyłania zależy od kontraktu między widgetem a pracownikiem; Nie myśl o Endpoint jak o uniwersalnym API plików.

## Pozwolenia

`sTaskController::index()` i `show()` wyraźnie sprawdzają uprawnienia `stask`; Część dotycząca metod działania opiera się wyłącznie na `mgr`. Infrastrukturalnie ograniczaj sesję menedżera modułu do sesji, a w niestandardowych kontrolerach powtarzaj sprawdzanie uprawnień dla operacji destrukcyjnych.
