# FAQ

## Zadanie to Laravel Queue?

Nie. sTask ma własne tabele Eloquent, kontrakt pracowników oraz polecenie run-and-ex. Nie używa połączenia broker/queue Laravel jako głównego silnika wykonawczego.

## Czy potrzebuję Redis?

Nie do podstawowej pracy. Worker cache/metryki używają Laravel Cache, a supervisor lock wyraźnie przyjmuje store `file`. Skonfigurowana pamięć podręczna aplikacji powinna nadal działać bez zarzutu.

## Czy istnieje SSE czy WebSocket?

Nie. Live Progress to adaptacyjny endpoint do ankietowania HTTP `/stask/task/{id}/progress`.

## Czy przycisk start wykonuje zadanie w tle?

Kontroler próbuje zamknąć odpowiedź FastCGI i uruchomić pracownika CLI; Opcja awaryjna może być synchroniczna lub nie wyzwalać się z powodu wyłączonych funkcji. Cron/scheduler to niezawodna ścieżka produkcji.

## Czy proces PHP zatrzyma awaryjną?

Nie. Tłumaczy tylko rekord bazy danych na nieudane. Proces operacyjny musi zostać zatrzymany osobno.

## Czy są automatyczne powtórki?

Nie. Próby/maksymalne próby są zapisywane, ale nieudane wiersze nie są automatycznie ponownie zaliczane przez domyślne polecenie.

## Co oznacza `system` w filtrze użytkownika?

Zadania z `started_by` nullowym lub `<= 0`, w tym wydarzenia z harmonogramem/nadzorcą.

## Dlaczego filtr pracownika pokazuje nazwę, a nie identyfikator?

Dostawca rozwiązuje `worker->title` i używa identyfikatora jedynie jako awaryjnego. Zapytanie filtruje zadania według identyfikatorów powiązanych z wybranymi identyfikatorami pracowników.

## Dlaczego zadanie z tym samym ładunkiem nie zostało utworzone po raz drugi?

Aktywne tłumienie duplikatów porównuje identyfikator, akcję oraz znormalizowaną meta dla kolejki statusowej/przygotowania/uruchomienia i zwraca istniejący model.

## Czy tłumienie duplikatów jest bezpieczne w wyścigu?

Nie całkowicie. W schemacie nie ma unikalnego klucza dla aktywnego hashu ładunku. Procesy równoległe mogą przechodzić przez wyszukiwanie w tym samym czasie.

## Gdzie przechowywany jest postęp?

`core/storage/stask/{taskId}.log`. `progress` bazy danych jest aktualizowana osobno i nie wyświetla automatycznie każdego migawki pliku.

## Dlaczego nie ma postępów, a zadanie działa?

Niepowodzenia w zapisie pliku są celowo ignorowane, aby nie zakłócać zadania biznesowego. Sprawdź uprawnienia i logi aplikacji.

## Jak mogę oczyścić swoją historię?

`sTask::cleanOldTasks($days)` usuwa tylko stare ukończone zadania z bazy danych. Nieudane zadania, `.log`, przesłania/wyniki oraz stan przełożonego wymagają osobnej polityki.

## Jak uruchomić wielu pracowników równolegle?

Obecny CLI nie zawiera roszczenia o atomowym wieloprocesowym procesie. Nie skaluj procesów poziomo bez nowego projektu roszczenia/najmu oraz idempotentności.

## Dlaczego regularny grafik nie stworzył zadania następnego dnia?

Algorytm wyszukuje kandydata tylko w bieżącym oknie dziennym i zwraca null po zakończeniu. Jest to znane ograniczenie obecnej implementacji.

## Czym różni się grafik Supervisora od okresowego badania zdrowotnego?

Supervisor aktualizuje jeden wiersz w stanie na żywo co minutę i tworzy zadanie tylko dla istotnego wydarzenia cyklu życia. Harmonogram okresowy obsługuje regularne kolejkowe `taskMake`.

## Czy stan nadzorcy jest usunięty z pracy pracownika?

Nie, automatycznie: relacja nie ma klucza obcego/kaskady bazy danych. Szeregi sierot sprzątają po ekwipunku.
