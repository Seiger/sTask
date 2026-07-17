# Démarrage rapide

Ce script s’exécute d’une installation propre à la première tâche terminée sans aucune API fictive.

## 1. Vérifiez le colis

```bash
cd core
php artisan route:list --path=stask
php artisan stask:worker
```

La deuxième équipe peut sortir `created 0 scheduled task(s), processed 0 task(s)` – c’est un résultat normal pour une file d’attente vide.

## 2. Mettre à jour le registre des workers

Ouvrez **sTask → Workers** et cliquez sur le bouton avec l’icône `database-cog` (**Mettre à jour le registre des workers**). La découverte lit `vendor/composer/autoload_classmap.php`, rejette les espaces de noms exclus, et enregistre des classes concrètes qui implémentent `TaskInterface`.

Un nouvel ouvrier est rendu inactif. Allumez-le avec le bouton d’alimentation ou dans la fenêtre d’édition modale.

## 3. Exécuter manuellement

Pour un worker actif utilisant la méthode `taskMake()` , appuyez sur `player-play`. sTask :

1. créer `s_tasks` avec statut `10`;
2. écrire la première ligne de `storage/stask/{id}.log`;
3. essaiera de faire tourner `php core/artisan stask:worker` en arrière-plan ;
4. affichera l’avancement en temps réel dans la ligne du tableau via un sondage HTTP adaptatif.

Si `exec`/`shell_exec` sont désactivés, exécutez la commande manuellement :

```bash
php artisan stask:worker
```

## 4. Vérifiez le résultat

Dans l’onglet **Tâches**, trouvez l’entrée par ID, nom de l’employé ou action. Séquence d’état attendue :

```text
10 queued → 50 running → 80 finished
```

Le statut `30 preparing` défini par le modèle et peut être utilisé par le code applicatif, mais la `TaskWorker` standard passe de la file d’attente directe à l’exécution.

Double-cliquer sur une ligne ouvre un modal en lecture seule avec message, méta et résultat. Un lien d’identification séparé se trouve dans l’onglet **Logs** et mène à la page complète des détails de la tâche.

## 5. Créez une tâche à partir de PHP

La façade restitue un double actif existant ou un nouveau modèle :

```php
<?php

use Seiger\sTask\Facades\sTask;

$task = sTask::create(
    identifier: 'inventory_sync',
    action: 'make',
    data: ['warehouse' => 12, 'force' => false],
    priority: 'normal',
    userId: evo()->getLoginUserID() ?: null,
);

echo $task->id;
```

Important : `create()` ne fait que mettre la candidature en file. L’exécution nécessite `stask:worker` ou appeler `sTask::execute($task)` dans un processus contrôlé.

## 6. Configurez le démarrage automatique

Dans le mode worker modal, activez Démarrage automatique et sélectionnez le planning. Par exemple, chaque heure à la 15e minute :

```json
{
  "schedule": {
    "enabled": true,
    "type": "periodic",
    "frequency": "hourly",
    "time": "*:15",
    "datetime": "",
    "start_time": "",
    "end_time": ""
  }
}
```

Les `stask:worker` suivantes créeront une tâche future en file d’attente avec `start_at`. Jusqu’à ce que le moment soit venu, la tâche est visible dans **Tâche**, mais n’est pas exécutée.

## Liste de contrôle

- la référence source du paquet correspond à la branche attendue 2.x ;
- les migrations réussissent ;
- `stask` autorisation attribuée au rôle de manager souhaité ;
- `storage/stask` un utilisateur web et un utilisateur CLI sont disponibles pour écrire ;
- cron exécute un planificateur toutes les minutes ;
- le worker est actif, la classe existe, l’identifiant est unique ;
- la tâche passe en statut final et a `finished_at`.
