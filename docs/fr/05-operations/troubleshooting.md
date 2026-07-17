# Diagnostics

Commencez par les preuves : référence de paquet, routes, migrations, planificateur, ligne de base de données, fichier d’avancement, journal d’application. Ne concluez pas uniquement avec le badge UI.

## sTask n’est pas visible dans le module Documentation

1. Vérifier l’artefact physique :

   ```bash
   test -f core/vendor/seiger/stask/docs/uk/README.md
   ```

2. Vérifiez la serrure/référence source :

   ```bash
   cd core
   composer show seiger/stask --all
   ```

3. Assurez-vous que les dDocs ont `scan_vendor_packages = 1`.
4. Effacer le cache de l’index/application.
5. Vérifier les métadonnées des paquets dans `lang/{locale}/global.php`: `module_title`, `module_description`, `module_icon`.

dDocs scanne automatiquement les paquets `seiger/*`. N’ajoutez pas la racine du fournisseur à `extra_docs_roots`: la racine du projet configurée peut devenir écrivable dans l’Visualiseur.

Si les documents et `core/vendor` dépôt Git ne le font pas, le problème vient du verrouillage/dist/deploy de Composer, pas de l’index Markdown.

## dDocs plante à l’indexation

Vérifiez la trace de pile du paquet de documentation tiers. Le fichier alias local, le fournisseur de service ou le cache peuvent planter avant la lecture de la source sTask. C’est un dDocs/environnement de défaut séparé ; La documentation sTask ne peut pas réparer le bootstrap de quelqu’un d’autre.

## Le module sTask n’est pas visible

- `composer show seiger/stask`;
- le fournisseur est présent dans la découverte de colis ;
- permission `stask` rôle assigné ;
- les migrations ont eu lieu ;
- le cache gestionnaire est vidé ;
- l’enregistrement du module/plugin est effectué par l’installateur Evolution CMS.

Le fournisseur dispose d’une méthode d’enregistrement manager, mais l’entrée réelle du module peut également être gérée par l’installateur/plugin du paquet Evolution. Vérifiez la découverte des enregistrements et paquets des modules de base de données, plutôt que d’appeler manuellement la méthode protégée.

## Module sans styles ni JavaScript

```bash
cd core
php artisan stask:publish
php artisan cache:clear-full
```

Dans le navigateur Réseau, vérifiez `stask-module.css`, `stask-module.js`, `stask.min.css`. Si le déploiement utilise un système de fichiers de libération en lecture seule, les ressources doivent être publiées au moment de la compilation.

## `stask:worker` ne démarre pas

```bash
php -v
php artisan list | grep stask
php artisan route:list --path=stask
```

Le paquet nécessite PHP 8.4. Vérifiez que la CLI et le FPM utilisent la même version, `.env`, extensions et permissions.

## Tâche en file d’attente et non exécutée

Découvrez :

```sql
SELECT id, identifier, action, status, start_at, created_at
FROM s_tasks
WHERE status IN (10, 30, 50)
ORDER BY id;
```

- cron est effectivement exécuté ;
- `start_at` pas dans le futur ;
- workers actifs ;
- la classe existe et met en œuvre `TaskInterface`;
- le journal d’application ne contient pas d’exception de résolution ;
- Il n’y a pas de verrou externe constamment occupé.

## L’emploi du temps ne crée pas une tâche

- `settings.schedule.enabled = true`;
- Type non `manual`;
- le worker concret a `taskMake()`;
- il n’y a pas de tâche incomplète de cet identifiant ;
- une date une fois dans le futur ;
- hebdomadaire a `days`;
- Fenêtre régulière valide et non du jour au lendemain ;
- `stask:worker` passe toutes les minutes.

## L’ouvrier n’apparaît pas

```bash
composer dump-autoload
php artisan package:discover
```

Ensuite, rafraîchissez la liste de recommandations. Le cours doit être concret et figurer dans la classmap de compositeur. La classe PSR-4, que Composer n’a pas optimisée dans la carte de classes, peut ne pas être trouvée par l’implémentation actuelle de découverte du dump autoritaire/optimisé.

Vérifiez `config/excluded_namespaces.php`: les grands espaces de noms de framework sont intentionnellement omis.

## `WorkerClassNotFound` / `WorkerInvalidInterface`

- le nom de la classe est `s_workers.class` correct ;
- le chargement automatique est à jour ;
- classe non abstraite ;
- Outils de classe `TaskInterface`;
- le constructeur ne plante pas en raison de la dépendance entre la base de données et les paramètres.

Ne cliquez pas sur « Clean orphan » lors du déploiement partiel : l’enregistrement peut être supprimé tant que la classe est temporairement indisponible.

## Progression en direct 404

404 signifie `storage/stask/{id}.log` non trouvé. La tâche peut encore être en file d’attente ou l’écriture a pu échouer discrètement.

```bash
ls -la core/storage/stask
```

Comparez utilisateur/groupe pour FPM et cron. Voir le statut de la base de données/message et le journal de l’application.

## Progrès figé, tâche terminée

Fichier de progression uniquement ajouté et n’est pas une source de vérité pour le statut final de la base de données. Le veilleur en direct s’arrête à la ligne terminale sur la dernière ligne. Si le worker personnalisé a finalisé la base de données mais n’a pas appelé `markFinished()`/n’a pas enregistré l’instantané final, l’interface utilisateur se mettra à jour après le rafraîchissement Livewire, mais le fichier peut rester en cours.

## L’arrêt d’urgence n’a pas arrêté le processus

C’est la limite attendue : l’action ne met l’enregistrement de la base de données qu’en échec. Trouve le processus en utilisant la télémétrie d’infrastructure, arrête-le normalement, vérifie les effets secondaires, puis lance une nouvelle tâche.

## Boucle de redémarrage superviseur

- Startup Grace est trop petite ;
- l’inspection ne reconnaît pas le démarrage ;
- le temps d’attente du battement cardiaque est plus court que la fréquence réelle ;
- changements d’empreintes digitales à chaque passage via horodatage ou texte aléatoire ;
- le processus redémarrera également systemd ;
- démarrage/redémarrage n’est pas détaché.

L’empreinte digitale doit être stable pour obtenir le même diagnostic.

## L’état du superviseur grandit

```sql
SELECT worker_id, identifier, supervisor_key, COUNT(*)
FROM s_supervisor_states
GROUP BY worker_id, identifier, supervisor_key;
```

Un `key_hash` unique empêche la croissance pour la même paire, mais un nouvel identifiant de worker ou une clé variable crée une nouvelle ligne. Réparez la stabilité des clés pour nettoyer les choses.

## Les statistiques montrent une durée ou mémoire nulle

L’implémentation actuelle de l’agrégation restitue un indicatif de place zéro pour ces indicateurs. Cela ne signifie pas une consommation nulle. Utilisez la durée des tâches dans les journaux et les APM externes.

## ArtisanWorker bloque la commande

Vérifiez `config/artisan_security.php`:

- les ordres dangereux sont interdits ;
- la confirmation requise nécessite `confirm=true`;
- la liste blanche, si elle n’est pas vide, n’autorise que les motifs listés ;
- la liste noire bloque des commandes supplémentaires ;
- Permission `run_artisan` est requise.

Ne désactivez pas les contrôles de sécurité en production.
