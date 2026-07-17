# Rekomendacje produkcyjne

## Model procesu

`stask:worker` — komenda do biegania i wyjazdu. Tworzy zaplanowane zadania, kolejkowo przetwarza gotowe wiersze w kolejce i kończy zadania. Do bieżącej konserwacji uruchamiaj harmonogram Laravel co minutę.

```cron
* * * * * cd /var/www/example/core && /usr/bin/flock -n /run/lock/example-stask.lock /usr/bin/php artisan schedule:run >> /var/log/example-scheduler.log 2>&1
```

`flock` — Strażnik infrastruktury z Overlap. Wybierz ścieżkę blokady zapisu i sprawdź, czy jedno długie zadanie nie blokuje krytycznych, niezwiązanych ze sobą zadań planistów. Alternatywą jest osobna jednostka systemd/timer z polityką `RefuseManualStart`/locking.

## Właściciel i prawa

Użytkownik sieci i użytkownik CLI muszą posiadać kompatybilne prawa do:

- `core/storage/stask`;
- Pamięć podręczna/magazyn Laravela;
- katalog przesyłania wyników;
- zasoby aplikacyjne, które zmienia pracownik.

Nie uruchamiaj crona z rootu bez potrzeby: pliki postępu lub cache tworzone przez root często psują proces zarządzania.

## Przerwa

Ścieżka działania menedżera może wywołać `set_time_limit(0)`, a polecenie CLI nie ustawia limitu czasu na każde zadanie. Przerwy muszą być w pracowniku:

- HTTP connect/read timeout;
- Limit czasu na wyrażenie DB;
- maksymalna liczba elementów/partii na zadanie;
- termin w metadanych;
- Eleganckie punkty kontrolne anulowania.

Podziel długie prace na idempotentne części. Jedno monolityczne zadanie blokuje kolejny harmonogram tego samego identyfikatora pracownika.

## Rywalizacja

Obecny CLI wybiera wiersze w kolejce bez zapytania o roszczenia atomowego/`FOR UPDATE SKIP LOCKED`. W związku z tym:

- zachować jeden `stask:worker` na instalację;
- Zastosowanie zewnętrznego zamka nakładania się;
- uczynić działanie idempotentnym;
- dla integracji krytycznych użyć klucza idempotencji na poziomie domeny;
- Nie mylić wyszukiwania duplikatów z gwarancją transakcyjną.

Jeśli wymagana jest współbieżność, najpierw zaprojektuj umowę o roszczeniu/dzierżawie; Samo zwiększenie liczby procesów jest niebezpieczne.

## Spróbuj jeszcze raz i odsuń się

`attempts/max_attempts` nie próbują sami powtórki. Polityka produkcyjna powinna określać:

- powtarzalne klasy wyjątków/kody statusu;
- maksymalna liczba prób;
- `start_at` od cofnięcia się;
- martwy liter/przegląd instrukcji;
- zachowania duplikatyczne/idempotentne;
- Alarm po ostatnim błędzie.

## Monitorowanie

Minimalne kontrole:

- Heartbeat schedulera/ostatni udany start;
- liczbę zadań w kolejce oraz wiek najstarszego;
- wiek zadań bieżących;
- wskaźnik niepowodzenia;
- zapisywalność `storage/stask`;
- postęp/wyniki korzystania z dysku;
- pracownicy zaginioni/nieaktywni w klasie;
- `s_supervisor_states.last_seen_at` i świeżość bicia serca;
- tożsamość PID demonów.

Wbudowana zakładka Statystyki nie zastępuje APM: czas/pamięć/częste błędy częściowymi zastępczymi.

## Logi

Istnieją trzy różne źródła:

1. `s_tasks.message/meta/result` — ciągły audyt/stan.
2. `storage/stask/{id}.log` — postęp tylko do dodawania na żywo.
3. logi aplikacji za pośrednictwem Laravel `Log` — wyjątki, odkrycia, ostrzeżenia o uruchomieniu.

Zdefiniuj retencję indywidualnie. `cleanOldTasks()` usuwa tylko stare ukończone wiersze DB; Postęp `.log`, nieudane zadania i stan przełożonego nie są zatwierdzane.

## Zatrzymanie

Przykład polityki do wdrożenia na warstwie operacyjnej:

- ukończone zadania: 30–90 dni;
- nieudane zadania: dłuższe lub przed przeglądem incydentu;
- Dzienniki postępów: 7–30 dni po ostatnim zadaniu;
- upload/wyniki: dla polityki biznesowej/prawnej;
- stan nadzorcy: jeden rzeczywisty wiersz na klawisz; Sieroty — po sprawdzeniu inwentarza.

Nie usuwaj aktywnych postępów w zadaniach. Sprawdź ostateczny status i zgłosz własność przed sprzątaniem.

## Nadzorca/systemd

sTask Supervisor to adapter cyklu życia na poziomie aplikacji, a nie całkowity zamiennik systemd/Supervisor. Jeśli proces restartuje jednocześnie systemd i sTask adapter, ustalcie jednego właściciela, w przeciwnym razie możliwe są pętle restartu.

Zalecane role:

- systemd zapewnia rozruch, limity użytkowników, zasobów oraz restart awarii;
- Adapter Worker odczytuje Health/Heartbeat i zwraca stan diagnostyczny;
- tylko jeden z nich wykonuje restart lub oba mają wspólny kontrakt na wycofanie/blokadę.

## Rozmieszczenie

Bezpieczna sekwencja:

1. zatrzymać tworzenie nowych miejsc pracy lub poczekać na ukończenie kluczowych miejsc;
2. `composer install` z blokadą;
3. `php artisan migrate --force` po przeglądzie kopii zapasowej i migracji;
4. `package:discover`/autoload rekonstrukcji;
5. `stask:publish`;
6. `cache:clear-full`;
7. odśwież rejestr pracowników po holistycznym wdrożeniu;
8. dymny `stask:worker`;
9. Sprawdź interfejs menedżera i harmonogram.

Nie uruchamiaj czyszczenia rejestru w trakcie wdrożenia, gdy klasy są tymczasowo nieobecne.

## Bezpieczeństwo

- pozwolenie `stask` wyłącznie role operacyjne;
- `run_artisan` osobno dla ArtisanWorker;
- biała lista/lista niebezpiecznych komend;
- CSRF dla POST;
- sekrety niebędące w meta/wyniku/wiadomości/postępie;
- przesyłania do niepublicznych magazynów;
- pracownicy weryfikują dane wejściowe i autoryzacje, nawet jeśli tylko za pomocą menedżera tras;
- konto serwisowe z minimalnymi uprawnieniami systemu plików/bazy danych.
