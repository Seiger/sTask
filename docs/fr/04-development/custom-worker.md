# Propre ouvrier

Le moyen le plus simple est de suivre `BaseWorker`. La classe reçoit ensuite la création de tâches, les réglages de planning, l’envoi des actions, la progression et la finalisation.

## Exemple complet

```php
<?php

namespace EvolutionCMS\Custom\Workers;

use EvolutionCMS\Models\SiteContent;
use Seiger\sTask\Models\sTaskModel;
use Seiger\sTask\Workers\BaseWorker;
use Throwable;

final class DocumentAuditWorker extends BaseWorker
{
    public function identifier(): string
    {
        return 'document_audit';
    }

    public function scope(): string
    {
        return 'custom';
    }

    public function icon(): string
    {
        return '<i data-lucide="database-search"></i>';
    }

    public function title(): string
    {
        return 'Audit des documents';
    }

    public function description(): string
    {
        return 'Compte les documents publiés et supprimés par lots.';
    }

    public function taskMake(sTaskModel $task, array $options = []): void
    {
        $batchSize = max(1, min(500, (int)($options['batch_size'] ?? 100)));
        $total = SiteContent::query()->count();
        $processed = 0;
        $published = 0;
        $deleted = 0;
        $startedAt = microtime(true);

        try {
            SiteContent::query()
                ->select(['id', 'published', 'deleted'])
                ->orderBy('id')
                ->chunkById($batchSize, function ($documents) use (
                    $task,
                    $total,
                    $startedAt,
                    &$processed,
                    &$published,
                    &$deleted,
                ): void {
                    foreach ($documents as $document) {
                        $processed++;
                        $published += (int)$document->published;
                        $deleted += (int)$document->deleted;
                    }

                    $progress = $total > 0 ? (int)floor($processed * 100 / $total) : 100;
                    $etaSeconds = $processed > 0
                        ? (int)round((microtime(true) - $startedAt) / $processed * ($total - $processed))
                        : 0;

                    $this->pushProgress($task, [
                        'status' => 'running',
                        'progress' => $progress,
                        'processed' => $processed,
                        'total' => $total,
                        'eta' => niceEta((float)$etaSeconds),
                        'message' => "**{$processed}** documents vérifiés sur **{$total}**",
                    ]);
                });

            $task->update(['progress' => 100]);
            $this->markFinished($task, null, "Audit terminé : {$processed} documents");
            $task->update(['result' => [
                'processed' => $processed,
                'published' => $published,
                'deleted' => $deleted,
            ]]);
        } catch (Throwable $exception) {
            report($exception);
            $this->markFailed($task, 'Échec de l\'audit : ' . $exception->getMessage());
        }
    }
}
```

L’exemple ne comporte aucune dépendance d’application : il lit le modèle standard Evolution CMS et ne modifie pas les documents. Le `markFinished()` courant écrit toujours son argument de chaîne annulable à `result`, tandis que le modèle projette le champ sous forme de tableau. Par conséquent, l’exemple de résultat structuré s’écrit séparément **après** `markFinished()`; C’est l’ordre réel qui empêche l’assistant d’effacer la valeur du tableau. La signature de base de l’action est strictement réelle : `taskMake(sTaskModel $task, array $options = []): void`.

## Méthodes requises

`TaskInterface` exige :

- `identifier(): string`;
- `scope(): string`;
- `icon(): string`;
- `title(): string`;
- `description(): string`;
- `renderWidget(): string`;
- `settings(): array`.

`BaseWorker` met déjà en place `renderWidget()` et `settings()`. La classe Concrete ajoute des métadonnées et des méthodes d’action.

## Actions de nommage

`invokeAction()` passe l’action en minuscules, remplace `-`/`_` par des mots, et ajoute un préfixe `task`:

| Action | Méthode |
| --- | --- |
| `make` | `taskMake` |
| `sync_stock` | `taskSyncStock` |
| `import-csv` | `taskImportCsv` |

Si la méthode manque, un `BadMethodCallException` est lancé et l’ouvrier CLI marque la tâche échouée.

## Inscription

1. Ajouter une classe au paquet/projet d’espace de noms PSR-4.
2. Mettre à jour la carte de classes du compositeur.
3. Découverte du lancement.
4. Activez l’ouvrier.

```bash
cd core
composer dump-autoload
php artisan package:discover
```

Ou programmatiquement :

```php
use Seiger\sTask\Facades\sTask;

sTask::registerWorker(DocumentAuditWorker::class);
sTask::activateWorker('document_audit');
```

L’identifiant doit être stable et unique. Rescan peut modifier l’enregistrement persisté de l’identifiant, mais `s_tasks.identifier` existants ne migrent pas automatiquement.

## Créer une tâche à partir d’un ouvrier

```php
$task = $worker->createTask('make', [
    'batch_size' => 100,
    'manual' => true,
]);
```

Si les options ne sont pas adoptées, `createTask()` prend `options` de requêtes et `filename` directes. Pour le code CLI/programmé, passez toujours explicitement les options.

## Progrès

```php
$this->pushProgress($task, [
    'status' => 'running',
    'progress' => 42,
    'processed' => 420,
    'total' => 1000,
    'eta' => niceEta(37),
    'message' => 'Traitement du **lot 5**',
]);
```

Cela écrit le journal des fichiers, mais ne met pas à jour le champ `s_tasks.progress`. Si des progrès persistants sont nécessaires pour les tables finales/récupération, mettez à jour le modèle sur les points de contrôle, pas sur chaque élément.

Le message doit être en une seule ligne ; `TaskProgress` remplace les coupures de ligne par `<br>` et les `|` de tuyau par `¦`.

## Complétions et erreurs

```php
$this->markFinished($task, null, 'Terminé');
$task->update(['result' => ['count' => 420]]);
```

```php
$this->markFailed($task, 'L\'API externe a renvoyé 503');
```

Si action lance une exception, `TaskWorker` définira également échec et écrira le nom du fichier/la ligne/erreur dans le journal de progression. Ne détectez les exceptions que lorsque vous pouvez ajouter du contexte ou un nettoyage ; sinon, laissez le runner confirmer centralement fail.

## Stratégie de réessai

Le package compte les tentatives, mais ne remet pas automatiquement les lignes ratées. Pour réessayer :

- rendre l’opération commerciale idempotente ;
- Identifier les types d’erreurs réessayables ;
- créer une nouvelle tâche ou retourner délibérément un enregistrement échoué en file d’attente ;
- appliquer un recul exponentiel après `start_at`;
- Ne répétez pas les erreurs de validation/authentification.

## Réglages des workers

```php
$timeout = (int)$this->getConfig('http.timeout', 10);
$this->setConfig('http.timeout', 20);
$this->updateConfig(['endpoint' => 'https://example.test']);
```

`updateSettings()` fait des `array_merge` peu profondes ; Les structures imbriquées peuvent être entièrement remplacées lors de la mise à jour.

## Widget personnalisé

Le dérogation `renderWidget()` uniquement lorsque l’exécuteur de tâches par défaut d’EvoUI est insuffisant. Retourner la vue Blade rendue du jour, échapper les données utilisateur et utiliser les routes du gestionnaire avec CSRF. N’intégrez pas de secrets dans un descripteur ou un HTML.

## Prolongation du superviseur

Si l’ouvrier possède un démon détaché, ajoutez- `SupervisorWorkerInterface` mais ne retirez pas le `TaskInterface` régulier. Contrat complet : [Superviseur de processus](../02-concepts/supervisor.md).
