# Emplois du temps

L’emploi du temps est stocké dans `s_workers.settings.schedule`. L’éditeur dans l’onglet **Ouvriers** normalise la charge utile en champs `enabled`, `type`, `datetime`, `frequency`, `time`, `start_time`, `end_time`.

## Manuel

```json
{"enabled": false, "type": "manual"}
```

Une tâche automatique n’est pas créée. Le lancement s’effectue par un bouton gestionnaire ou un code PHP.

## Une fois

```json
{
  "enabled": true,
  "type": "once",
  "datetime": "2026-07-20 03:30:00"
}
```

`stask:worker` ne crée une tâche que si la date est encore dans le futur et que le worker n’a pas une tâche inachevée. Si le temps est déjà écoulé avant le passage du premier planificateur, la tâche ne sera pas créée.

## Périodique

Fréquences prises en charge :

| Fréquence | Champs | Prochain lancement |
| --- | --- | --- |
| `minutely` | Temps non requis | minute suivante |
| `every_5min` | Temps non requis | multiples de minutes les plus proches de 5 |
| `every_15min` | Temps non requis | multiples de minute les plus proches de 15 |
| `every_30min` | Temps non requis | minute la plus proche en multiples de 30 |
| `hourly` | `time = *:MM` | Heure/minute suivante |
| `daily` | `time = HH:MM` | Aujourd’hui ou demain |
| `weekly` | `time`, `days[]` | Prochain jour choisi |
| `monthly` | `time` | jour actuel du mois ; UI ne fournit pas de champ journalier séparé |

Exemple chaque jour à 02h15 :

```json
{
  "enabled": true,
  "type": "periodic",
  "frequency": "daily",
  "time": "02:15"
}
```

Limitation pratique de UI 2.x : modal a du temps, mais n’affiche pas l’éditeur `days` pour hebdomadaire et `day` pour le mensuel. Ces valeurs ne peuvent être enregistrées que via les réglages/code JSON ; Avant la production, vérifiez-les avec de vrais `stask:worker`.

## Régulier dans la fenêtre horaire

```json
{
  "enabled": true,
  "type": "regular",
  "frequency": "every_15min",
  "start_time": "08:00",
  "end_time": "18:00"
}
```

Intervalles disponibles : `every_5min`, `every_15min`, `every_30min`, `hourly`. La fenêtre doit être dans un délai d’un jour calendaire : si `end_time < start_time`, la prochaine heure n’est pas calculée. La fenêtre de nuit comme `22:00–06:00` l’implémentation actuelle n’est pas prise en charge.

La recherche de la case suivante commence à `start_time` et ajoute des intervalles jusqu’à ce que le candidat soit plus tard que `now`. Après la fin de la fenêtre, la fonction retourne `null`; la tâche pour le lendemain n’est pas créée dans ce passe. C’est une limitation opérationnelle importante : vérifier le comportement souhaité en fin de journée.

## Superviseur

`type = supervisor` n’est disponible qu’en modal pour une classe qui implémente `SupervisorWorkerInterface`. Il ne crée pas de contrôle de santé à chaque minute. Le planificateur met à jour une ligne d’état en direct, et les lignes de tâches sont créées uniquement pour des événements significatifs du cycle de vie.

Détails : [Superviseur de processus](supervisor.md).

## Règle de la tâche unique incomplète

Pour une fois/périodique/régulier, le planificateur vérifie les tâches de l’ouvrier de relation avec la portée `incomplete()` et ne crée pas la tâche suivante s’il y a un enregistrement en file d’attente/préparation/exécution. Une tâche longue ou figée bloque ainsi la planification supplémentaire de cet identifiant.

L’arrêt d’urgence libère l’enregistrement, le mettant en échec, mais ne coupe pas le processus du système d’exploitation. D’abord, il faut définir si le processus est toujours en cours, puis exécuter la tâche suivante.

## Cron et Laravel planificateurs

Le fournisseur ajoute la commande :

```php
$schedule->command(TaskWorker::class)->everyMinute();
```

Cette définition ne fonctionne pas seule avec l’ordonnanceur. L’infrastructure doit effectuer `php artisan schedule:run` chaque minute ou `schedule:work` maintenir sous la supervision d’un superviseur externe des processus.

## Erreurs courantes

- **Rien n’est créé** — worker inactif, emploi du temps désactivé, pas de `taskMake()`, temps invalide, ou une tâche déjà incomplète.
- **Une fois passé** — le planificateur a vu l’heure de date une fois passée.
- **Hebdomadaire ne fonctionne pas** - `days` réseau manque.
- **Arrêté régulièrement le soir** — l’algorithme actuel ne déplace pas la case suivante au lendemain.
- **Duplicates** — plusieurs `stask:worker` fonctionnent en parallèle ; La vérification des doublons n’est pas un verrou distribué atomique.
