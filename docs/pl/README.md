# Dokumentacja sTask

sTask to pakiet Evolution CMS do zadan w tle. Wykrywa workery, tworzy zadania w
kolejce, sledzi postep, zapisuje logi i wyniki oraz udostepnia panel
administracyjny EvoUI + Livewire.

## Przewodniki

- [Przewodnik uzytkownika](03-manager/interface.md)
- [Przewodnik dewelopera](04-development/public-api.md)
- [Reference](06-reference/cli-statuses-routes.md)
- [Konfiguracja](06-reference/configuration.md)
- [Rozwiazywanie problemow](05-operations/troubleshooting.md)
- [Migracja custom workerow](05-operations/upgrade-1-to-2.md)
- [Frontend guide](01-getting-started/quick-start.md)
- [Backend guide](02-concepts/architecture-and-lifecycle.md)

## Powierzchnie managera

- Dashboard z licznikami zadan i aktywnych workerow.
- Ostatnie zadania z akcja podgladu i modalem szczegolow po double-click.
- Zadania w trybie tabeli/listy z filtrami: worker, akcja, status, priorytet, proby i data utworzenia.
- Workery z edycja, uruchomieniem, aktywacja/dezaktywacja, statusem i dostepnoscia klasy.
- Logi z tym samym modalem szczegolow co zadania.
- Zakladka statystyk dla performance/cache.

## dDocs

Ten katalog jest plikowym zrodlem dokumentacji. Starsze strony Docusaurus
pozostaja historyczne; dDocs powinien zaczynac od katalogow lokalizacyjnych.
