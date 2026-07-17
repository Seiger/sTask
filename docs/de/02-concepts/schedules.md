# Zeitpläne

Der Zeitplan wird in `s_workers.settings.schedule` gespeichert. Der Editor im **Workers**-Tab normalisiert die Nutzlast auf Felder `enabled`, `type`, `datetime`, `frequency`, `time`, `start_time`, `end_time`.

## Handbuch

```json
{"enabled": false, "type": "manual"}
```

Es wird keine automatische Aufgabe erstellt. Der Start erfolgt über einen Manager-Button oder einen PHP-Code.

## Einmal

```json
{
  "enabled": true,
  "type": "once",
  "datetime": "2026-07-20 03:30:00"
}
```

`stask:worker` erstellt eine Aufgabe nur, wenn die Datumszeit noch in der Zukunft liegt und der Arbeiter keine unvollständige Aufgabe hat. Wenn die Zeit bereits vergangen ist, bevor der erste Scheduler passiert, wird die Aufgabe nicht erstellt.

## Periodisch

Unterstützte Frequenzen:

| Frequenz | Felder | Nächster Start |
| --- | --- | --- |
| `minutely` | Zeit nicht benötigt | Nächste Minute |
| `every_5min` | Zeit nicht benötigt | Nächstgelegene Minutenvielfache von 5 |
| `every_15min` | Zeit nicht benötigt | Nächstgelegene Minutenvielfache von 15 |
| `every_30min` | Zeit nicht benötigt | Nächste Minute in Vielfachen von 30 |
| `hourly` | `time = *:MM` | Nächste Stunde/Minute |
| `daily` | `time = HH:MM` | Heute oder morgen |
| `weekly` | `time`, `days[]` | Nächster Ausgewählter Tag |
| `monthly` | `time` | aktueller Tag des Monats; UI bietet kein separates Tagesfeld |

Beispiel jeden Tag um 02:15:

```json
{
  "enabled": true,
  "type": "periodic",
  "frequency": "daily",
  "time": "02:15"
}
```

Praktische Einschränkung von UI 2.x: Modal hat Zeit, zeigt aber den `days` Editor für wöchentlich und `day` für monatlich nicht an. Solche Werte können nur über JSON-Einstellungen/-Code gespeichert werden; Vor der Produktion sollte man sie mit echtem `stask:worker` überprüfen.

## Regelmäßig im Zeitfenster

```json
{
  "enabled": true,
  "type": "regular",
  "frequency": "every_15min",
  "start_time": "08:00",
  "end_time": "18:00"
}
```

Verfügbare Intervalle: `every_5min`, `every_15min`, `every_30min`, `hourly`. Das Zeitfenster muss innerhalb eines Kalendertages liegen: Wenn `end_time < start_time`, wird die nächste Zeit nicht berechnet. Ein Übernachtfenster wie `22:00–06:00` der aktuellen Implementierung wird nicht unterstützt.

Die Suche nach dem nächsten Slot beginnt bei `start_time` und fügt das Intervall hinzu, bis der Kandidat später als `now` ist. Nach Ende des Fensters gibt die Funktion `null` zurück; Die Aufgabe für den nächsten Tag wird in diesem Pass nicht geschaffen. Dies ist eine wichtige operative Einschränkung: Überprüfen Sie am Ende des Tages das gewünschte Verhalten.

## Vorgesetzter

`type = supervisor` ist nur im Modal für eine Klasse verfügbar, die `SupervisorWorkerInterface` implementiert. Es erstellt nicht jede Minute eine Gesundheitsprüfung. Der Scheduler aktualisiert eine Live-State-Zeile, und Aufgabenzeilen werden nur für sinnvolle Lebenszyklusereignisse erstellt.

Details: [Prozessleiter](supervisor.md).

## Regel einer einzelnen unvollständigen Aufgabe

Für einmalige/periodische/reguläre Aufgaben überprüft der Scheduler die Relation-Worker-Aufgaben mit Scope `incomplete()` und erstellt die nächste Aufgabe nicht, wenn es einen in Warteschlange/Vorbereitung/laufenden Datensatz gibt. Eine lange oder eingefrorene Aufgabe blockiert somit die weitere Planung dieses Bezeichners.

Emergency Stop gibt den Datensatz frei, setzt ihn in failed ein, beendet aber nicht den OS-Prozess. Zuerst setzen Sie, ob der Prozess noch läuft, und erst dann die nächste Aufgabe ausführen.

## Cron und Laravel Terminplaner

Der Anbieter fügt den Befehl hinzu:

```php
$schedule->command(TaskWorker::class)->everyMinute();
```

Diese Definition läuft nicht für sich allein. Die Infrastruktur muss `php artisan schedule:run` jede Minute ausführen oder `schedule:work` unter einem externen Prozessleiter halten.

## Häufige Fehler

- **Nichts wird erstellt** — Arbeiter inaktiv, Zeitplan deaktiviert, keine `taskMake()`, ungültige Zeit oder es gibt bereits eine unvollständige Aufgabe.
- **Einmal übersprungen** — der Planer sah das Datum erst, nachdem es vorbei war.
- **Wöchentlich funktioniert nicht** - `days` Array fehlt.
- **Regular stoppt am Abend** — der aktuelle Algorithmus verschiebt den nächsten Slot nicht auf den nächsten Tag.
- **Duplikate** — mehrere `stask:worker` laufen parallel; Duplicate Check ist kein Atomic Distributed Lock.
