# Szybki start

Ten skrypt uruchamia się od czystej instalacji do pierwszego ukończonego zadania bez żadnych fikcyjnych API.

## 1. Sprawdź opakowanie

```bash
cd core
php artisan route:list --path=stask
php artisan stask:worker
```

Druga drużyna może wyciągnąć `created 0 scheduled task(s), processed 0 task(s)` – to normalny wynik dla pustej kolejki.

## 2. Zaktualizuj rejestr pracowników

Otwórz **sTask → Workers** i kliknij przycisk z ikoną `database-cog` (**Aktualizuj Worker Registry**). Discovery odczytuje `vendor/composer/autoload_classmap.php`, odrzuca wykluczone przestrzenie nazw i rejestruje konkretne klasy implementujące `TaskInterface`.

Nowy pracownik jest tworzony jako nieaktywny. Włącz go przyciskiem zasilania lub w oknie edycji modalnej.

## 3. Uruchom ręcznie

Dla aktywnego workera z metodą `taskMake()` naciśnij `player-play`. sTask:

1. utworzyć `s_tasks` o statusie `10`;
2. napisać pierwszą linię w `storage/stask/{id}.log`;
3. spróbuje uruchomić `php core/artisan stask:worker` w tle;
4. będzie pokazywać postęp na żywo w wierszu tabeli za pomocą adaptacyjnego odpytywania HTTP.

Jeśli `exec`/`shell_exec` są wyłączone, wykonaj polecenie ręcznie:

```bash
php artisan stask:worker
```

## 4. Sprawdź wynik

Na zakładce **Zadania** znajdź wpis według ID, nazwy pracownika lub akcji. Oczekiwana sekwencja statusów:

```text
10 queued → 50 running → 80 finished
```

Status `30 preparing` definiowany przez model i może być używany przez kod aplikacji, ale standardowy `TaskWorker` przechodzi z kolejki bezpośrednio do uruchomienia.

Podwójne kliknięcie na linię otwiera modal tylko do odczytu z komunikatem, meta i wynikiem. Osobny link do ID znajduje się na zakładce **Logs** i prowadzi na pełną stronę szczegółów zadań.

## 5. Stwórz zadanie z PHP

Fasada zwraca istniejący aktywny duplikat lub nowy model:

```php
<?php

use Seiger\sTask\Facades\sTask;

$task = sTask::create(
    identifier: 'inventory_sync',
    action: 'make',
    data: ['warehouse' => 12, 'force' => false],
    priority: 'normal',
    userId: evo()->getLoginUserID() ?: null,
);

echo $task->id;
```

Ważne: `create()` tylko ustawia kolejkę dla wpisu. Wykonanie wymaga `stask:worker` lub wywołania `sTask::execute($task)` w kontrolowanym procesie.

## 6. Ustaw automatyczny start

W trybie worker włącz Auto Start i wybierz harmonogram. Na przykład co godzina w 15. minucie:

```json
{
  "schedule": {
    "enabled": true,
    "type": "periodic",
    "frequency": "hourly",
    "time": "*:15",
    "datetime": "",
    "start_time": "",
    "end_time": ""
  }
}
```

Następne `stask:worker` utworzy przyszłe zadanie kolejkowe z `start_at`. Do czasu nadejścia tego momentu zadanie jest widoczne w **Zadaniu**, ale nie jest wykonywane.

## Lista kontrolna

- odniesienie źródłowe pakietu odpowiada oczekiwanej gałęzi 2.x;
- migracje są udane;
- `stask` przydzielone do wybranej roli menedżera;
- `storage/stask` użytkownik sieci i użytkownik CLI są dostępne do zapisu;
- cron uruchamia scheduler co minutę;
- pracownik jest aktywny, klasa istnieje, identyfikator jest unikalny;
- zadanie przechodzi w status końcowy i ma `finished_at`.
