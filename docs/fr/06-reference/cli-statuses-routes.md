# CLI, statuts et routes

## CLI

### `stask:worker`

```bash
php artisan stask:worker
```

Aucune option. Crée des tâches planifiées, supervise les workers démons, traite toutes les tâches prêtes en file d’attente séquentiellement, exécute le cleanup-if-idle et renvoie le code de sortie 0 même si les tâches individuelles échouent (les exceptions sont détectées par tâche).

Prestataire programmé : à chaque minute.

### `stask:publish`

```bash
php artisan stask:publish
```

Copies emballent les actifs vers les `assets/site` publiques ; Utilise `Filesystem`. Après avoir mis à jour le paquet, relancez-le.

### Commandes d’évolution associées

```bash
php artisan migrate
php artisan package:discover
php artisan route:list --path=stask
php artisan schedule:list
php artisan cache:clear-full
```

Leur disponibilité exacte dépend de la version CMS d’Evolution.

## Statut de la tâche

| Code | Constante PHP | Texte API | Actif | Final |
| ---: | --- | --- | --- | --- |
| 10 | `TASK_STATUS_QUEUED` | en attente | oui | non |
| 30 | `TASK_STATUS_PREPARING` | Préparation | oui | non |
| 50 | `TASK_STATUS_RUNNING` | Running | oui | non |
| 80 | `TASK_STATUS_FINISHED` | terminé | non | oui |
| 100 | `TASK_STATUS_FAILED` | Échec | non | oui |

Code inconnu → `unknown`.

## État du superviseur

| Valeur | Signification | nécessiteLancement |
| --- | --- | --- |
| `healthy` | Processus disponible | non |
| `starting` | Démarrage en cours | non |
| `degraded` | La santé s’est détériorée | oui |
| `failed` | Échec d’inspection/lancement | oui |
| `stopped` | Processus arrêté/non trouvé | oui |

## Valeurs du calendrier

Types : `manual`, `once`, `periodic`, `regular`, `supervisor`.

Fréquences :

- périodique : `minutely`, `every_5min`, `every_15min`, `every_30min`, `hourly`, `daily`, `weekly`, `monthly`;
- régulier : `every_5min`, `every_15min`, `every_30min`, `hourly`.

## Routes de manager

Tous ont un préfixe `/stask`, un préfixe de nom de route `sTask.` et un middleware `mgr`. Tableau complet avec méthodes et contexte de charge utile : [Itinéraires, fichiers de progression et téléchargements](../04-development/routes-and-progress.md).

Clé :

```text
GET  /stask
POST /stask/worker/{identifier}/run/{action}
GET  /stask/task/{id}/progress
GET  /stask/task/{id}
GET  /stask/performance/summary
POST /stask/cache/clear
```

## Cours publics de PHP

| Classe | Rôle |
| --- | --- |
| `Seiger\sTask\sTask` | Service de façade |
| `Seiger\sTask\Facades\sTask` | Façade de Laravel |
| `Seiger\sTask\Workers\BaseWorker` | Base ouvrière |
| `Seiger\sTask\Contracts\TaskInterface` | Contrat de travail |
| `Seiger\sTask\Contracts\SupervisorWorkerInterface` | Capacité démoniaque |
| `Seiger\sTask\Support\SupervisorStatus` | Instantané immuable de santé |
| `Seiger\sTask\Enums\SupervisorState` | États démons |
| `Seiger\sTask\Models\sTaskModel` | tâche Modèle éloquent |
| `Seiger\sTask\Models\sWorker` | ouvrier Modèle éloquent |
| `Seiger\sTask\Models\sSupervisorState` | Modèle d’état en direct |
| `Seiger\sTask\Services\TaskProgress` | Progression du fichier |
| `Seiger\sTask\Services\WorkerDiscovery` | Découverte du registre |
| `Seiger\sTask\Services\WorkerService` | Résolution/cache |
| `Seiger\sTask\Services\SupervisorService` | Supervision du cycle de vie |
| `Seiger\sTask\Services\MetricsService` | Statistiques / Métriques de cache |

## Exceptions

- `WorkerNotFoundException`;
- `WorkerClassNotFoundException`;
- `WorkerInvalidInterfaceException`.

Chaque exception de résolution possède une charge utile contextuelle pour la journalisation. La couche Manager/API peut les convertir en messages JSON ; Ne pas afficher l’utilisateur final de la trace de la pile.

## Aides à la mise en forme dans l’interface utilisateur

- `niceCount(int|float)` — comptage compact ;
- `niceSize(bytes)` — taille lisible ;
- `niceEta(seconds)` — durée/ETA/temps de fonctionnement.

Ce sont des aides à runtime Evolution/evo-UI, pas une façade sTask.
