# Interface gestionnaire

Le module dispose de cinq onglets Livewire : **Panneau**, **Tâches**, **Workers**, **Journaux**, **Statistiques**. La commutation se produit sans un redémarrage complet du cadre gestionnaire. L’onglet initial peut être passé par le paramètre de requête `get`; La valeur inconnue est remplacée par `dashboard`.

## Accès

Le shell du module et les détails de la tâche peuvent être ouverts par un utilisateur gestionnaire avec la permission `stask`. Toutes les routes HTTP se trouvent dans le groupe middleware `mgr`. Certains points d’accès d’action ne reposent que sur `mgr` middleware et n’appellent pas `hasPermission('stask')` à répétition, donc ne publiez pas `/stask/*` en dehors de l’authentification du manager.

## Règles communes des tables EvoUI

Les tableaux **Tâches**, **Workers** et **Journaux** prennent en charge :

- recherche ;
- par page : 15, 30, 50, 100, 200 (par défaut 30) ;
- vue tableau/liste ;
- trier uniquement par colonnes marquées `sortable` dans un préréglage ;
- filtres multi-sélection et plage de dates ;
- actions en ligne ;
- double-clic dans toutes les vues par lignes : dans le Panneau, dans Tâches et Journaux, cela ouvre les détails des tâches, et dans Workers — édition modale.

La recherche et les filtres sont appliqués sur le serveur. La vue de liste modifie la vue, pas le jeu de données.

## Panneau

![Tableau de bord sTask avec indicateurs clés](../../assets/screenshots/stask-dashboard.png)

### Cartes

Le panneau montre les comptages en file d’attente, en cours, terminés, échoués, ouvriers et actifs. Cela est suivi par des tâches récentes et, si disponible, un tableau séparé des erreurs récentes.

### Tâches récentes

Colonnes : ID, Worker, Action, Statut, Progression, Début d’exécution, Actions. Pour la tâche active, la ligne reçoit l’URL de progression, et `stask-module.js` lit périodiquement l’instantané sans historique de journal (`include_log=0`).

Un double-clic ou l’icône `eye` ouvre un modal avec les champs principaux, le journal des tâches, le méta et le résultat. Contenu en lecture seule.

### Progrès en direct

La barre de progression/les valeurs et le message sont mis à jour via un sondage HTTP. Marquedown en ligne sécurisé supporté : backticks, gras, rayage, emphase. Le HTML s’échappe d’abord. Au dernier statut, Livewire rafraîchit le panneau une fois.

## Tâches

![Tableau des tâches sTask](../../assets/screenshots/stask-tasks.png)

### Recherche

Recherches par identifiant numérique, `identifier`, `action`, `message`.

### Filtres

- **Worker** est une liste consultable avec des `worker->title` humaines plutôt que des identifiants bruts.
- **Action** — actions distinctes de la base de données.
- **Statut** — en file d’attente, en préparation, en cours, complété, échoué.
- **Utilisateur** — utilisateurs gestionnaires qui ont déjà exécuté des tâches, plus `system` pour `started_by IS NULL OR <= 0`.
- **Plage de création** — frontières inclusives du début à la fin de la journée.

Priorité et Tentatives ne sont pas des colonnes ou filtres pertinents de cet onglet. Ils restent des champs d’exécution/schéma pour la compatibilité, mais ne sont pas documentés comme contrôle d’interface utilisateur.

### Colonnes

| Colonne | Signification |
| --- | --- |
| ID | `#id`; Nouveautés premières pour Default |
| Ouvrier | Solution de secours localisée/humaine pour le titre ou l’identifiant |
| Action | Code d’action |
| Statut | Insigne de statut numérique |
| Progrès | `0–100%`; La ligne active peut être mise à jour en direct |
| En courant | Nom d’utilisateur ou `system` |
| Messages | message persisté avec rendu Markdown sûr |
| Début de l’exécution | `start_at`; Pour la tâche en file d’attente future, c’est le temps prévu |
| Terminé | `finished_at` |

### Actions

- `eye` — détails modaux.
- `player-eject` — arrêt d’urgence pour la file d’attente/la préparation/la course.

L’arrêt d’urgence affiche le statut échoué, `finished_at = now()` et le message « La tâche est arrêtée par plantage ». Il **ne met pas fin au processus PHP/OS**. Si le processus continue de fonctionner, il peut toujours modifier les données ou le fichier de progression.

Un double-clic sur la ligne ouvre les détails modals.

## Ouvriers

![Registre des workers avec horaires et temps de disponibilité des superviseurs](../../assets/screenshots/stask-workers.png)

### Recherche & Filtres

Recherche : identifiant, portée, classe. Filtres :

- actif/inactif ;
- cours disponibles/manquants ;
- visible/caché.

### Colonnes

- Identifiant.
- Ouvrier — titre avec instance de classe.
- Description — extrait jusqu’à 96 caractères.
- Calendrier — puce ; Pour le badge de superviseur en bonne santé, il apparaît le temps de disponibilité après `niceEta()`.
- Nombre de tâches — `niceCount()` (nombre localisé compact).
- Dernière action.
- Dernière exécution — l’horodatage de la dernière trace de tâche.

Caché est le drapeau de visibilité du manager ; L’enregistrement du worker n’est pas supprimé. Le worker inactif ne peut pas être lancé et le planificateur le saute.

### Barre d’outils et actions sur les lignes

- `database-cog` — découvrir + rescanner + nettoyer l’orphelin + effacer le cache des ouvriers.
- `player-play` — ne démarre que le worker de la ligne sélectionnée uniquement si la classe est active, la classe existe et est `taskMake()`.
- `edit` — réglages modaux.
- `power` — activement à bascule.
- `eye/eye-off` — bascule de la visibilité.

Le registre de rafraîchissement peut supprimer les enregistrements dont la classe n’existe plus. Avant de passer en production, vérifiez que le chargement automatique du compositeur est terminé et que le déploiement n’est pas dans un état intermédiaire.

### Worker modal

Lecture seule : titre, identifiant, portée, classe, description. Modifiable : actif, caché, position, planning, paramètres JSON supplémentaires.

Le JSON supplémentaire ne doit pas contenir de clé `schedule`: lors de l’enregistrement, il est extrait et remplacé par des valeurs de formulaire. Le JSON invalide n’est pas stocké ; Le fournisseur laisse les paramètres personnalisés précédents.

Pour la classe capable de superviser, le modal montre également :

- clé ;
- Insigne d’État ;
- PID ;
- battement de cœur ;
- temps de travail (`niceEta`) ;
- le dernier diagnostic ;
- Dernière transition.

L’option superviseur est cachée si la classe n’implémente pas `SupervisorWorkerInterface`.

## Journaux

![Journal de progression des tâches](../../assets/screenshots/stask-logs.png)

Ce n’est pas une table de journal distincte : l’onglet lit `s_tasks` et affiche l’historique des tâches plus en détail.

### Recherche & Filtres

Recherche : ID, identifiant, action, message. Filtres : titre de travaileur, action, statut, utilisateur y compris `system`, plage de dates créée.

### Colonnes

ID-lien, titre de worker, identifiant, action, statut, progression, commencé par, créé, débuté, terminé, mis à jour, **Temps de travail**.

**Runtime** utilise la durée de la tâche : pour la tâche finale `finished_at - start_at`, pour la tâche active `now - start_at`. La mise en forme est assurée par `niceEta()`. La clé de traduction du nom est partagée avec le superviseur, mais ici il s’agit de la durée de la tâche.

ID ouvre une page de détails distincte. Un double-clic ouvre un modal en lecture seule avec message, méta, résultat et classe du worker.

## Statistiques

![Statistiques de tâches des dernières 24 heures](../../assets/screenshots/stask-statistics.png)

Affiche les cartes de performance des dernières 24 heures, les alertes et les statistiques de la cache des employés.

Réel sur la mise en œuvre actuelle :

- le nombre de tâches ;
- taux de réussite/erreur avec les enregistrements de statut ;
- regroupement des workers ;
- collisions, échecs, expulsions, taux de réussite, taille du cache ;
- Nettoyage de la cache des workers.

Limitations : `MetricsService` jusqu’à présent retourne `0` pour la durée moyenne, la mémoire moyenne et le temps d’exécution total lorsqu’ils sont agrégés à partir des enregistrements de tâches ; Common Errors est aussi un espace réservé vide. N’utilisez pas ces valeurs comme SLI de production sans télémétrie externe.

`niceSize()` est utilisé pour les valeurs de mémoire lisibles par l’humain lorsqu’il existe une valeur d’octet réel ; `niceCount()` — pour les compteurs ; `niceEta()` pour des secondes/durée.

## Sécurité des données

Le méta, le résultat et le journal de progression sont visibles pour les utilisateurs gestionnaires ayant accès au module. Ne partagez pas de mots de passe, de jetons API, de chaînes de session ou de données personnelles sauf si vous devez les montrer à l’opérateur. Les points de terminaison de téléchargement/téléchargement bénéficient d’une validation spécifique à chaque employé, mais l’employé doit toujours vérifier le type, la taille et le contenu du fichier.
