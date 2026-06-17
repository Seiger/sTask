# Przewodnik uzytkownika

## Otwieranie sTask

Otworz sTask z managera Evolution CMS. Nowy panel uzywa lokalnych zasobow EvoUI.
Jesli ekran jest bez stylow, najpierw sprawdz publikacje zasobow `stask` i
`evo-ui`.

## Dashboard

Dashboard pokazuje zadania oczekujace, uruchomione, zakonczone, bledne,
wszystkie zadania oraz aktywne workery. Ostatnie zadania mozna otworzyc ikona
oka albo dwuklikiem na wierszu. Gdy nie ma bledow, blok ostatnich bledow jest
ukryty.

## Zadania

Zakladka Zadania ma tryb tabeli i listy. Dostepne filtry:

- worker;
- akcja;
- status;
- priorytet;
- proby;
- okres utworzenia.

Widoczne sa ID, tytul workera, identyfikator, akcja, status, priorytet, postep,
proby, uzytkownik startu, komunikat, czas utworzenia, startu, zakonczenia i
aktualizacji.

## Workery

Lista workerow pokazuje tytul, opis, identyfikator, zakres, aktywnosc,
dostepnosc klasy, widocznosc, liczbe zadan, ostatni status, pozycje i czas
aktualizacji. Akcje w wierszu i toolbarze: edytuj, uruchom, aktywuj/dezaktywuj.

## Logi

Logi sa panelem audytu zadan i uzywaja tego samego modala szczegolow co Zadania.
Filtruj je po workerze, statusie i dacie utworzenia.

## Bezpieczna praca

Uruchomienie workera, Composer update, komendy Artisan i dezaktywacja zmieniaja
stan systemu. Po aktualizacjach publikuj zasoby ponownie, a po zmianach
permissions zaloguj sie ponownie do managera.
