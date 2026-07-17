# Migracja z zadania sTask 1.x do 2.x

To lista kontrolna oparta na dowodach, a nie automatyczny aktualizator. Repozytorium nie posiada pełnego, maszynowo czytelnego kontraktu migracyjnego dla wszystkich pracowników 1.x stron trzecich, więc sprawdź każdą niestandardową klasę.

## Co zmieniło powierzchnię 2.x

- Moduł EvoUI/Livewire z pięcioma zakładkami.
- Widoki tabel/list, filtry serwera oraz modale tylko do odczytu.
- Adaptacyjne badanie HTTP na żywo.
- Tłumienie duplikatów dla aktywnego identyfikatora/akcji/znormalizowanej mety.
- Odświeżanie rejestru pracowników oraz filtry klas/tytułów.
- Typy harmonogramu – ręczne/jednorazowe/okresowe/regularne/nadzorcze.
- Oddzielne `SupervisorWorkerInterface`, `SupervisorStatus`, `s_supervisor_states`.
- Zatrzymanie awaryjne jako nieudane przejście na poziomie DB.
- Kompaktowy interfejs: Priorytety/Próby zostały usunięte z obecnych kolumn/filtrów.

## Przed aktualizacją

1. Stworzyć kopię zapasową bazy danych, `core/composer.lock`, niestandardowych pracowników i `storage/stask` audytować w razie potrzeby.
2. Napraw aktywne zadania; Pozwól im dokończyć.
3. Pracownicy magazynu:

   ```sql
   SELECT id, identifier, class, active, settings FROM s_workers ORDER BY id;
   ```

4. Znajdź niestandardowe klasy implementujące stary kontrakt.
5. Sprawdź PHP 8.4 i evo-ui 1.0.6+.

## Pracownik adaptacyjny

Zalecana forma:

```php
final class ExampleWorker extends BaseWorker
{
    public function identifier(): string { return 'example'; }
    public function scope(): string { return 'custom'; }
    public function icon(): string { return '<i data-lucide="settings"></i>'; }
    public function title(): string { return 'Example'; }
    public function description(): string { return 'Example worker'; }

    public function taskMake(sTaskModel $task, array $options = []): void
    {
        // business logic
        $task->update(['progress' => 100, 'result' => ['ok' => true]]);
        $this->markFinished($task, null, 'Done');
    }
}
```

Sprawdź:

- nazewnictwo akcji `task{StudlyAction}`;
- sygnatury z opcjami `sTaskModel` i tablicy;
- metody metadanych;
- brak `$modx`; używaj `evo()`/services;
- ścieżka finalizacji i wyjątków;
- komunikaty postępu jednoliniowe;
- sekrety nie trafiają do interfejsu.

## Migracja według harmonogramu

Przenieś stare klucze do niestandardowego harmonogramu do:

```json
{
  "schedule": {
    "enabled": true,
    "type": "periodic",
    "frequency": "hourly",
    "time": "*:10",
    "datetime": "",
    "start_time": "",
    "end_time": ""
  }
}
```

Planista 2.x oczekuje `taskMake()` dla konwencjonalnych harmonogramów. Manual/raz/periodyczny/regularny nie są wyrażeniami cronowcami.

## Migracja nadzorców

Nie symuluj minutowego sprawdzania zdrowia jak zwykłego zadania w kolejce. Dla klasy daemon dodaj `SupervisorWorkerInterface`, klucz stabilny, inspekcję tylko do odczytu, odłączony start/restart i grace. sTask tworzy wiersze zadań tylko dla zdarzeń cyklu życia.

## Baza danych

Uruchom migracje pakietów i sprawdź:

- główne stoły nie były rekreacją destrukcyjną;
- `s_supervisor_states` stworzony;
- `stask` aktywne zezwolenie;
- istniejące identyfikatory pracowników nie zostały zmienione przypadkowo;
- JSON ustawień jest poprawny.

Obecne migracje baz mają `Schema::create`, więc bezpieczne zachowanie przy reinstalacji zależy od aktualnego odniesienia w górnym kierunku. Zawsze aktualizuj do sprawdzonego commitu 2.x i uruchamiaj migration smoke na kopii schematu produkcyjnego.

## Zasoby i cache

```bash
php artisan package:discover
php artisan stask:publish
php artisan cache:clear-full
```

Stare JS/CSS w pamięci podręcznej przeglądarki mogą zepsuć przełączanie między kartami lub postęp na żywo.

## Test akceptacyjny

- sTask jest otwierane rolą uprawnień;
- każda z pięciu zakładek działa;
- rejestr przyjmuje pracowników customowych;
- ręczne `taskMake` końców;
- przyszły harmonogram tworzy zadanie w kolejce;
- Postępy na żywo są aktualizowane na podstawie ankiet;
- podwójne kliknięcie otwiera modal;
- zatrzymanie awaryjne oznacza, że test aktywny rekord nie powiódł;
- Przejście stanu nadzorczego do rozpoczęcia → zdrowym bez powodzi zdarzeń;
- dDocs pokazuje zlokalizowane drzewo zadań sTask.

## Cofnij się

Cofaj kod/blokadę i DB konsekwentnie. Nie usuwaj `s_supervisor_states` ani nowych pól, dopóki proces 2.x może działać. Jeśli wersja 1.x nie rozumie nowych ustawień, zapisz kopię zapasową i przygotuj jawną transformację.
