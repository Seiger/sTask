# Reference

Cette page liste les signaux stables du paquet pour dDocs, les integrations et
les agents de migration.

## Service Provider

`Seiger\sTask\sTaskServiceProvider` enregistre le module manager Evolution, les
routes, migrations, vues, traductions, le composant Livewire, la commande
console et les presets de tables EvoUI.

| Signal | Role |
| --- | --- |
| `cms.settings` | Reglages manager depuis `config/sTaskCheck.php`. |
| `stask.tasks.table` | Preset table des taches depuis `config/tasks/table.php`. |
| `stask.workers.table` | Preset table des workers depuis `config/workers/table.php`. |
| `stask.logs.table` | Preset table des logs depuis `config/logs/table.php`. |
| `tasks.table` | Nom de configuration publiee pour les taches. |
| `workers.table` | Nom de configuration publiee pour les workers. |
| `logs.table` | Nom de configuration publiee pour les logs. |
| `sTaskAlias` | Alias du package plugin bridge. |
| `sTaskCheck` | Source des reglages d'enregistrement du module. |

## Runtime Classes

| Class | Responsibility |
| --- | --- |
| `DashboardData` | Cartes dashboard, dernieres taches et dernieres erreurs. |
| `MetricsService` | Metriques runtime et statistiques. |
| `PublishAssets` | Publie les manager assets du paquet et maintient les fichiers EvoUI-facing. |
| `ModulePanel` | Panneau manager Livewire + EvoUI. |
| `TasksTableData` | Donnees de table/liste des taches. |
| `WorkersTableData` | Donnees de table/liste des workers. |
| `LogsTableData` | Donnees des logs et details de tache. |
| `TaskProgress` | Normalise progression et messages. |
| `WorkerDiscovery` | Decouvre les worker classes et applique namespace exclusions. |
| `WorkerService` | Cree et controle l'execution des taches worker. |
| `BaseWorker` | Classe de base des custom workers. |
| `ArtisanWorker` | Worker integre pour commandes Artisan autorisees. |
| `ComposerUpdateWorker` | Worker integre pour maintenance Composer. |
| `TaskWorker` | Worker console qui traite la queue. |

## Exceptions

| Exception | Meaning |
| --- | --- |
| `WorkerNotFoundException` | Worker identifier introuvable. |
| `WorkerClassNotFoundException` | Worker class non chargeable. |
| `WorkerInvalidInterfaceException` | Class non conforme au worker contract. |

## Worker Contract

Un worker declare `identifier()`, `scope()`, `title()`, `description()` et
`settings()`. La map `handles` decrit les actions. Discovery peut ignorer des
namespaces via `excluded_namespaces`.

## Console Commands

```console
php artisan stask:worker
php artisan stask:publish
php artisan stask:publish --no-prune
```

Signature complete de publish:
`stask:publish {--no-prune : Do not delete existing files before publish}`.
