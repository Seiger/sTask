# Itinéraires, fichiers d’avancement et téléchargements

## Statut API

Toutes les routes ci-dessous sont dans le groupe middleware manager `mgr`. Il s’agit d’une surface HTTP interne du manager, et non d’une API REST publique pour les clients externes. Utilisez l’authentification de session et le CSRF pour le POST.

## Itinéraires

| Méthode | Chemin | Nom de l’itinéraire | But |
| --- | --- | --- | --- |
| GET | `/stask` | `sTask.index` | Shell de module |
| GET | `/stask/stats` | `sTask.stats` | Compteurs |
| POST | `/stask/task` | `sTask.task.create` | Création de la tâche de façade |
| POST | `/stask/task/store` | `sTask.task.store` | alias Create |
| GET | `/stask/task/{id}` | `sTask.task.show` | Détails complets de la tâche |
| POST | `/stask/worker/{identifier}/run/{action}` | `sTask.worker.task.run` | créer + agent de lancement |
| GET | `/stask/task/{id}/progress` | `sTask.task.progress` | Résumé de la progression/historique |
| GET | `/stask/task/{id}/download` | `sTask.task.download` | Téléchargement du résultat |
| POST | `/stask/task/{id}/upload` | `sTask.task.upload` | Téléchargement lié à la tâche |
| POST | `/stask/worker/{identifier}/upload` | `sTask.worker.upload` | Téléversement de l’ouvrier avant la tâche |
| POST | `/stask/clean` | `sTask.clean` | supprimer les anciennes tâches terminées |
| GET | `/stask/server-limits` | `sTask.serverLimits` | Limites de téléversement PHP |
| GET | `/stask/workers` | `sTask.workers` | découvrir et rediriger |
| POST | `/stask/worker/clean-orphaned` | `sTask.worker.clean` | Supprimer les classes manquantes |
| POST | `/stask/worker/activate` | `sTask.worker.activate` | activer par identifiant |
| POST | `/stask/worker/deactivate` | `sTask.worker.deactivate` | désactiver par identifiant |
| GET | `/stask/performance/summary` | `sTask.performance.summary` | Résumé des métriques |
| GET | `/stask/performance/workers` | `sTask.performance.workers` | Statistiques des workers |
| GET | `/stask/performance/alerts` | `sTask.performance.alerts` | alertes |
| GET | `/stask/cache/stats` | `sTask.cache.stats` | Statistiques de la cache des workers |
| POST | `/stask/cache/clear` | `sTask.cache.clear` | Effacer le cache des workers |

## Action de lancement

```http
POST /stask/worker/search_index/run/make
Content-Type: application/json
X-CSRF-TOKEN: ...

{"batch_size":100,"force":false}
```

Le contrôleur prend les `options` imbriqués ou tout le corps, retire `_token` et `options`, résout le worker actif et appelle `createTask()`.

Réponse réussie :

```json
{"success":true,"id":123,"message":"Task created successfully"}
```

Le code HTTP peut rester à 200 même lorsqu’il est `success=false`; Le client doit vérifier le drapeau JSON.

Après que le contrôleur de réponse utilise `fastcgi_finish_request()` ou un repli synchrone, tente d’exécuter `stask:worker`. Cela ne garantit pas un processus de file séparé sur tous les SAPI.

## Fin de progression

```http
GET /stask/task/123/progress?include_log=0
Accept: application/json
```

Success:

```json
{
  "success": true,
  "code": 200,
  "id": 123,
  "status": "running",
  "progress": 42,
  "processed": 420,
  "total": 1000,
  "eta": "37s",
  "message": "Traitement du lot",
  "log_lines": []
}
```

Sans `include_log=0` point de terminaison ajoute les 50 derniers messages. L’ID invalide retourne 400 ; Dossier de progression manquant — 404.

## Format de fichier

Chemin :

```text
core/storage/stask/{taskId}.log
```

Chaque ligne uniquement en annexe :

```text
status|progress|processed|total|eta|message
```

`readProgress()` lit la dernière ligne valide ; `readLog()` extrait le message des N dernières lignes. Un échec d’écriture ne fait pas planter intentionnellement une tâche métier, donc l’absence de progression en temps réel ne prouve pas que la tâche n’est pas terminée.

## Nettoyage

Lorsque des tâches en file d’attente/préparation/exécution manquent, `stask:worker` supprime `*.json` plus anciennes de 24 heures et le JSON temporaire plus vieux de 10 heures. Le `TaskProgress` actuel utilise en fait `*.log`, donc ces fichiers journaux ne sont pas automatiquement supprimés par cette boucle. Mettez en place une politique de rétention distincte pour `storage/stask/*.log` après avoir rapproché les exigences d’audit.

## Télécharger/télécharger

Le contrôleur dispose de chemins d’envoi normaux et en blocs, d’un point de terminaison à limite du serveur, et d’extensions autorisées spécifiques à chaque worker autorisé. Les fichiers sont stockés sous `storage/stask/uploads`.

Règles intégratrices :

- Ne pas compter uniquement sur l’extension ;
- vérifier le MIME et le format réel dans l’ouvrier ;
- taille limite et nombre de morceaux ;
- générer des noms de fichiers côté serveur ;
- ne permettent pas de traverser le chemin ;
- supprimer les fichiers temporaires/résultats par politique de conservation ;
- Ne retournez pas le chemin de téléchargement tant que le fichier n’existe pas et appartient à la tâche.

La charge utile exacte de téléchargement dépend du contrat du widget/worker ; ne considérez pas Endpoint comme une API universelle de fichiers.

## Autorisations

`sTaskController::index()` et `show()` vérifient explicitement les autorisations `stask`; La partie Méthodes d’Action repose uniquement sur `mgr`. Limiter infrastructurelement la session du gestionnaire de routes de module à la session, et dans les contrôleurs personnalisés, répéter la vérification des permissions pour les opérations destructrices.
