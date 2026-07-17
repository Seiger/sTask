# Harmonogramy

Harmonogram jest przechowywany w `s_workers.settings.schedule`. Edytor zakładki **Workers** normalizuje ładunek na pola `enabled`, `type`, `datetime`, `frequency`, `time`, `start_time`, `end_time`. .

## Instrukcja

```json
{"enabled": false, "type": "manual"}
```

Nie tworzy się automatycznego zadania. Uruchamianie odbywa się za pomocą przycisku menedżera lub kodu PHP.

## Raz

```json
{
  "enabled": true,
  "type": "once",
  "datetime": "2026-07-20 03:30:00"
}
```

`stask:worker` tworzy zadanie tylko wtedy, gdy datetime jest nadal w przyszłości, a pracownik nie ma nieukończonego zadania. Jeśli czas minął przed pierwszym przejściem planera, zadanie nie zostanie utworzone.

## Okresowe

Obsługiwane częstotliwości:

| Częstotliwość | Pola | Następny start |
| --- | --- | --- |
| `minutely` | Czas niepotrzebny | następna minuta |
| `every_5min` | Czas niepotrzebny | najbliższe minutowe wielokrotności 5 |
| `every_15min` | Czas niepotrzebny | najbliższe mnożniki minutowe 15 |
| `every_30min` | Czas niepotrzebny | najbliższa minuta w wielokrotnościach 30 |
| `hourly` | `time = *:MM` | następna godzina/minuta |
| `daily` | `time = HH:MM` | Dziś czy jutro |
| `weekly` | `time`, `days[]` | Następny Wybrany Dzień |
| `monthly` | `time` | Aktualny dzień miesiąca; UI nie udostępnia osobnego pola dnia |

Przykład codziennie o 02:15:

```json
{
  "enabled": true,
  "type": "periodic",
  "frequency": "daily",
  "time": "02:15"
}
```

Praktyczne ograniczenie UI 2.x: modalny ma czas, ale nie pokazuje edytora `days` dla tygodnia i `day` dla miesięcznych. Takie wartości można zapisać tylko za pomocą ustawień/kodu JSON; Przed wyprodukowaniem sprawdź je na prawdziwych `stask:worker`.

## Regularne w oknie czasowym

```json
{
  "enabled": true,
  "type": "regular",
  "frequency": "every_15min",
  "start_time": "08:00",
  "end_time": "18:00"
}
```

Dostępne przedziały: `every_5min`, `every_15min`, `every_30min`, `hourly`. Okno musi przypadać w ciągu jednego dnia kalendarzowego: jeśli `end_time < start_time`, następny czas nie jest obliczany. Okno nocne, takie jak `22:00–06:00` obecnej implementacji, nie jest obsługiwane.

Wyszukiwanie kolejnego miejsca zaczyna się od `start_time` i dodaje odstępy aż do momentu, gdy kandydat będzie później niż `now`. Po zakończeniu okna funkcja zwraca `null`; Zadanie na następny dzień nie jest tworzone w tym przejściu. To ważne ograniczenie operacyjne: na koniec dnia sprawdź pożądane zachowanie.

## Nadzorca

`type = supervisor` jest dostępny tylko w modalu dla klasy implementującej `SupervisorWorkerInterface`. Nie tworzy to zadania kontroli stanu zdrowia co minutę. Harmonogram aktualizuje jeden wiersz stanu na żywo, a wiersze zadań są tworzone tylko dla istotnych zdarzeń cyklu życia.

Szczegóły: [Nadzorca Procesu](supervisor.md).

## Reguła pojedynczego nieukończonego zadania

Dla razu/okresowego/regularnego harmonogram sprawdza zadania relacyjnego pracownika z zakresem `incomplete()` i nie tworzy następnego zadania, jeśli istnieje rekord w kolejce/przygotowaniu/ruchu. Długie lub zamrożone zadanie blokuje zatem dalsze planowanie tego identyfikatora.

Zatrzymanie awaryjne zwalnia rekord, wpisując go w niepowodzenie, ale nie zabija procesu operacyjnego. Najpierw ustawi, czy proces nadal działa, a dopiero wtedy wykonaj kolejne zadanie.

## Harmonogram Cron i Laravel

Dostawca dodaje polecenie:

```php
$schedule->command(TaskWorker::class)->everyMinute();
```

Ta definicja nie uruchamia planisty samodzielnie. Infrastruktura musi wykonywać `php artisan schedule:run` co minutę lub utrzymywać `schedule:work` pod zewnętrznym nadzorcą procesów.

## Typowe błędy

- **Nic nie jest tworzone** — pracownik nieaktywny, harmonogram zablokowany, brak `taskMake()`, nieważny czas lub zadanie jest już nieukończone.
- **Once skiped** — scheduler po raz pierwszy zobaczył datę po jej minieniu.
- **Tygodniowe nie działa** - `days` tablica jest zniknięta.
- **Regularne zatrzymane wieczorem** — aktualny algorytm nie przesuwa kolejnego slotu na następny dzień.
- **Duplikaty** — kilka `stask:worker` działa równolegle; Kontrola duplikatów nie jest Atomic Distributed Lock.
