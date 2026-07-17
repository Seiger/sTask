# Configuration

## `config/sTaskCheck.php`

Fusionnant en `cms.settings`:

| Clé | Par défaut | Signification |
| --- | --- | --- |
| `check_sTask` | `true` | Présence du paquet/Drapeau de contrôle |
| `sTaskVer` | `2.9999999.9999999.9999999-dev` | Marqueur de version de la branche de développement |

Ce n’est pas un réglage à l’exécution du travail.

## Préréglages de table

| Fichier | Clé de configuration | Surface |
| --- | --- | --- |
| `config/tasks/table.php` | `stask.tasks.table` | Tâches |
| `config/workers/table.php` | `stask.workers.table` | Ouvriers |
| `config/logs/table.php` | `stask.logs.table` | Journaux |

Les préréglages définissent le fournisseur, les méthodes de fil, la pagination, les vues, les filtres, les colonnes, le mode et les actions. Pour le remplacement de projet, utilisez le mécanisme de configuration personnalisé Evolution ou le point de publication/extension si votre version le supporte ; Ne modifiez pas les fichiers des fournisseurs.

## `config/excluded_namespaces.php`

Liste des préfixes d’espace de noms que `WorkerDiscovery` ne prend pas en compte. Par défaut, les espaces framework/fournisseurs comme `Illuminate\`, `Symfony\`, `EvolutionCMS\Legacy\`, `PHPUnit\` , etc. sont exclus.

Si votre worker se trouve sous le préfixe exclus, déplacez-le dans l’espace de noms paquet/projet. Ne raccourcissez pas la liste sans analyse : la découverte peut commencer à instancier des milliers de classes tierces.

## `config/artisan_security.php`

Utilisé `ArtisanWorker`:

| Clé | Par défaut |
| --- | --- |
| `dangerous_commands` | `migrate:fresh`, `migrate:reset`, `db:wipe` |
| `confirmation_required` | `migrate`, `migrate:refresh`, `migrate:rollback`, `db:seed`, `cache:clear-full` |
| `whitelist` | vide : tous sauf bloqués |
| `blacklist` | vide |
| `enabled` | `true` |
| `log_executions` | `true` |
| `required_permission` | `run_artisan` |

Les patrons whitelistés prennent en charge `*` pour les commentaires de contrat. En production, laissez la sécurité activée et formez une liste blanche explicite pour les besoins opérationnels.

## Réglages des workers

Stocké en `s_workers.settings` JSON :

```json
{
  "schedule": {
    "enabled": true,
    "type": "periodic",
    "datetime": "",
    "frequency": "hourly",
    "time": "*:10",
    "start_time": "",
    "end_time": ""
  },
  "http": {
    "timeout": 15
  }
}
```

`BaseWorker` API:

```php
$worker->settings();
$worker->getConfig('http.timeout', 10);
$worker->setConfig('http.timeout', 20);
$worker->updateConfig(['endpoint' => 'https://example.test']);
$worker->getSchedule();
$worker->shouldRunNow();
```

`TaskWorker` calcule indépendamment la prochaine exécution ; `shouldRunNow()` est un assistant côté worker et n’est pas la décision principale de planificateur dans la CLI.

## Conteneur de service

Célibataires :

```php
app(Seiger\sTask\sTask::class);
app(Seiger\sTask\Services\WorkerService::class);
app(Seiger\sTask\Services\MetricsService::class);
app(Seiger\sTask\Services\SupervisorService::class);
```

Accessoire de façade : `sTask`.

## Stockage

| Chemin | Données |
| --- | --- |
| `core/storage/stask/{id}.log` | Progrès uniquement en annexe |
| `core/storage/stask/uploads` | Contrôleur/worker de fichiers de téléchargement/résultat |
| cache Laravel | instances de worker, métriques, verrouillages de superviseurs |

Le fournisseur ne crée que la racine `storage/stask`. Les sous-répertoires sont créés par le chemin de code correspondant.

## Métadonnées de paquet pour dDocs

dDocs indique :

- Nom du compositeur `seiger/stask`;
- les `lang/{locale}/global.php` localisées `module_title`, `module_description`, `module_icon`;
- `docs/{locale}`.

Métadonnées attendues :

```php
'module_title' => 'sTask',
'module_icon' => 'tabler-progress-check',
```

`docs/docs.json` est un manifeste d’inventaire portable. L’exécution actuelle de dDocs peut ne pas utiliser manifest directement ; Discoverability offre un scan de paquet Composer et un arbre de documentation localisé physique.
