# Exigences, installation et mises à jour

## Exigences

La branche 2.x actuelle `composer.json` exige :

| Composant | Exigence |
| --- |----------------------------|
| PHP | <code>&#94;8.4</code> |
| CMS d’évolution | <code>&#94;3.5.7</code> |
| evo-ui | <code>&#94;1.0.6</code> |
| Composer | Disponible dans le catalogue `core` |

L’entité HTML pour caret est utilisée intentionnellement : dDocs ne la convertit pas en exposant et affiche la contrainte exacte du Composer.

Le traitement automatique des files d’attente nécessite un cron système ou un autre planificateur qui exécute Laravel à chaque minute. Pour exécuter la tâche immédiatement depuis l’interface, le processus PHP doit également avoir accès à `exec()` ou `shell_exec()`; Si elles ne sont pas autorisées, l’entrée dans la file d’attente sera quand même créée et traitée par le prochain pass Cron.

## Installation via le compositeur

Les commandes sont exécutées depuis le répertoire CMS `core` Evolution :

```bash
cd /var/www/example/core
composer require seiger/stask:"2.x-dev"
php artisan migrate
php artisan package:discover
php artisan stask:publish
php artisan cache:clear-full
```

Ce qui doit se passer :

1. La découverte de paquets Laravel relie `Seiger\sTask\sTaskServiceProvider` et alias `sTask`.
2. `migrate` déclenche des migrations batch que le fournisseur a ajoutées via `loadMigrationsFrom()`.
3. `package:discover` met à jour le manifeste de découverte de paquets Laravel ; La carte de classes Composer est construite lors de l’installation/mise à jour ou `composer dump-autoload`.
4. `stask:publish` copie CSS, JavaScript et SVG vers `assets/site`.
5. Un nettoyage complet du cache supprime les anciennes vues du gestionnaire, les routes et les métadonnées des paquets.

Ne modifiez pas les fichiers dans `core/vendor/seiger/stask`: Le compositeur les remplacera lors de la mise à jour.

## Fournisseur de services

Fournisseur automatique :

- registres singleton `Seiger\sTask\sTask` et alias `sTask`;
- registres `WorkerService`, `MetricsService`, `SupervisorService`;
- charge les migrations, traductions, vues Blade, routes de gestion et composants Livewire ;
- relie les préréglages de table `stask.tasks`, `stask.workers`, `stask.logs`;
- crée `storage/stask` s’il n’y a pas encore de répertoire ;
- les registres `stask:worker` et `stask:publish` dans la CLI ;
- ajoute `stask:worker` au planificateur de Laravel avec une fréquence d’une fois par minute.

Le menu Manager ajoute un plugin batch `plugins/sTaskPlugin.php` à l’événement `evolution.OnManagerMenuPrerender` uniquement lorsque l’utilisateur a la permission de `stask`. Elle mène sur une route nommée `sTask.index` et utilise `sTaskServiceProvider::MODULE_ICON`. Le fichier `module/sTaskModule.php` est un wrapper protégé pour l’entrée du module Evolution et affiche le même contrôleur. La méthode protégée `registerManagerModule()` présente dans le fournisseur pour le flux installateur/module, mais ne l’appelle pas directement `boot()` ; Ne comptez pas sur l’appel manuel de cette méthode.

## Migrations et permissions

Le package crée trois tableaux : `s_workers`, `s_tasks`, `s_supervisor_states`. Une migration d’idempotents distincte crée un groupe de permissions `sTask`, clé de permission `stask` et ajoute son rôle `1` si les tables système correspondantes existent.

À vérifier :

```bash
php artisan migrate:status
php artisan route:list --path=stask
```

Dans le gestionnaire, l’utilisateur a besoin d’une autorisation `stask`. Les routes Manager sont également protégées par le groupe middleware `mgr`; ce n’est pas une API HTTP publique.

## Poster les actifs

Équipe :

```bash
php artisan stask:publish
```

publie notamment :

- `assets/site/stask.svg`;
- `assets/site/stask.min.css`;
- `assets/site/stask-module.css`;
- `assets/site/stask-module.js`;
- `assets/site/stask.js`;
- `assets/site/seigerit.tooltip.js`.

Si le module s’ouvre sans styles ou si la progression en temps réel ne se met pas à jour, commencez par répéter la publication et `cache:clear-full`, puis vérifier HTTP 200 pour ces ressources.

## Cron

Enregistrement de production recommandé :

```cron
* * * * * cd /var/www/example/core && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

Vérifiez les chemins absolus :

```bash
command -v php
cd /var/www/example/core && php artisan schedule:list
cd /var/www/example/core && php artisan stask:worker
```

Ne lancez pas plusieurs entrées cron non contrôlées pour la même installation. Les tâches classiques n’ont pas de verrouillage global de réclamation, donc `stask:worker` parallèles peuvent créer un risque d’exécution concurrentielle.

## Mise à jour 2.x

```bash
cd /var/www/example/core
composer update seiger/stask --with-dependencies
php artisan migrate
php artisan package:discover
php artisan stask:publish
php artisan cache:clear-full
```

Après la mise à jour, vérifiez la référence réelle de la source :

```bash
composer show seiger/stask --all
```

Si la documentation batch n’apparaît pas dans dDocs, vérifiez non seulement `docs` dans le dépôt Git, mais aussi le répertoire physique `core/vendor/seiger/stask/docs/uk`. Le verrouillage/dist du compositeur peut rester sur l’ancien commit.

## Retour en arrière

Avant de faire une mise à jour, faites une copie de sauvegarde de la base de données et `core/composer.lock`. Revenez en arrière le code avec Composer vers une référence vérifiée. Annulez le schéma de la base de données uniquement selon un plan séparé après avoir vérifié l’ensemble réel des migrations dans la version installée du package et les conséquences pour les données de production.
