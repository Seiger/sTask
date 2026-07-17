# Datenbanktabellen

Nachfolgend sind die tatsächlichen Schema-Migrationen des 2.x-Zweigs aufgeführt.

## `s_workers`

| Spalte | Typ/Attribute | Zweck |
| --- | --- | --- |
| `id` | BIGINT PK | interne Mitarbeiter-ID |
| `uuid` | UUID nullable unique | optionale externe Identität |
| `identifier` | String-Unique | Stabiler Aufgaben-Routingschlüssel |
| `scope` | String-Standard `''` | Paket-/Modulgruppierung |
| `class` | String | FQCN |
| `active` | Boolean Default False | Scheduler/Run Gate |
| `position` | Unsigned Int Default 0 | UI-Reihenfolge |
| `settings` | JSON-Standard-Leere-Array-Ausdruck | Zeitplan/benutzerdefinierte Einstellungen |
| `hidden` | Unsigned Int Default 0 | UI-Sichtbarkeit |
| Zeitstempel | erstellt/aktualisiert | Prüfung |

Indizes: eindeutige UUID, eindeutige Identifikator, plus Kennung, Umfang, aktiv, Positionsindizes. Die eindeutige Identifikatorin erstellt bereits einen Index; zusätzlicher expliziter Index kann je nach Datenbank redundant sein.

## `s_tasks`

| Spalte | Typ/Attribute | Zweck |
| --- | --- | --- |
| `id` | BIGINT PK | Aufgabe-ID |
| `identifier` | String | Arbeiter-Routing-Schlüssel |
| `action` | String | Aktionscode |
| `status` | Unsigned small int default 10 | Lebenszyklus |
| `message` | Text nullierbar | Persistente Zusammenfassung/Fehler |
| `started_by` | Unsigned int nullable | Manager Benutzer oder System |
| `meta` | longText nullable | Eingabemetadaten |
| `result` | longText nullable | Ergebnis Nutzlast/Pfad |
| `start_at` | Zeitstempel-nullable | geplanter/tatsächlicher Start |
| `finished_at` | Zeitstempel-nullable | Letztes Mal |
| `attempts` | int default 0 | erhöht beim Laufen |
| `max_attempts` | int default 3 | wiederholte Metadaten |
| `priority` | String-Standardnormal | Kompatibilität/Warteschlangenreihenfolge |
| `progress` | int default 0 | anhaltender Fortschritt |
| Zeitstempel | erstellt/aktualisiert | Prüfung |

Indizes: `(identifier, action)`, Status, started_by, start_at, created_at, Priorität.

Es gibt keinen Fremdschlüssel von der Task-Identifikatorin auf `s_workers.identifier`, daher überlebt die Historie die Löschung des Arbeiterdatensatzes. Die Relation funktioniert logisch nach Identifikator.

Meta/result beschreibt das Eloquent-Modell als Array; der tatsächliche Speicher ist langer Text, kein natives JSON.

## `s_supervisor_states`

| Spalte | Typ/Attribute | Zweck |
| --- | --- | --- |
| `id` | BIGINT PK | Staatsreihen-ID |
| `worker_id` | unsignierte BIGINT, indexiert | Besitzer `s_workers.id` ohne FK |
| `identifier` | String, indexiert | Denormalisierter Arbeiterschlüssel |
| `supervisor_key` | String | Adapterprozessidentität |
| `key_hash` | char(64) einzigartig | sha256 Arbeiter-ID + Schlüssel |
| `state` | string(24), standardmäßig gestoppt, indexiert | Lebenszykluszustand |
| `pid` | unsigniert BIGINT nullabel | Prozess-ID |
| `heartbeat_at` | Zeitstempel-nullable | Letzter Herzschlag |
| `supervisor_started_at` | Zeitstempel-nullable | Prozessstart |
| `uptime_seconds` | unsigniert BIGINT nullabel | Adapter-Verfügbarkeit |
| `message` | Text nullierbar | Diagnostik |
| `fingerprint` | char(64) nullierbar | Dedup-Fingerabdruck |
| `last_transition_at` | Zeitstempel-nullable | Zustandsübergang |
| `last_seen_at` | Zeitstempel-nullierbar, indexiert | Letzte Beobachtung des Schedulers |
| `repeat_count` | Unsigned Int Default 0 | Wiederholte Fingerabdruckzählung |
| `launch_requested_at` | Zeitstempel-nullable | Start-Grace-Cursor |
| Zeitstempel | erstellt/aktualisiert | Unterlagenprüfung |

### Lebenszyklus

`firstOrNew(key_hash)` garantiert eine Live-Zeile pro Arbeiter-ID + stabilen Schlüssel. Jeder Durchgang aktualisiert die zuletzt gesehene Version; Derselbe Fingerabdruck erhöht die Anzahl der Wiederholungen, der neue Fingerabdruck setzt sie auf null zurück. Statusübergangsupdates `last_transition_at`.

Gesunder Status reinigt `launch_requested_at`. Andere Statusse behalten die vorherige oder aktuelle Startanforderung bei.

### Wachstum & Reinigung

Heartbeat-Geschichte sammelt keine Reihen an. Wachstum bedeutet neue Arbeiter-IDs oder Schlüssel. Es gibt keine automatische Aufbewahrung, keine Fremdschlüssel oder Kaskade.

Sichere Reinigung:

1. Inventar tatsächlicher Arbeiter und Adapterschlüssel;
2. Stelle sicher, dass Daemon mit dem Alten Schlüssel nicht funktioniert;
3. Archivierung der Diagnostik, falls erforderlich;
4. Lösche nur die exakten Orphan-IDs/Hashes.

Machen Sie während des aktiven Schedulers keine große `TRUNCATE` .

## Berechtigungstabellen

Migration, falls Systemtabellen existieren:

- findet/erzeugt eine Gruppe `sTask`;
- Upsert-it-Erlaubnis `stask` mit `disabled = 0`;
- fügt role_permissions für Rolle `1` hinzu;
- auf PostgreSQL kann die Sequenz nach Einfügungskonflikten wiederherstellen.

Die Migration deaktiviert den Laravel-Transaktionswrapper, weil PostgreSQL die Transaktion nach der fehlgeschlagenen Anweisung abbricht und der Code einen Retry-Pfad hat.

## Portabilität

Schema konzentriert sich auf MySQL/MariaDB/PostgreSQL/SQLite über den Laravel Schema Builder. JSON-Standardausdruck `JSON_ARRAY()` DB-sensitiv ist; Führen Sie Migrationstests auf der Ziel-Engine durch. Die Berechtigungsmigration verwaltet PostgreSQL-Sequenzen separat.
