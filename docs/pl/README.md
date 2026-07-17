# sTask 2.x

sTask to pakiet Evolution CMS do zarządzania zadaniami w tle. Przechowuje kolejkę w bazie danych, wyszukuje workery przez Composer, uruchamia je poleceniem `stask:worker`, wyświetla postęp i logi w managerze oraz osobno nadzoruje długotrwałe procesy supervisora.

Dokumentacja opisuje rzeczywistą gałąź `2.x`. Nie zakłada istnienia zewnętrznej kolejki, SSE ani WebSocket: live progress w managerze jest odczytywany przez okresowe żądania HTTP z logów plikowych `storage/stask/{taskId}.log`.

## Dla kogo jest ta dokumentacja

- **Administrator** — instalacja, uprawnienia, cron, zakładki managera, diagnostyka i eksploatacja produkcyjna.
- **Integrator** — harmonogramy, rejestracja workerów, trasy managera, migracje i aktualizacje.
- **PHP developer** — `TaskInterface`, `BaseWorker`, fasada `sTask`, API postępu oraz kontrakt supervisora.

## Mapa dokumentacji

1. Rozpoczęcie
   - [Wymagania, instalacja i aktualizacje](01-getting-started/installation.md)
   - [Szybki start](01-getting-started/quick-start.md)
2. Pojęcia
   - [Architektura i cykl życia](02-concepts/architecture-and-lifecycle.md)
   - [Rozkłady](02-concepts/schedules.md)
   - [Nadzorca Procesu](02-concepts/supervisor.md)
3. Menedżer CMS Evolution
   - [Panel, Zadania, Pracownicy, Logi i Statystyki](03-manager/interface.md)
4. Rozwój
   - [Interfejs fasady i API PHP](04-development/public-api.md)
   - [Własny pracownik](04-development/custom-worker.md)
   - [Trasy, pliki postępów i pliki do pobrania](04-development/routes-and-progress.md)
5. Działanie
   - [Zalecenia produkcyjne](05-operations/production.md)
   - [Diagnostyka](05-operations/troubleshooting.md)
   - [Przejście z 1.x do 2.x](05-operations/upgrade-1-to-2.md)
6. Katalog
   - [Konfiguracja](06-reference/configuration.md)
   - [Tabele bazodanowe](06-reference/database.md)
   - [CLI, statusy i trasy](06-reference/cli-statuses-routes.md)
   - [FAQ](06-reference/faq.md)

## Limity odpowiedzialności

sTask wykonuje pracownika sekwencyjnie w procesie `stask:worker`. Pakiet nie zapewnia dokładnie jednorazowej, rozproszonej gwarancji brokera, automatycznego zakończenia procesu systemu operacyjnego przyciskiem awaryjnego zatrzymania ani przechowywania wszystkich komunikatów postępu w bazie danych. Takie wymagania są realizowane przez pracownika aplikacji oraz infrastrukturę projektu.

## Pierwsza kontrola

Po zainstalowaniu wykonaj:

```bash
cd core
php artisan migrate
php artisan package:discover
php artisan stask:publish
php artisan stask:worker
```

Oczekiwany rezultat: migracje zostały utworzone `s_workers`, `s_tasks` i `s_supervisor_states`, opublikowano zasoby wsadowe, a zespół pracowników zakończył komunikatem o liczbie utworzonych i przetworzonych zadań. Następnie otwórz moduł **sTask** w menedżerze.
