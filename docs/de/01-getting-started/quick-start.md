# Schneller Start

Dieses Skript läuft von einer sauberen Installation bis zur ersten abgeschlossenen Aufgabe ohne jegliche fiktive APIs.

## 1. Überprüfen Sie das Paket

```bash
cd core
php artisan route:list --path=stask
php artisan stask:worker
```

Das zweite Team kann `created 0 scheduled task(s), processed 0 task(s)` herausbringen – das ist ein normales Ergebnis bei einer leeren Warteschlange.

## 2. Aktualisieren Sie das Arbeiterregister

Öffne **sTask → Workers** und klicke auf den Button mit dem `database-cog` -Symbol (**Worker Registry aktualisieren**). Discovery liest `vendor/composer/autoload_classmap.php`, verwerft ausgeschlossene Namensräume und registriert konkrete Klassen, die `TaskInterface` implementieren.

Ein neuer Mitarbeiter wird inaktiv gemacht. Schalte es mit dem Power-Knopf oder im Modal-Edit-Fenster ein.

## 3. Manuell ausführen

Drücken Sie für einen aktiven Worker mit der Methode `taskMake()` auf `player-play`. sTask:

1. Schaffen `s_tasks` mit Status `10`;
2. die erste Zeile in `storage/stask/{id}.log` schreiben;
3. wird versuchen, `php core/artisan stask:worker` im Hintergrund laufen zu lassen;
4. wird den Live-Fortschritt in der Tabellenzeile über adaptive HTTP-Abfrage anzeigen.

Wenn `exec`/`shell_exec` deaktiviert sind, führe den Befehl manuell aus:

```bash
php artisan stask:worker
```

## 4. Überprüfen Sie das Ergebnis

Im Tab **Aufgaben** finden Sie den Eintrag nach ID, Arbeitername oder Aktion. Erwartete Statussequenz:

```text
10 queued → 50 running → 80 finished
```

Der Status `30 preparing` vom Modell definiert und kann im Anwendungscode verwendet werden, aber der Standard- `TaskWorker` geht direkt vom Warteschlangen in den laufenden Modus.

Doppelklick auf eine Zeile öffnet ein Readonly-Modal mit Message, Meta und Result. Ein separater ID-Link befindet sich im **Logs**-Tab und führt zur vollständigen Seite mit Aufgabendetails.

## 5. Erstelle eine Aufgabe aus PHP

Die Fassade bringt ein bestehendes aktives Duplikat oder ein neues Modell zurück:

```php
<?php

use Seiger\sTask\Facades\sTask;

$task = sTask::create(
    identifier: 'inventory_sync',
    action: 'make',
    data: ['warehouse' => 12, 'force' => false],
    priority: 'normal',
    userId: evo()->getLoginUserID() ?: null,
);

echo $task->id;
```

Wichtig: `create()` stellt nur den Eingang in die Warteschlange. Die Ausführung erfordert `stask:worker` oder einen Aufruf `sTask::execute($task)` in einem kontrollierten Prozess.

## 6. Automatische Start einrichten

Im Worker-Modus aktivieren Sie Auto-Start und wählen Sie den Zeitplan aus. Zum Beispiel jede Stunde in der 15. Minute:

```json
{
  "schedule": {
    "enabled": true,
    "type": "periodic",
    "frequency": "hourly",
    "time": "*:15",
    "datetime": "",
    "start_time": "",
    "end_time": ""
  }
}
```

Das folgende `stask:worker` erstellt eine zukünftige Warteschlange-Aufgabe mit `start_at`. Bis die Zeit gekommen ist, ist die Aufgabe in **Aufgabe** sichtbar, wird aber nicht ausgeführt.

## Checkliste

- Paketquellenreferenz entspricht dem erwarteten Zweig 2.x;
- Migrationen sind erfolgreich;
- Berechtigung `stask` die gewünschte Managerrolle zugewiesen;
- `storage/stask` ein Webnutzer und ein CLI-Benutzer zum Schreiben verfügbar sind;
- Cron betreibt den Scheduler jede Minute;
- Arbeiter ist aktiv, Klasse existiert, Identifikator ist eindeutig;
- Die Aufgabe geht in den Endstatus und hat `finished_at`.
