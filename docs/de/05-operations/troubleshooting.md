# Fehlerbehebung

Diese Seite hilft bei typischen sTask Manager- und Worker-Migrationsproblemen.

## Modul fehlt

Pruefe `Seiger\sTask\sTaskServiceProvider`, Paketinstallation, Evolution
Moduldatensatz und Berechtigungen. Nach Permission-Migrationen logout/login.

## Manager ist ungestylt

Meist wurden CSS/JS nicht publiziert oder ein Asset liefert HTML error response.

```console
php artisan vendor:publish --tag=evo-ui --force
php artisan vendor:publish --tag=stask --force
```

Der Shell soll `evo::partials.assets` nutzen und keine `stask.min.css`, CDN
bundles oder legacy manager scripts laden.

## Access denied

Manager session erneuern, dann Modulberechtigungen und `cms.settings` aus
`sTaskCheck` pruefen.

## Worker class not found

`WorkerClassNotFoundException` weist auf Autoload, Namespace oder
`excluded_namespaces` hin.

## Worker contract invalid

`WorkerInvalidInterfaceException` bedeutet, dass die Klasse den worker contract
nicht erfuellt. Nutze bevorzugt `BaseWorker` und `handles`.

## Details modal oeffnet nicht

Tasks und Logs sollen das gemeinsame EvoUI modal oeffnen. Pruefe row action und
`LogsTableData`.

## Artisan command blocked

Pruefe `config/artisan_security.php`. Dangerous/forbidden commands duerfen nicht
durch custom widgets umgangen werden.

