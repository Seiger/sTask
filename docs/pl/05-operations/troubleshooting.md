# Rozwiazywanie problemow

Uzyj tej strony przy typowych problemach managera sTask i migracji workerow.

## Modul nie jest widoczny

Sprawdz provider `Seiger\sTask\sTaskServiceProvider`, instalacje pakietu, rekord
modulu Evolution i uprawnienia. Po migracji uprawnien wykonaj logout/login.

## Manager jest bez styli

Najczesciej CSS/JS nie zostaly opublikowane albo asset zwraca HTML error
response. Opublikuj assets ponownie:

```console
php artisan vendor:publish --tag=evo-ui --force
php artisan vendor:publish --tag=stask --force
```

Shell powinien uzywac `evo::partials.assets`, bez `stask.min.css`, CDN bundles
i legacy manager scripts.

## Access denied

Odswiez sesje managera, potem sprawdz uprawnienia modulu i `cms.settings` z
`sTaskCheck`.

## Worker class not found

`WorkerClassNotFoundException` oznacza problem z autoload, namespace albo
`excluded_namespaces`.

## Worker contract invalid

`WorkerInvalidInterfaceException` oznacza, ze klasa nie spelnia worker contract.
Preferuj `BaseWorker` i jawna mape `handles`.

## Details modal nie otwiera sie

Taski i logi powinny otwierac wspolny EvoUI modal. Sprawdz row action i
`LogsTableData`.

## Artisan command blocked

Sprawdz `config/artisan_security.php`. Dangerous/forbidden commands nie powinny
byc obchodzone przez custom widgets.

