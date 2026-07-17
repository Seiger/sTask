# sTask Dokumentation

sTask ist ein Evolution CMS Paket fuer Hintergrundaufgaben. Es erkennt Worker,
erstellt Queue-Tasks, verfolgt Fortschritt, speichert Logs und Ergebnisse und
stellt ein kompaktes EvoUI + Livewire Manager-Panel bereit.

## Leitfaden

- [Benutzerhandbuch](03-manager/interface.md)
- [Entwicklerhandbuch](04-development/public-api.md)
- [Referenz](06-reference/cli-statuses-routes.md)
- [Konfiguration](06-reference/configuration.md)
- [Fehlerbehebung](05-operations/troubleshooting.md)
- [Custom Worker Migration](05-operations/upgrade-1-to-2.md)
- [Frontend Guide](01-getting-started/quick-start.md)
- [Backend Guide](02-concepts/architecture-and-lifecycle.md)

## Manager-Bereiche

- Dashboard mit Kennzahlen fuer Tasks und aktive Worker.
- Letzte Tasks mit Augen-Aktion und Detailmodal per Doppelklick.
- Tasks als Tabelle/Liste mit Filtern fuer Worker, Aktion, Status, Prioritaet, Versuche und Erstellungszeit.
- Worker mit Bearbeiten, Starten, Aktivieren/Deaktivieren, Status und Klassenverfuegbarkeit.
- Logs mit demselben Detailmodal wie Tasks.
- Statistikbereich fuer Performance/Cache.

## dDocs

Dieser Ordner ist die dateibasierte Dokumentationsquelle. Alte Docusaurus-Seiten
bleiben historisch; dDocs sollte mit den Sprachordnern starten.
