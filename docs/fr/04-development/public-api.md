# API Facade et PHP

La classe de service canonique est `Seiger\sTask\sTask`; façade — `Seiger\sTask\Facades\sTask`. L’alias du compositeur est `sTask` également enregistré, mais l’importation explicite est plus lisible et plus pratique pour une analyse statique.

## Créer une tâche

```php
public function create(
    string $identifier,
    string $action,
    array $data = [],
    string $priority = 'normal',
    ?int $userId = null,
): sTaskModel
```

```php
use Seiger\sTask\Facades\sTask;

$task = sTask::create(
    'catalog_sync',
    'sync_stock',
    ['shop_id' => 7, 'dry_run' => false],
    'normal',
    evo()->getLoginUserID() ?: null,
);
```

La méthode normalise la méta associative par tri récursif et retourne le double actif si l’identifiant/action/méta correspond déjà. Nouveau record : file d’attente, progression 0, tentatives 0, max_attempts 3.

La priorité existe en PHP/schéma pour la compatibilité et l’ordre des files d’attente dans `getPendingTasks()`, mais n’est pas affichée dans les colonnes/filtres actuels de la table du gestionnaire.

## Accomplir une seule tâche

```php
public function execute(sTaskModel $task): bool
```

La méthode écrit des métriques de départ, établit l’exécution, résout le worker via `WorkerService`, appelle l’action, et finalise la tâche si le worker ne l’a pas fait. Une exception envoie une tâche à échec et retourne `false`.

Appelez `execute()` uniquement dans un contexte CLI/file contrôlé. Le flux de manager et le planificateur utilisent le `stask:worker`.

## File d’attente

```php
public function getPendingTasks(int $limit = 10): Collection
public function processPendingTasks(?int $batchSize = null): int
```

`getPendingTasks()` lit le statut `10`, trie la priorité élevée → normal → faible, puis `created_at`. Contrairement à la `TaskWorker` de la ligne de ligne, cette méthode ne filtre pas les `start_at` futures ; Ne l’utilisez pas pour la sémantique du planificateur sans condition supplémentaire.

`processPendingTasks()` appelle `execute()` séquentiellement et renvoie le nombre de tâches réussies.

## Statistiques et indicateurs

```php
public function getStats(): array
public function getPerformanceMetrics(int $hours = 24): array
public function getWorkerStats(?string $identifier = null, int $hours = 24): array
public function getPerformanceAlerts(): array
```

`getStats()` les déclarations comptent les attentes/en cours/complétées/échouées/totales et les ouvriers. API Performance partiellement de place : l’agrégation de durée/mémoire à partir des enregistrements de tâches n’a pas encore été implémentée.

## Registre des workers

```php
public function discoverWorkers(): array
public function registerWorker(string $className): ?sWorker
public function cleanOrphanedWorkers(): int
public function getWorkers(bool $activeOnly = false): Collection
public function getWorker(string $identifier): ?sWorker
public function activateWorker(string $identifier): bool
public function deactivateWorker(string $identifier): bool
```

Discovery fonctionne sur la carte de classes Composer. Après avoir ajouté un cours :

```bash
composer dump-autoload
php artisan package:discover
```

ou cliquez sur rafraîchir le registre dans l’interface. Rappelez-vous : de nouveaux dossiers sont créés inactifs.

## Cache de workers

```php
public function getCacheStats(): array
public function clearWorkerCache(?string $identifier = null): void
```

`WorkerService` possède un cache en mémoire et des entrées de cache Laravel avec le préfixe `stask_worker_`. Effacez un identifiant spécifique après avoir changé les paramètres/la classe ou tout le cache après le rafraîchissement/déploiement du registre.

## Nettoyage de l’histoire

```php
public function cleanOldTasks(int $days = 30): int
```

Ne supprime que les tâches terminées (`status = 80`) avec `finished_at` seuil maximal. Les fichiers échoués, en file d’attente, en cours d’exécution, état superviseur et de progression ne sont pas effacés par cette méthode.

## sTaskModel

Applications et méthodes utiles :

```php
sTaskModel::queued();
sTaskModel::preparing();
sTaskModel::running();
sTaskModel::finished();
sTaskModel::failed();
sTaskModel::incomplete();
sTaskModel::byIdentifier('catalog_sync');
sTaskModel::byAction('make');

$task->markAsRunning();
$task->markAsFinished('Done');
$task->markAsFailed('Reason');
$task->updateProgress(50, 'Half complete');
$task->canRetry();
$task->isFinished();
$task->isRunning();
$task->isPending();
```

`markAsRunning()` écrase `start_at = now()` et augmente les tentatives. `markAsFinished()` mis le progrès à 100. `markAsFailed()` ne mets pas le progrès à 100.

## Meta et Résultat

Le modèle fait couler `meta` et `result` en tant que tableaux, et `start_at`/`finished_at` en dates. Passez les valeurs compatibles JSON. Ne mettez pas de modèles éloquents, de ressources, de clôtures ou de secrets.

Pour un résultat téléchargeable, `BaseWorker::markFinished()` peut obtenir un chemin de chaînes, mais le model cast `result => array` et la logique HTTP de téléchargement ont leurs propres attentes. Vérifiez le contrat des workers concrets et le test de fin ; Ne considérez pas qu’un chemin arbitraire est automatiquement accessible.
