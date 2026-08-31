# Migration de sTask 1.x vers 2.x

Il s’agit d’une liste de contrôle fondée sur des preuves, pas d’un automatisme de mise à jour. Le dépôt ne dispose pas de contrat de migration lisible entièrement par la machine pour tous les workers tiers 1.x, donc vérifiez chaque classe personnalisée.

## Qu’est-ce qui a changé la surface 2.x

- Module EvoUI/Livewire avec cinq onglets.
- Vues tableau/liste, filtres serveur et modals de détail en lecture seule.
- Sondage HTTP adaptatif en temps réel.
- Suppression des doublons pour identifiant actif/action/méta normalisé.
- Rafraîchissement du registre des workers et filtres de classe/titre.
- Types d’emploi du temps manuel/une fois/périodique/régulier/superviseur.
- Séparer `SupervisorWorkerInterface`, `SupervisorStatus`, `s_supervisor_states`.
- Arrêt d’urgence lors d’une transition ratée au niveau de la base de données.
- Compact UI : Les priorités/tentatives ont été supprimées des colonnes/filtres actuels.

## Avant la mise à jour

1. Créer une base de données de sauvegarde, `core/composer.lock`, des workers personnalisés et `storage/stask` auditer selon les besoins.
2. Corriger les tâches actives ; Laissez-les finir.
3. Workers de l’inventaire :

   ```sql
   SELECT id, identifier, class, active, settings FROM s_workers ORDER BY id;
   ```

4. Trouver des classes personnalisées qui implémentent l’ancien contrat.
5. Vérifiez PHP 8.4 et evo-ui 1.2+.

## Worker d’adaptation

Formulaire recommandé :

```php
final class ExampleWorker extends BaseWorker
{
    public function identifier(): string { return 'example'; }
    public function scope(): string { return 'custom'; }
    public function icon(): string { return '<i data-lucide="settings"></i>'; }
    public function title(): string { return 'Example'; }
    public function description(): string { return 'Example worker'; }

    public function taskMake(sTaskModel $task, array $options = []): void
    {
        // business logic
        $task->update(['progress' => 100, 'result' => ['ok' => true]]);
        $this->markFinished($task, null, 'Done');
    }
}
```

Découvrez :

- dénomination des actions `task{StudlyAction}`;
- signatures avec `sTaskModel` et options de tableau ;
- méthodes de métadonnées ;
- pas de `$modx`; utiliser `evo()`/services ;
- chemin de finalisation et d’exception ;
- messages de progression en une ligne ;
- Les secrets ne s’intègrent pas dans l’interface.

## Migration des horaires

Déplacez les anciennes clés de planning personnalisées vers :

```json
{
  "schedule": {
    "enabled": true,
    "type": "periodic",
    "frequency": "hourly",
    "time": "*:10",
    "datetime": "",
    "start_time": "",
    "end_time": ""
  }
}
```

Le planificateur 2.x attend `taskMake()` pour les planifications conventionnels. Manuel/once/périodique/régulier ne sont pas des expressions cron.

## Migration des superviseurs

Ne simulez pas un contrôle de santé minute par minute comme une tâche en file d’attente classique. Pour la classe démon, ajoutez `SupervisorWorkerInterface`, clé d’écurie, inspection en lecture seule, démarrage/redémarrage détaché, et grâce. sTask ne créera que des lignes de tâches pour les événements du cycle de vie.

## Base de données

Effectuez des migrations de paquets et vérifiez :

- les tables principales n’étaient pas des activités destructrices ;
- `s_supervisor_states` créée ;
- autorisation `stask` active ;
- les identifiants de workers existants n’ont pas changé par accident ;
- réglage JSON est valide.

Les migrations de base actuelles ont `Schema::create`, donc le comportement sécuritaire de la réinstallation dépend de la référence en amont actuelle. Mettez toujours à jour vers un commit 2.x éprouvé et lancez de la migration smoke sur une copie du schéma de production.

## Actifs et cache

```bash
php artisan package:discover
php artisan stask:publish
php artisan cache:clear-full
```

L’ancien JS/CSS dans le cache du navigateur peut casser le changement de tabulation ou la progression en direct.

## Test d’acceptation

- sTask s’ouvre avec un rôle d’autorisation ;
- chacune des cinq œuvres de tabs ;
- le registre reconnaît les workers des services de sécurité ;
- les extrémités `taskMake` manuelles ;
- un planning futur crée une tâche en file d’attente ;
- Le Progress en Temps Réel est mis à jour par sondage ;
- double-clic ouvre le modal ;
- arrêt d’urgence indique l’échec de l’enregistrement actif du test ;
- Les transitions étatiques du superviseur vers un démarrage sain → sans inondation d’événements ;
- dDocs affiche un arbre sTask localisé.

## Retour en arrière

Annulez constamment le code/verrouillage et la base de données. Ne supprimez pas `s_supervisor_states` ni de nouveaux champs tant que le processus 2.x peut s’exécuter. Si la version 1.x ne comprend pas les nouveaux paramètres, sauvegardez la sauvegarde et préparez une transformation explicite.
