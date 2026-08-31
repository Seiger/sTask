# Wymagania, instalacja i aktualizacje

## Wymagania

Obecna `composer.json` 2.x wymaga:

| Komponent | Wymóg |
| --- |----------------------------|
| PHP | <code>&#94;8.4</code> |
| Evolution CMS | <code>&#94;3.5.7</code> |
| evo-ui | <code>&#94;1.2</code> |
| Kompozytor | Dostępne w katalogu `core` |

Encja HTML do kursora jest używana celowo: dDocs nie konwertuje jej na indeks górny i pokazuje dokładne ograniczenie Composer.

Automatyczne przetwarzanie kolejek wymaga systemowego crona lub innego planisty, który uruchamia harmonogram Laravel co minutę. Aby wykonać zadanie bezpośrednio z interfejsu użytkownika, proces PHP musi mieć również dostęp do `exec()` lub `shell_exec()`; Jeśli nie są dozwolone, wpis w kolejce zostanie utworzony i przetworzony przez następny przepustkę CRON.

## Instalacja przez kompozytora

Polecenia są wykonywane z katalogu `core` Evolution CMS:

```bash
cd /var/www/example/core
composer require seiger/stask:"2.x-dev"
php artisan migrate
php artisan package:discover
php artisan stask:publish
php artisan cache:clear-full
```

Co powinno się wydarzeć:

1. Odkrywanie pakietu Laravel łączy `Seiger\sTask\sTaskServiceProvider` i alias `sTask`.
2. `migrate` wywołuje migracje wsadowe, które dostawca dodał za pomocą `loadMigrationsFrom()`.
3. `package:discover` aktualizuje manifest odkrywania pakietów Laravel; Composer classmap jest tworzony podczas instalacji/aktualizacji lub `composer dump-autoload`.
4. `stask:publish` kopiuje CSS, JavaScript i SVG do `assets/site`.
5. Całkowite czyszczenie pamięci podręcznej usuwa stare metadane Widoków Menedżera, Tras i Pakietów.

Nie edytuj plików w `core/vendor/seiger/stask`: Composer wymieni je podczas aktualizacji.

## Dostawca usług

Dostawca automatycznie:

- rejestruje pojedyncze `Seiger\sTask\sTask` i aliasy `sTask`;
- rejestruje `WorkerService`, `MetricsService`, `SupervisorService`;
- migracje ładowania, translacje, widoki blade, trasy menedżerskie oraz komponenty Livewire;
- łączy presety tabeli `stask.tasks`, `stask.workers`, `stask.logs`;
- tworzy `storage/stask` jeśli katalog jeszcze nie istnieje;
- rejestruje `stask:worker` i `stask:publish` w CLI;
- dodaje `stask:worker` do planisty Laravel z częstotliwością raz na minutę.

Menu Menedżera dodaje wtyczkę wsadową `plugins/sTaskPlugin.php` do zdarzenia `evolution.OnManagerMenuPrerender` tylko wtedy, gdy użytkownik ma uprawnienia `stask`. Prowadzi na nazwaną trasę `sTask.index` i używa `sTaskServiceProvider::MODULE_ICON`. Plik `module/sTaskModule.php` jest chronioną otoczką dla wpisu modułu Evolution i renderuje ten sam kontroler. Metoda chroniona `registerManagerModule()` obecna w dostawcy dla przepływu instalatora/modułu, ale nie wywołuje `boot()` niej bezpośrednio; Nie polegaj na ręcznym wywoływaniu tej metody.

## Migracje i pozwolenia

Pakiet tworzy trzy tabele: `s_workers`, `s_tasks`, `s_supervisor_states`. Oddzielna migracja idempotentów tworzy grupę uprawnień `sTask`, klucz `stask` uprawnień i dodaje jego rolę `1` jeśli istnieją odpowiednie tabele systemowe.

Sprawdzone:

```bash
php artisan migrate:status
php artisan route:list --path=stask
```

W menedżerze użytkownik potrzebuje uprawnień `stask`. Trasy menedżerów są dodatkowo chronione przez grupę middleware `mgr`; nie jest to publiczne API HTTP.

## Publikuj aktywa

Drużyna:

```bash
php artisan stask:publish
```

publikuje w szczególności:

- `assets/site/stask.svg`;
- `assets/site/stask.min.css`;
- `assets/site/stask-module.css`;
- `assets/site/stask-module.js`;
- `assets/site/stask.js`;
- `assets/site/seigerit.tooltip.js`.

Jeśli moduł otwiera się bez stylów lub postęp na żywo się nie aktualizuje, najpierw powtórz publikowanie i `cache:clear-full`, a następnie sprawdź HTTP 200 dla tych zasobów.

## Cron

Zalecane nagranie produkcyjne:

```cron
* * * * * cd /var/www/example/core && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

Sprawdź absolutne ścieżki:

```bash
command -v php
cd /var/www/example/core && php artisan schedule:list
cd /var/www/example/core && php artisan stask:worker
```

Nie uruchamiaj wielu niekontrolowanych wpisów cron dla tej samej instalacji. Zwykłe zadania nie mają globalnej blokady roszczeń, więc równoległe `stask:worker` mogą stwarzać ryzyko konkurencyjnego wykonania.

## Aktualizacja 2.x

```bash
cd /var/www/example/core
composer update seiger/stask --with-dependencies
php artisan migrate
php artisan package:discover
php artisan stask:publish
php artisan cache:clear-full
```

Po aktualizacji sprawdź rzeczywistą referencję źródłową:

```bash
composer show seiger/stask --all
```

Jeśli dokumentacja wsadowa nie pojawia się w dDocs, sprawdź nie tylko `docs` w repozytorium Git, ale także katalog fizyczny `core/vendor/seiger/stask/docs/uk`. Blokada/dysz kompozytora może pozostać na starym commite.

## Cofnij się

Przed aktualizacją zrób kopię zapasową bazy danych i `core/composer.lock`. Cofnij kod za pomocą Composer do zweryfikowanego źródła. Cofnij schemat bazy danych tylko według osobnego planu po sprawdzeniu rzeczywistego zestawu migracji w zainstalowanej wersji pakietu i konsekwencji dla danych produkcyjnych.
