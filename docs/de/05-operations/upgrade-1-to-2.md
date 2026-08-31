# Migration von sTask 1.x zu 2.x

Dies ist eine evidenzbasierte Checkliste, kein automatischer Upgrader. Das Repository hat keinen vollständigen, maschinenlesbaren Migrationsvertrag für alle Drittanbieter-1.x-Worker, also prüfe jede benutzerdefinierte Klasse.

## Was hat die 2.x-Oberfläche verändert

- EvoUI/Livewire-Modul mit fünf Tabs.
- Tabellen-/Listenansichten, Serverfilter und schreibgeschützte Detailmodals.
- Adaptive HTTP-Abfrage im Live-Fortschritt.
- Duplikatunterdrückung für aktive Identifikator/Aktion/normalisierte Meta.
- Aktualisierung des Arbeiterregisters und Klassen-/Titelfilter.
- Dienstplantypen manuell/einmal/periodisch/regulär/supervisor.
- Trenne `SupervisorWorkerInterface`, `SupervisorStatus`, `s_supervisor_states`.
- Notstopp als fehlgeschlagener Übergang auf DB-Niveau.
- Kompakte Benutzeroberfläche: Priorität/Versuche wurden aus den aktuellen Spalten/Filtern entfernt.

## Vor dem Update

1. Eine Sicherungsdatenbank erstellen, `core/composer.lock`, individuelle Mitarbeiter erstellen und bei Bedarf `storage/stask` Audit durchführen.
2. Aktive Aufgaben feststellen; Lass sie ausreden.
3. Inventararbeiter:

   ```sql
   SELECT id, identifier, class, active, settings FROM s_workers ORDER BY id;
   ```

4. Finde benutzerdefinierte Klassen, die den alten Vertrag implementieren.
5. Überprüfe PHP 8.4 und evo-ui 1.2+.

## Anpassungsarbeiter

Empfohlene Form:

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

Abreise:

- Aktionsbenennung `task{StudlyAction}`;
- Signaturen mit `sTaskModel` - und Array-Optionen;
- Metadatenmethoden;
- No `$modx`; nutzen Sie `evo()`/services;
- Finalisierungs- und Ausnahmepfad;
- Fortschrittsnachrichten einzeilig;
- Geheimnisse gelangen nicht in die Benutzeroberfläche.

## Zeitplanmigration

Verschieben Sie die alten benutzerdefinierten Zeitplanschlüssel zu:

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

Der 2.x-Scheduler erwartet `taskMake()` für konventionelle Zeitpläne. Manuell/einmal/periodisch/regulär sind keine Cron-Ausdrücke.

## Migration des Supervisors

Simuliere keinen Minuten-für-Minuten-Gesundheitscheck wie eine normale Warteschlange. Für die Dämonenklasse füge `SupervisorWorkerInterface`, stabilen Schlüssel, schreibgeschützte Inspektion, getrennten Start/Neustart und Grace hinzu. sTask erstellt nur Aufgabenzeilen für Lebenszyklusereignisse.

## Datenbank

Führen Sie Paketmigrationen durch und überprüfen Sie:

- Die Haupttische waren keine zerstörerische Freizeitgestaltung;
- `s_supervisor_states` geschaffen;
- Erlaubnis `stask` aktiv;
- bestehende Arbeitnehmerkennungen nicht zufällig geändert wurden;
- Settings JSON ist gültig.

Aktuelle Basenmigrationen haben `Schema::create`, daher hängt das sichere Wiederinstallationsverhalten von der aktuellen Upstream-Referenz ab. Upgrade immer auf einen bewährten 2.x-Commit und führe Migrationsrauch auf einer Kopie des Produktionsschemas aus.

## Assets und Cache

```bash
php artisan package:discover
php artisan stask:publish
php artisan cache:clear-full
```

Altes JS/CSS im Browser-Cache kann das Tab-Switching oder den Live-Fortschritt kaputt machen.

## Akzeptanztest

- sTask wird mit der Berechtigungsrolle geöffnet;
- jede der fünf Tabs funktioniert;
- das Register sieht benutzerdefinierte Arbeiter;
- manuelle `taskMake` Enden;
- Future Schedule erstellt eine Warteschlange-Task;
- Live-Fortschritt wird durch Umfragen aktualisiert;
- Doppelklick öffnet das Modal;
- Notstopp zeigt an, dass der aktive Testrekord fehlgeschlagen ist;
- Der Supervisor-Zustand beginnt → gesund ohne Ereignisflut;
- dDocs zeigt einen lokalisierten sTask-Baum.

## Rollback

Rolle Code/Lock und DB konsequent zurück. Lösche keine `s_supervisor_states` oder neue Felder, solange der 2.x-Prozess ausgeführt werden kann. Wenn 1.x die neuen Einstellungen nicht versteht, speichere das Backup und bereite eine explizite Transformation vor.
