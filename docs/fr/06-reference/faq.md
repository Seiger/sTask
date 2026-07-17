# FAQ

## sTask est la file Laravel ?

Non. sTask dispose de ses propres tables Eloquent, contrat de worker et commande d’exécution ponctuelle. Il n’utilise pas la connexion queue Laravel comme moteur d’exécution principal.

## Ai-je besoin de Rendis ?

Pas pour le travail de base. Le cache/métriques de worker utilise le cache Laravel, et le verrouillage superviseur prend explicitement `file` stock. Le cache d’application configuré devrait encore fonctionner correctement.

## Y a-t-il un SSE ou WebSocket ?

Non. Live Progress est un point de terminaison adaptatif HTTP `/stask/task/{id}/progress`.

## Le bouton start fait-il la tâche en arrière-plan ?

Le contrôleur essaie de fermer la réponse FastCGI et de lancer l’opérateur CLI ; Le plan de secours peut être synchrone ou ne pas se déclencher en raison de fonctions désactivées. Cron/scheduler est une voie de production fiable.

## L’urgence arrête-t-elle le traitement PHP ?

Non. Cela ne traduit que l’enregistrement de la base de données en échec. Le processus OS doit être arrêté séparément.

## Y a-t-il des essais automatiques ?

Non. Les tentatives/maximales sont sauvegardées, mais les lignes ratées ne sont pas automatiquement remises en file d’attente par la commande par défaut.

## Que signifie `system` dans le filtre utilisateur ?

Tâches avec `started_by` null ou `<= 0`, y compris les événements du planificateur/superviseur.

## Pourquoi le filtre ouvrier affiche-t-il le nom et non l’identifiant ?

Le fournisseur résout `worker->title` et utilise l’identifiant uniquement comme solution de secours. La requête filtre les tâches par identifiants associés à certains identifiants de worker.

## Pourquoi une tâche avec la même charge utile n’a-t-elle pas été créée une seconde fois ?

La suppression active de doublons compare l’identifiant, l’action et la méta normalisée pour les états en file d’attente/préparation/exécution et renvoie le modèle existant.

## La suppression des doublons est-elle sûre pour les courses ?

Pas complètement. Il n’existe pas de clé unique pour le hachage actif de la charge utile dans le schéma. Les processus parallèles peuvent passer par une recherche simultanément.

## Où le progrès est-il stocké ?

`core/storage/stask/{taskId}.log`. Le `progress` de base de données est mis à jour séparément et n’affiche pas automatiquement chaque instantané de fichier.

## Pourquoi il n’y a pas de progrès mais la tâche fonctionne ?

Les échecs d’écriture de fichiers sont délibérément ignorés afin de ne pas pirater la tâche métier. Vérifiez les autorisations et les journaux de demande.

## Comment effacer mon historique ?

`sTask::cleanOldTasks($days)` ne supprime que les anciennes tâches de base de données terminées. Les tâches ratées, les `.log`, les téléchargements/résultats et l’état du superviseur nécessitent une politique distincte.

## Comment faire fonctionner plusieurs workers en parallèle ?

Le CLI actuel ne possède pas de revendication atomique multi-processus. Ne pas redimensionner les processus horizontalement sans un nouveau plan de réclamation/bail et une idempotence.

## Pourquoi le planning régulier n’a-t-il pas créé une tâche le lendemain ?

L’algorithme recherche le candidat uniquement dans la fenêtre diurne actuelle et retourne le nul après la fin. C’est une limitation connue de l’implémentation actuelle.

## En quoi un planning de superviseur diffère-t-il d’un contrôle de santé périodique ?

Le superviseur met à jour une ligne d’état en direct chaque minute et crée une tâche uniquement pour un événement significatif du cycle de vie. Le calendrier périodique prend en charge les `taskMake` régulières en file d’attente.

## L’État superviseur est-il retiré de l’ouvrier ?

Non, la relation automatique : n’a pas de clé étrangère/cascade de base de données. Les rangées d’orphelins nettoient après l’inventaire.
