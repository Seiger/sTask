# Superviseur de processus

Le planning superviseur est conçu pour des processus détachés à long terme : écouteur, démon de transport ou autre exécution appartenant au worker. sTask ne sait pas comment vérifier le PID ou le battement cardiaque d’un processus particulier ; Cela est fait par un adaptateur en classe du worker.

## Contrat

Worker reste simultanément un `TaskInterface`/`BaseWorker` régulier et ajoute :

```php
use Seiger\sTask\Contracts\SupervisorWorkerInterface;
use Seiger\sTask\Support\SupervisorStatus;

interface SupervisorWorkerInterface
{
    public function supervisorKey(): string;
    public function inspectSupervisor(): SupervisorStatus;
    public function startSupervisor(): SupervisorStatus;
    public function restartSupervisor(): SupervisorStatus;
    public function supervisorStartupGraceSeconds(): int;
}
```

`inspectSupervisor()` ne devrait pas changer l’autonomie. `startSupervisor()` et `restartSupervisor()` devraient revenir rapidement après le lancement détaché ; Ils n’ont pas à attendre que le Démon termine la fin.

## Statut du superviseur

```php
return new SupervisorStatus(
    state: SupervisorState::Healthy,
    pid: 18422,
    heartbeatAt: now()->subSeconds(5),
    startedAt: now()->subHours(3),
    uptimeSeconds: 10800,
    message: 'Listener is receiving updates',
    fingerprint: 'listener:healthy:v1',
);
```

Champs :

- `state` — `healthy`, `starting`, `degraded`, `failed`, `stopped`;
- `pid` — PID informationnel, annulable ;
- `heartbeatAt` — la dernière confirmation de la vie ;
- `startedAt` et `uptimeSeconds` — démarrage/disponibilité du processus, que l’interface utilisateur affiche ;
- `message` — diagnostics sûrs pour le manager ;
- `fingerprint` est une empreinte adaptatrice stable. Si non spécifié, sTask hache état + message.

N’incluez pas dans les messages ou les traces d’empreintes, les secrets de session ou les lignes de commande complètes avec les identifiants.

## Un passage de planificateur

1. sTask reçoit un `supervisorKey()` non vide .
2. Formes `key_hash = sha256(worker_id + ':' + supervisor_key)`.
3. Prend le verrouillage du cache de fichiers `stask:supervisor:{key_hash}` pendant 55 secondes.
4. Cause une inspection en lecture seule.
5. Si l’état `stopped`, `degraded` ou `failed` et que la grâce au démarrage est expirée, cela appelle démarrer/redémarrer.
6. Renouveler `s_supervisor_states`.
7. Crée une dernière ligne de tâche uniquement pour un événement significatif : démarré, redémarré, récupéré, échoué, arrêté, changement d’état/diagnostic.

Un instantané sain avec la même empreinte ne met à jour que `last_seen_at`, le rythme cardiaque/le temps d’activité et `repeat_count`; Une nouvelle tâche n’est pas créée à chaque minute.

## Grâce au démarrage

`launch_requested_at` se souvient de la dernière demande de lancement. Jusqu’à ce que `max(1, supervisorStartupGraceSeconds())` soit passé, le redémarrage/redémarrage est supprimé. Après un état de santé, le terrain est dégagé.

Grace ne prouve pas que le processus a commencé. L’adaptateur doit distinguer correctement entre `starting`, `healthy`, `degraded`, `failed`, `stopped` par PID/battement cardiaque/preuve d’exécution.

## Transitions et tâches d’événements

| Observation | Action | Tâche d’événement |
| --- | --- | --- |
| Première `stopped` | `startSupervisor()` | `started`, ou `failed/stopped` selon le statut de retour |
| Des `degraded/failed` existantes | `restartSupervisor()` | `restarted`, ou échec |
| `starting` en grâce | Le lancement ne se répète pas | seulement lors du changement d’état/empreinte digitale |
| Mauvais → `healthy` | état persister | `recovered` |
| État modifié | état persister | événement avec le nouvel État |
| Empreinte digitale a changé | diagnostic persister | `diagnostic_changed` |
| En santé inchangée | Rafraîchissement du curseur | pas de tâche |

Une tâche d’événement a une action `supervisor`, `started_by = 0`, `max_attempts = 1`, horodatages, `now()`. Échec/dégradé/arrêté devient statut de tâche échouée ; Autres événements — terminés.

## Onglet Workers

Pour l’emploi du temps du superviseur, le tableau affiche une puce avec `activity-heartbeat`. Si l’État est en bonne santé et `uptime_seconds` connu, le badge affiche la mise en disponibilité formatée via `niceEta()` au lieu du mot « Œuvre ». Le modal indique la clé, l’état, la PID, le battement de cœur, la disponibilité du temps, le dernier diagnostic et le temps de transition.

## État du tableau et croissance

`key_hash` unique, donc une ligne est `worker_id + supervisor_key` mise à jour pour la même paire. Un battement de cœur normal ne crée pas de lignes d’historique. Le tableau ne s’agrandit que lorsque de nouveaux identifiants/clés d’ouvriers apparaissent ; Il n’y a pas d’élagage automatique.

Après désinstallation ou réinstallation des workers, les lignes d’état orphelins ne sont pas effacées avec une clé étrangère, car le schéma ne crée pas de FK/cascade. Ne faites un nettoyage de routine qu’après avoir vérifié que l’worker ou la clé n’existe plus.

## Liste opérationnelle

- lancement détaché et retour rapide ;
- la clé superviseur est stable et non vide ;
- le battement cardiaque a un temps d’arrêt clair ;
- Le PID est vérifié en même temps que l’identité du processus, pas seulement `posix_kill($pid, 0)`;
- démarrer/redémarrer idempotent ;
- La grâce est plus grande qu’un temps de démarrage typique ;
- le systemd/superviseur externe ne conflit pas avec la politique de redémarrage de l’adaptateur ;
- Les journaux et l’État ne contiennent pas de secrets.
