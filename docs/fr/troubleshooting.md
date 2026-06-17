# Depannage

Cette page aide a diagnostiquer les problemes courants du manager sTask et de la
migration des workers.

## Module absent

Verifie `Seiger\sTask\sTaskServiceProvider`, l'installation du paquet, le record
module Evolution et les permissions. Apres migration des permissions: logout/login.

## Manager sans style

Souvent CSS/JS ne sont pas publies ou un asset renvoie une HTML error response.

```console
php artisan vendor:publish --tag=evo-ui --force
php artisan vendor:publish --tag=stask --force
```

Le shell doit utiliser `evo::partials.assets`, sans `stask.min.css`, CDN bundles
ni legacy manager scripts.

## Access denied

Rafraichis la session manager, puis verifie les permissions module et
`cms.settings` depuis `sTaskCheck`.

## Worker class not found

`WorkerClassNotFoundException` indique un probleme d'autoload, namespace ou
`excluded_namespaces`.

## Worker contract invalid

`WorkerInvalidInterfaceException` signifie que la classe ne respecte pas le
worker contract. Preferer `BaseWorker` et une map `handles` explicite.

## Details modal ne s'ouvre pas

Les taches et logs doivent ouvrir le meme EvoUI modal. Verifie row action et
`LogsTableData`.

## Artisan command blocked

Verifie `config/artisan_security.php`. Les dangerous/forbidden commands ne
doivent pas etre contournees par custom widgets.

