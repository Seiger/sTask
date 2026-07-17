# sTask 2.x

sTask est un package Evolution CMS pour gérer les tâches en arrière-plan. Il stocke la file dans la base de données, trouve les workers via Composer, les exécute avec la commande `stask:worker`, affiche la progression et les journaux dans le manager, et supervise séparément les processus supervisés de longue durée.

La documentation décrit la branche actuelle `2.x`. Elle ne suppose ni file d’attente externe, ni SSE, ni WebSocket : la progression en direct dans le manager est lue par des requêtes HTTP périodiques depuis les journaux `storage/stask/{taskId}.log`.

## À qui s’adresse cette documentation

- **Administrateur** — installation, permissions, cron, onglets du manager, diagnostic et exploitation en production.
- **Intégrateur** — planifications, enregistrement des workers, routes du manager, migrations et mises à jour.
- **Développeur PHP** — `TaskInterface`, `BaseWorker`, façade `sTask`, API de progression et contrat du supervisor.

## Carte de documentation

1. Débuts
   - [Exigences, installation et mises à jour](01-getting-started/installation.md)
   - [Démarrage rapide](01-getting-started/quick-start.md)
2. Concepts
   - [Architecture et cycle de vie](02-concepts/architecture-and-lifecycle.md)
   - [Horaires](02-concepts/schedules.md)
   - [Superviseur de processus](02-concepts/supervisor.md)
3. Responsable du CMS Evolution
   - [Panel, Tâches, Travies, Journaux et Statistiques](03-manager/interface.md)
4. Développement
   - [API Facade et PHP](04-development/public-api.md)
   - [Ouvrier propre](04-development/custom-worker.md)
   - [Itinéraires, fichiers de progression et téléchargements](04-development/routes-and-progress.md)
5. Fonctionnement
   - [Recommandations de production](05-operations/production.md)
   - [Diagnostic](05-operations/troubleshooting.md)
   - [Transition de 1.x à 2.x](05-operations/upgrade-1-to-2.md)
6. Annuaire
   - [Configuration](06-reference/configuration.md)
   - [Tables de bases de données](06-reference/database.md)
   - [CLI, statuts et routes](06-reference/cli-statuses-routes.md)
   - [FAQ](06-reference/faq.md)

## Limites de responsabilité

sTask exécute le worker de manière séquentielle dans le processus `stask:worker`. Le package ne fournit pas de garantie unique et distribuée par le courtier, la fin automatique du processus du système d’exploitation avec un bouton d’arrêt d’urgence, ni le stockage de tous les messages de progression dans la base de données. Ces exigences sont mises en œuvre par l’opérateur applicatif et l’infrastructure du projet.

## Premier contrôle

Une fois installé, effectuez :

```bash
cd core
php artisan migrate
php artisan package:discover
php artisan stask:publish
php artisan stask:worker
```

Résultat attendu : des migrations ont été créées `s_workers`, `s_tasks` et `s_supervisor_states`, les assets batch ont été publiés, et la commande `stask:worker` s’est terminée par un message indiquant le nombre de tâches créées et traitées. Ensuite, ouvrez le module **sTask** dans le gestionnaire.
