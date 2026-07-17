# Architecture et cycle de vie

## Composants

| Composant | Responsabilité |
| --- | --- |
| `sWorker` | enregistrement de journal des ouvriers, classe, actif/caché, position, paramètres JSON |
| `TaskInterface` | métadonnées minimales/worker contractuel UI |
| `BaseWorker` | Configuration, planifications, création de tâches, répartition des actions, progression et finalisation |
| `sTaskModel` | tâche, statut, méta/résultat, horodatages et portées persistantes |
| `TaskWorker` | Création de tâches planifiées et exécution séquentielle d’une file d’attente prête à l’emploi |
| `TaskProgress` | En `storage/stask/{id}.log` uniquement ajouter en direct |
| `WorkerDiscovery` | recherchez des classes `TaskInterface` concrètes dans la carte des classes Composer |
| `WorkerService` | Résolution, validation et instances de l’ouvrier de cache |
| `SupervisorService` | Inspection/démarrage/redémarrage sérialisé des processus à long terme |
| EvoUI + Livewire | Tables de gestion, filtres, fenêtres modales et Progression des sondages HTTP |

## Conditions

- **Worker** est une classe PHP qui sait comment effectuer une ou plusieurs actions.
- **Tâche** — tentative persistante d’effectuer une action d’un identifiant de worker spécifique.
- **Action** est une chaîne comme `make` ou `sync_stock`; `BaseWorker` le transforme en `taskMake()` ou `taskSyncStock()`.
- **Schedule** est le JSON dans le `s_workers.settings.schedule` qui `stask:worker` prend en charge la tâche suivante en file d’attente.
- **Progress** — instantané/historique volatile dans le fichier `.log`; Le champ `s_tasks.progress` n’est pas automatiquement synchronisé par chaque `pushProgress()`.
- **Métadonnées (`meta`)** — paramètres d’entrée normalisés de la tâche, modèle de casting `array`.
- **Résultat** — la charge utile finale ou le chemin enregistré par le worker ; Type de base de données `LONGTEXT`, conversion du modèle en `array`.
- **Message** est un état/erreur persisté court dans `s_tasks.message`; L’historique en direct est stocké séparément.

## Création

Il existe deux principales façons :

1. `sTask::create($identifier, $action, $data, $priority, $userId)`.
2. `$worker->createTask($action, $options)` dans `BaseWorker`.

Les deux normalisent les meta en triant récursivement les clés associatives et recherchent le duplicata actif par `identifier + action + normalized meta`. Statut actif `10`, `30`, `50`.

Cela ne protège que contre des dossiers actifs identiques. Ce n’est pas un verrou distribué mondial et ne remplace pas l’exploitation commerciale.

## Exécution

`stask:worker` suit ces étapes :

1. Lit tous les workers actifs ;
2. pour les planifications activés, crée des tâches de suivi manquantes ou exécute le passage superviseur ;
3. Sélectionne toutes les tâches en file d’attente où `start_at IS NULL OR start_at <= now()`;
4. Pour chaque tâche, trouve l’enregistrement et la classe du worker actif ;
5. vérifications `TaskInterface`;
6. met le statut `running`, `start_at = now()`, augmente `attempts`;
7. causes `invokeAction()`;
8. Si l’ouvrier n’a pas finalisé la tâche, elle `finished` définit automatiquement ;
9. À une exception près, il établit `failed` et enregistre la progression des erreurs ;
10. Lorsqu’il n’y a pas de tâches actives, supprime les fichiers de progression âgés de plus de 24 heures.

La commande actuelle charge toutes les lignes prêtes à l’emploi sans limite de lot et traite séquentiellement. Planifiez le volume pour qu’un passage de cron ne soit pas gelé indéfiniment.

## Statut

| Code | Constante | Texte | Signification |
| ---: | --- | --- | --- |
| 10 | `TASK_STATUS_QUEUED` | `pending` | Temps d’attente/Laissez-passer ouvrier |
| 30 | `TASK_STATUS_PREPARING` | `preparing` | État intermédiaire pour le code d’application |
| 50 | `TASK_STATUS_RUNNING` | `running` | L’action est en cours ou considérée comme active |
| 80 | `TASK_STATUS_FINISHED` | `completed` | Réussite de l’État final |
| 100 | `TASK_STATUS_FAILED` | `failed` | Erreur ou arrêt d’urgence |

Il n’existe pas de statut d’annulation persistante séparé. `isFinished()` revient `true` uniquement pour `80` et `100`.

## Horodatages et durée

- `created_at` — lorsqu’un enregistrement a été créé.
- `start_at` est le temps prévu avant l’exécution, et après `markAsRunning()` est le début réel.
- `finished_at` — finalisation.
- `updated_at` est le dernier changement enregistré.
- accésiseur `duration` — secondes de `start_at` à `finished_at`; pour une tâche en cours, de `start_at` à l’heure actuelle.

En raison de la valeur de double `start_at` , la tâche en file d’attente à venir affiche le début programmé, et la tâche terminée affiche le démarrage réel au démarrage.

## Réessayer

`markAsRunning()` augmente `attempts`. `canRetry()` vrai pour une tâche échouée, tant que `attempts < max_attempts`. Cependant, le `TaskWorker` standard ne renvoie pas automatiquement une tâche échouée en file d’attente et ne dispose pas de commande de réessai séparée. La politique de réessai doit être mise en œuvre par un intégrateur ou un worker de reprise. Ne promettez pas aux utilisateurs des nouvelles tentatives automatiques juste après `max_attempts = 3`.

## Progression et interface en direct

`pushProgress()` ajoute une seule corde séparée par tuyau au `.log`. Observateur JavaScript :

- commence à intervalles de 1,2 seconde ;
- augmente le délai à 25 secondes si l’instantané ne change pas ;
- vérifie une fois toutes les 5 secondes dans l’onglet caché/invisible ;
- ne fait pas de requêtes parallèles ;
- s’arrête à `finished`, `failed`, `completed` ou après cinq pannes réseau ;
- après l’état final, demande à Livewire de rafraîchir la surface.

C’est un polling HTTP, pas un transport push.
