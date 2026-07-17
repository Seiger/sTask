# FAQ

## Ist sTask eine Laravel Queue?

Nein. sTask verfügt über eigene Eloquent-Tabellen, einen Arbeitervertrag und einen eigenen Run-and-Exit-Befehl. Es verwendet nicht die Broker/Warteschlangenverbindung Laravel als Hauptausführungs-Engine.

## Brauche ich Redis?

Nicht für einfache Arbeiten. Worker Cache/Metriken verwenden Laravel Cache, und Supervisor Lock nimmt explizit Store `file`. Der konfigurierte Anwendungscache sollte weiterhin in gutem Zustand sein.

## Gibt es ein SSE oder WebSocket?

Nein. Live Progress ist ein adaptiver HTTP-Polling-Endpunkt `/stask/task/{id}/progress`.

## Erledigt die Starttaste die Aufgabe im Hintergrund?

Der Controller versucht, die FastCGI-Antwort zu schließen und den CLI-Worker zu starten; Das Rückfallback kann synchron sein oder aufgrund deaktivierter Funktionen nicht ausgelöst werden. Cron/Scheduler ist ein verlässlicher Produktionspfad.

## Stoppt der Notfall den PHP-Prozess?

Nein. Es übersetzt nur den Datenbankeintrag in 'failed übersetzt'. Der OS-Prozess muss separat gestoppt werden.

## Gibt es automatische Wiederholungen?

Nein. Versuche/maximale Versuche werden gespeichert, aber fehlgeschlagene Zeilen werden nicht automatisch durch den Standardbefehl wieder in die Warteschlange gesetzt.

## Was bedeutet `system` im User Filter?

Aufgaben mit `started_by` null oder `<= 0`, einschließlich Scheduler-/Supervisor-Events.

## Warum zeigt der Arbeiterfilter den Namen und nicht die Kennung an?

Der Anbieter löst `worker->title` auf und verwendet die Kennung nur als Rückfall. Abfrage filtert Aufgaben nach Identifikatoren, die mit ausgewählten Worker-IDs verknüpft sind.

## Warum wurde eine Aufgabe mit derselben Nutzlast nicht ein zweites Mal erstellt?

Die aktive Duplikatunterdrückung vergleicht den Identifikator, die Aktion und die normalisierte Meta für Status in Warteschlange/Vorbereitung/Ausführung und gibt das bestehende Modell zurück.

## Ist Duplikatunterdrückung rassensicher?

Nicht ganz. Es gibt keinen eindeutigen Schlüssel für den aktiven Payload-Hash im Schema. Parallele Prozesse können gleichzeitig einen Lookup durchlaufen.

## Wo wird der Fortschritt gespeichert?

`core/storage/stask/{taskId}.log`. Die Datenbank `progress` wird separat aktualisiert und zeigt nicht automatisch jedes Dateisnapshot an.

## Warum gibt es keinen Fortschritt, aber die Aufgabe funktioniert?

Dateischreibfehler werden absichtlich ignoriert, um die Geschäftsaufgabe nicht zu hacken. Überprüfen Sie Berechtigungen und Anwendungsprotokolle.

## Wie lösche ich meine Geschichte?

`sTask::cleanOldTasks($days)` löscht nur alte, fertige Datenbank-Aufgaben. Fehlgeschlagene Aufgaben, `.log`, Uploads/Ergebnisse und der Supervisor-Status erfordern eine separate Richtlinie.

## Wie betreibe ich mehrere Arbeiter parallel?

Die aktuelle CLI hat keinen atomaren Multiprozess-Anspruch. Skalieren Sie Prozesse nicht horizontal ohne ein neues Claim/Lease-Design und eine neue Impotenz.

## Warum hat der reguläre Zeitplan nicht am nächsten Tag eine Aufgabe erstellt?

Der Algorithmus sucht nur im aktuellen Tagesfenster nach Kandidaten und liefert nach Ende Null zurück. Dies ist eine bekannte Einschränkung der aktuellen Implementierung.

## Wie unterscheidet sich ein Supervisor-Plan von einer regelmäßigen Gesundheitskontrolle?

Der Supervisor aktualisiert jede Minute eine Live-State-Zeile und erstellt eine Aufgabe nur für ein sinnvolles Lebenszyklusereignis. Periodic Schedule unterstützt reguläre Warteschlangen `taskMake`.

## Wird der Vorgesetzte vom Arbeitnehmer entfernt?

Nein, automatisch: Die Relation hat keinen DB-Fremdschlüssel oder keine Kaskade. Waisenreihen räumen nach dem Inventar auf.
