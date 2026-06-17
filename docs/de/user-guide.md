# Benutzerhandbuch

## sTask Oeffnen

Oeffnen Sie sTask im Evolution CMS Manager. Das neue Panel nutzt lokale EvoUI
Assets. Wenn CSS oder JavaScript fehlen, pruefen Sie zuerst die Veroeffentlichung
der `stask` und `evo-ui` Assets.

## Dashboard

Das Dashboard zeigt wartende, laufende, abgeschlossene, fehlgeschlagene und alle
Tasks sowie aktive Worker. Letzte Tasks koennen mit der Augen-Aktion oder per
Doppelklick geoeffnet werden. Gibt es keine Fehler, wird kein leerer Fehlerblock
angezeigt.

## Tasks

Die Task-Ansicht unterstuetzt Tabellen- und Listenmodus. Filter:

- Worker;
- Aktion;
- Status;
- Prioritaet;
- Versuche;
- Erstellungszeitraum.

Die Tabelle zeigt ID, Worker, Identifier, Aktion, Status, Prioritaet, Fortschritt,
Versuche, Startbenutzer, Nachricht, Erstellt, Start, Abschluss und Aktualisiert.

## Worker

Worker-Zeilen zeigen Titel, Beschreibung, Identifier, Scope, Aktivstatus,
Klassenverfuegbarkeit, Sichtbarkeit, Taskanzahl, letzten Task, Position und
Update-Zeit. Aktionen: Bearbeiten, Task starten, Aktivieren/Deaktivieren.

## Logs

Logs sind die Audit-Ansicht fuer Tasks und nutzen dasselbe Detailmodal wie die
Task-Ansicht. Filtern Sie nach Worker, Status und Erstellungsdatum.

## Sichere Bedienung

Worker-Start, Composer Update, Artisan-Kommandos und Deaktivierung aendern den
Systemzustand. Nach Updates Assets neu publizieren und nach neuen Permissions
erneut im Manager anmelden.
