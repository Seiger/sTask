# Recommandations de production

## Modèle de processus

`stask:worker` — commande de fuite et de sortie. Il crée des tâches planifiées, traite séquentiellement des lignes prêtes à l’emploi en file d’attente, et en sort. Pour une maintenance continue, faites fonctionner le planificateur Laravel à chaque minute.

```cron
* * * * * cd /var/www/example/core && /usr/bin/flock -n /run/lock/example-stask.lock /usr/bin/php artisan schedule:run >> /var/log/example-scheduler.log 2>&1
```

`flock` — Protection des infrastructures contre le chevauchement. Sélectionnez le chemin de verrouillage scriptable et vérifiez qu’une tâche longue ne bloque pas les tâches critiques de planificateur non liées. Une alternative est une unité/minuteur séparé avec `RefuseManualStart` politique /verrouillage.

## Propriétaire et droits

L’utilisateur Web et l’utilisateur CLI doivent avoir des droits compatibles sur :

- `core/storage/stask`;
- Cache/stockage Laravel ;
- annuaire de téléchargement des résultats ;
- ressources applicatives que le worker modifie.

Ne lancez pas cron à partir de la racine inutilement : les fichiers de progression/cache créés par la racine cassent souvent le processus gestionnaire.

## Temps mort

Le chemin run du manager peut appeler `set_time_limit(0)`, et la commande CLI ne définit pas de délai d’attente par tâche. Les temps morts doivent être dans le worker :

- Délai d’attente pour connexion/lecture HTTP ;
- Délai d’expiration de l’état de base de données ;
- nombre maximal d’articles/lotés par tâche ;
- date limite dans les métadonnées ;
- Des points de contrôle d’annulation élégants.

Décomposez les longs travaux en morceaux idempotents. Une tâche monolithique bloque le prochain calendrier avec le même identifiant de worker.

## Compétitivité

La ligne de ligne actuelle sélectionne les lignes en file d’attente sans requête atomique ou `FOR UPDATE SKIP LOCKED`. Donc :

- garder un `stask:worker` par installation ;
- Utiliser un verrou de chevauchement externe ;
- rendre l’action idempotente ;
- pour les intégrations critiques, utiliser la clé d’idempotence au niveau du domaine ;
- Ne confondez pas la recherche dupliquée avec la garantie transactionnelle.

Si la concurrence est requise, il faut d’abord concevoir un contrat de créance/bail ; Augmenter simplement le nombre de processus est dangereux.

## Réessayer et reculer

`attempts/max_attempts` ne retentent pas seuls. La politique de production devrait déterminer :

- classes/codes d’état réessayables ;
- le nombre maximal de tentatives ;
- `start_at` pour le retrait ;
- revue par lettre morte/manuel ;
- comportement de doublons/idempotence ;
- Alerter après la dernière erreur.

## Surveillance

Contrôles minimums :

- Scheduler Heartbeat/dernier lancement réussi ;
- le nombre de tâches en file d’attente et l’âge des plus anciennes ;
- l’ancienneté de la tâche ;
- taux d’échec ;
- la facilité d’écriture `storage/stask`;
- progression/résultats d’utilisation du disque ;
- workers disparus ou inactifs de classe ;
- `s_supervisor_states.last_seen_at` et fraîcheur du battement de cœur ;
- identité PID du démon.

L’onglet Statistiques intégré ne remplace pas APM : durée/mémoire/erreurs courantes par des espaces réservés partiels.

## Journaux

Il existe trois sources différentes :

1. `s_tasks.message/meta/result` — audit/état persistant.
2. `storage/stask/{id}.log` — progression en direct uniquement en appendice.
3. journaux d’application via Laravel `Log` — exceptions, découverte, avertissements de lancement.

Définissez la rétention individuellement. `cleanOldTasks()` ne supprime que les anciennes lignes de base de données terminées ; Les `.log` de progression, les tâches ratées et l’état du superviseur ne sont pas validés.

## Rétention

Un exemple de politique à mettre en œuvre dans votre couche opérationnelle :

- tâches terminées : 30 à 90 jours ;
- tâches échouées : plus longtemps ou avant l’examen de l’incident ;
- journaux de progression : 7 à 30 jours après la dernière tâche ;
- Téléchargement/résultats : pour la politique commerciale/juridique ;
- état superviseur : une ligne réelle par clé ; Rangées d’orphelins — Après vérification d’inventaire.

Ne supprimez pas la progression active de la tâche. Vérifiez le statut final et la propriété du dossier avant de nettoyer.

## Superviseur/systématique

sTask Supervisor est un adaptateur de cycle de vie au niveau de l’application, et non un remplaçant complet de systemd/Supervisor. Si le processus redémarre l’adaptateur systemd et sTask en même temps, convenez-vous d’un propriétaire unique, sinon des boucles de redémarrage sont possibles.

Rôles recommandés :

- SystemD fournit les limites de démarrage, d’utilisateur, de ressources et de redémarrage en cas de plantage ;
- L’adaptateur de travail lit Santé/Battement cardiaque et renvoie un état diagnostique ;
- un seul d’entre eux effectue un redémarrage, ou bien les deux ont un contrat commun de retrait/verrouillage.

## Déployez

Séquence sûre :

1. arrêter la création de nouveaux emplois ou attendre l’achèvement de ceux critiques ;
2. `composer install` avec serrure ;
3. `php artisan migrate --force` après la sauvegarde et la revue de migration ;
4. `package:discover`/reconstruction automatique ;
5. `stask:publish`;
6. `cache:clear-full`;
7. actualiser le registre des workers après un déploiement global ;
8. fumée `stask:worker`;
9. Vérifiez l’interface utilisateur du gestionnaire et le planificateur.

Ne faites pas le nettoyage du registre en plein déploiement lorsque les classes sont temporairement absentes.

## Sécurité

- autorisation `stask` uniquement des rôles opérationnels ;
- `run_artisan` séparément pour Artisan ;
- liste blanche/liste noire des commandements dangereux ;
- CSRF pour POST ;
- Secrets absents du méta/résultat/message/progression ;
- les téléchargements dans un stockage non public ;
- les workers valident l’entrée et l’autorisation, même si c’est uniquement le gestionnaire de route ;
- compte de service avec des privilèges minimaux de système de fichiers/base de données.
