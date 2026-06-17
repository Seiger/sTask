# Troubleshooting

Use this page to diagnose common sTask manager and worker migration failures.

## Module Is Missing

Check that `Seiger\sTask\sTaskServiceProvider` is registered in `composer.json`,
the package was installed in the demo site, and the Evolution manager module
record exists. If permissions were just migrated, log out and log in again.

## Manager Renders Unstyled HTML

This usually means CSS or JavaScript was not published or the manager is loading
an HTML error response as an asset. Re-publish local assets:

```console
php artisan vendor:publish --tag=evo-ui --force
php artisan vendor:publish --tag=stask --force
```

The EvoUI shell should include `evo::partials.assets`. It should not load
`stask.min.css`, CDN bundles, or legacy manager scripts.

## Access Denied

First refresh the manager session by logging out and back in. Then check module
permissions and the `cms.settings` registration from `sTaskCheck`.

## Worker Class Not Found

`WorkerClassNotFoundException` means the configured class cannot be autoloaded.
Check Composer autoload, namespace spelling, and `excluded_namespaces`.

## Worker Contract Is Invalid

`WorkerInvalidInterfaceException` means the class does not implement the worker
contract. Prefer extending `BaseWorker` and declaring the required identity
methods and `handles` actions.

## Task Details Do Not Open

Tasks and logs should open details through the shared EvoUI modal. Check that
the row action points to the details action and that `LogsTableData` can load
the selected task record.

## Artisan Command Is Blocked

Check `config/artisan_security.php`. Dangerous or forbidden commands should not
be bypassed from custom widgets.

