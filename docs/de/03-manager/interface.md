# Manager-Schnittstelle

Das Modul verfügt über fünf Livewire-Tabs: **Panel**, **Aufgaben**, **Arbeiter**, **Logs**, **Statistik**. Das Umschalten erfolgt ohne einen vollständigen Neustart des Manager-Frames. Der Anfangstab kann durch den Abfrageparameter `get`; Der unbekannte Wert wird durch `dashboard` ersetzt.

## Zugang

Die Modulshell und Aufgabendetails können von einem Manager-Benutzer mit der Berechtigung `stask` geöffnet werden. Alle HTTP-Routen befinden sich in der Middleware-Gruppe `mgr`. Einige Aktions-Endpunkte verlassen sich nur auf `mgr` Middleware und rufen `hasPermission('stask')` nicht wiederholt auf, daher poste `/stask/*` nicht außerhalb der Manager-Authentifizierung.

## EvoUI-Tabellen gängige Regeln

Unterstützung für Tabellen **Aufgaben**, **Arbeiter**, **Logs**:

- Suche;
- pro Seite: 15, 30, 50, 100, 200 (Standard 30);
- Tabellen-/Listenansicht;
- Sortieren nur nach Spalten, die voreingestellt `sortable` markiert sind;
- Multi-Select- und Date-Range-Filter;
- Reihenaktionen;
- Doppelklick in allen String-Ansichten: Im Panel, in Aufgaben und Logs werden Aufgabendetails geöffnet, und in Workers – modale Bearbeitung.

Suche und Filter werden auf dem Server angewendet. Die Listenansicht verändert die Ansicht, nicht der Datensatz.

## Panel

![sTask-Dashboard mit Schlüsselkennzahlen](../../assets/screenshots/stask-dashboard.png)

### Karten

Das Panel zeigt die Zählungen der Warteschlange, laufenden, abgeschlossenen, durchgefallenen, Arbeiter und aktiven Arbeiter. Darauf folgen aktuelle Aufgaben und, falls vorhanden, eine separate Tabelle mit aktuellen Fehlern.

### Aktuelle Aufgaben

Spalten: ID, Worker, Aktion, Status, Fortschritt, Beginn der Ausführung, Aktionen. Für aktive Aufgaben erhält der String die Fortschritts-URL und liest `stask-module.js` periodisch den Schnappschuss ohne Loghistorie (`include_log=0`).

Doppelklick oder das `eye` -Symbol öffnet ein Modal mit den Hauptfeldern, Task-Log, Meta und Ergebnis. Inhalt nur als Lesung.

### Live-Fortschritt

Fortschrittsleiste/-werte und die Nachricht werden über HTTP-Abfragen aktualisiert. Sicherer Inline-Markdown unterstützt: Backticks, fett, durchgestrichen, Betonung. HTML entweicht zuerst. Im Endstand aktualisiert Livewire das Panel einmal.

## Aufgaben

![sTask Table](../../assets/screenshots/stask-tasks.png)

### Suche

Suchen nach numerischer ID, `identifier`, `action`, `message`.

### Filter

- **Worker** ist eine durchsuchbare Liste mit menschlichen `worker->title` statt rohen Identifikatoren.
- **Aktion** — unterschiedliche Aktionen aus der Datenbank.
- **Status** — in Warteschlange, Vorbereitung, Ausführung, abgeschlossen, fehlgeschlagen.
- **Benutzer** — Manager-Benutzer, die bereits Aufgaben ausgeführt haben, plus `system` für `started_by IS NULL OR <= 0`.
- **Erstellungsbereich** — Grenzen, die von Tagesbeginn bis Tagesende inklusiv sind.

Priorität und Versuche sind keine relevanten Spalten oder Filter dieses Tabs. Sie bleiben Laufzeit-/Schemafelder für die Kompatibilität, sind aber nicht als UI-Steuerung dokumentiert.

### Spalten

| Spalte | Bedeutung |
| --- | --- |
| ID | `#id`; Neueste Premiere für Standard |
| Arbeiter | Lokalisierter/menschlicher Titel oder Identifikator-Fallback |
| Aktion | Aktionscode |
| Status | Numerisches Statusabzeichen |
| Fortschritt | `0–100%`; Active Row kann live aktualisiert werden |
| Durch Laufen | Benutzername oder `system` |
| Nachrichten | Persistente Nachricht mit sicherer Markdown-Wiedergabe |
| Beginn der Hinrichtung | `start_at`; Für Future Queued Task ist dies die geplante Zeit |
| Abgeschlossen | `finished_at` |

### Aktionen

- `eye` — modale Details.
- `player-eject` — Notstopp für Warteschlange/Vorbereitung/Laufen.

Notstopp zeigt den Status "Fehlgeschlagen", `finished_at = now()` und die Meldung "Aufgabe ist abgestürzt". Es **beendet den PHP/OS-Prozess nicht**. Wenn der Prozess weiterhin funktioniert, kann er weiterhin die Daten oder die Fortschrittsdatei ändern.

Doppelklick auf die Leitung öffnet das Details-Modal.

## Arbeiter

![Arbeiterregister mit Dienstplänen und Vorgesetzten-Verfügbarkeit](../../assets/screenshots/stask-workers.png)

### Suche & Filter

Suche: Identifikator, Umfang, Klasse. Filter:

- aktiv/inaktiv;
- Klasse verfügbar/fehlend;
- sichtbar/verborgen.

### Spalten

- Identifikator.
- Arbeiter — Titel mit Klasseninstanz.
- Beschreibung — Auszug mit bis zu 96 Zeichen.
- Zeitplan — Chip; Für das Healthy Supervisor Badge wird Uptime nach `niceEta()` angezeigt.
- Anzahl der Aufgaben — `niceCount()` (kompakte lokalisierte Anzahl).
- Letzte Aktion.
- Letzter Durchlauf — der Zeitstempel der letzten Aufgabenaufzeichnung.

Versteckt ist die Sichtbarkeitsflagge des Managers; Der Mitarbeitereintrag wird nicht gelöscht. Ein inaktiver Worker kann nicht gestartet werden und der Scheduler überspringt ihn.

### Werkzeugleiste- und Zeilenaktionen

- `database-cog` — entdecken + wiederscannen + verwaiste reinigen + Arbeiter-Cache löschen.
- `player-play` — startet nur ausgewählt/Zeilenarbeiter, wenn aktiv, Klasse existiert und `taskMake()` ist.
- `edit` — modale Settings.
- `power` — aktiver Schalter.
- `eye/eye-off` — Sichtbarkeitsschalter.

Die Aktualisierungsregistrierung kann Datensätze löschen, deren Klasse nicht mehr existiert. Bevor du in Produktion startest, prüfe, ob das Composer-Autoladen abgeschlossen ist und der Deploy sich nicht im Zwischenzustand befindet.

### Modaler Arbeiter

Nur-lesen: Titel, Kennung, Umfang, Klasse, Beschreibung. Editierbar: aktiv, versteckt, Position, Zeitplan, zusätzliche JSON-Einstellungsnutzlast.

Zusätzliches JSON sollte keinen Schlüssel `schedule` enthalten: Beim Speichern wird er extrahiert und durch Formularwerte ersetzt. Ungültiges JSON wird nicht gespeichert; Der Anbieter belässt die vorherigen benutzerdefinierten Einstellungen.

Für den betreuerfähigen Kurs zeigt die Modalform außerdem:

- Schlüssel;
- Staatsabzeichen;
- PID;
- Herzschlag;
- Arbeitszeit (`niceEta`);
- die neueste Diagnose;
- Letzter Übergang.

Die Supervisor-Option ist verborgen, wenn die Klasse `SupervisorWorkerInterface` nicht implementiert.

## Stämme

![Aufgabenfortschrittsprotokoll](../../assets/screenshots/stask-logs.png)

Dies ist keine separate Log-Tabelle: Der Tab liest `s_tasks` und zeigt die Aufgabenhistorie detaillierter an.

### Suche & Filter

Suche: ID, Identifikator, Aktion, Nachricht. Filter: Mitarbeitertitel, Aktion, Status, Nutzer einschließlich `system`, erstellter Datumsbereich.

### Spalten

ID-Link, Mitarbeitertitel, Kennung, Aktion, Status, Fortschritt, begonnen von, erstellt, Start, abgeschlossen, aktualisiert, **Arbeitszeit**.

**Laufzeit** verwendet die Dauer der Aufgaben: für die letzte Aufgabe `finished_at - start_at`, für aktive Aufgabe `now - start_at`. Die Formatierung erfolgt von `niceEta()`. Der Namensübersetzungsschlüssel wird mit der Betriebszeit des Vorgesetzten geteilt, aber hier ist es die Dauer der Aufgabe.

ID öffnet eine separate Detailseite. Doppelklick öffnet ein Readonly-Modus mit Message, Meta, Result und Worker Class.

## Statistiken

![sTask-Statistiken der letzten 24 Stunden](../../assets/screenshots/stask-statistics.png)

Zeigt Performance-Karten der letzten 24 Stunden, Benachrichtigungen und Worker-Cache-Statistiken an.

Überblick über die aktuelle Umsetzung:

- Aufgabenanzahl;
- Erfolgs-/Fehlerquote bei Statusdatensätzen;
- Arbeitergruppierung;
- Cache-Treffer, Fehltritte, Räumungen, Trefferrate, Cache-Größe;
- Leere den Arbeiter-Cache.

Einschränkungen: `MetricsService` liefert bisher `0` für die durchschnittliche Dauer, den durchschnittlichen Speicher und die gesamte Ausführungszeit, wenn sie aus Task-Datensätzen aggregiert werden; Häufige Fehler ist ebenfalls ein leerer Platzhalter. Verwenden Sie diese Werte nicht als Produktions-SLI ohne externe Telemetrie.

`niceSize()` wird für menschenlesbare Speicherwerte verwendet, bei denen ein realer Bytewert vorhanden ist; `niceCount()` – für Grafen; `niceEta()` für Sekunden/Dauer.

## Datensicherheit

Meta, Ergebnis, Nachricht und Fortschrittsprotokoll sind für Manager-Benutzer mit Zugriff auf das Modul sichtbar. Teilen Sie keine Passwörter, API-Token, Sitzungsstrings oder persönliche Daten, es sei denn, Sie müssen sie dem Betreiber zeigen. Upload-/Download-Endpunkte haben eine worker-spezifische Validierung, aber der Worker muss trotzdem den Dateityp, die Größe und den Inhalt überprüfen.
