# Tables de bases de données

Ci-dessous sont présentées les migrations réelles du schéma de la branche 2.x.

## `s_workers`

| Colonne | Type/attributs | But |
| --- | --- | --- |
| `id` | BIGINT PK | ID interne de l’ouvrier |
| `uuid` | UUID unique nullable | Identité externe optionnelle |
| `identifier` | chaîne unique | Clé de routage stable des tâches |
| `scope` | chaîne par défaut `''` | Regroupement de paquets/modules |
| `class` | chaîne | FQCN |
| `active` | Faux par défaut booléen | Planificateur/porte de course |
| `position` | unsigned int par défaut 0 | Ordre de l’UI |
| `settings` | Expression par défaut du tableau vide JSON | Calendrier/Paramètres personnalisés |
| `hidden` | unsigned int par défaut 0 | Visibilité UI |
| horodatages | créé/mis à jour | Audit |

Index : UUID unique, identifiant unique, plus identifiant, portée, actif, index de position. Un identifiant unique crée déjà un index ; un index explicite supplémentaire peut être redondant selon la base de données.

## `s_tasks`

| Colonne | Type/attributs | But |
| --- | --- | --- |
| `id` | BIGINT PK | ID de tâche |
| `identifier` | chaîne | Clé de routage des workers |
| `action` | chaîne | Code d’action |
| `status` | Unsigned Small Int par défaut 10 | Cycle de vie |
| `message` | Texte annulable | résumé/erreur persistant |
| `started_by` | unsigned int nullable | Gestionnaire utilisateur ou système |
| `meta` | longText annulable | Métadonnées d’entrée |
| `result` | longText annulable | charge utile/chemin du résultat |
| `start_at` | Annulable en horodatage | Début programmé/réel |
| `finished_at` | Annulable en horodatage | Dernière fois |
| `attempts` | int par défaut 0 | incrémenté sur la course |
| `max_attempts` | int par défaut 3 | métadonnées de réessai |
| `priority` | Normal par défaut de la chaîne | Compatibilité/Ordre des files d’attente |
| `progress` | int par défaut 0 | progrès persistants |
| horodatages | créé/mis à jour | Audit |

Index : `(identifier, action)`, statut, started_by, start_at, created_at, priorité.

Il n’y a pas de clé étrangère de l’identifiant de tâche vers `s_workers.identifier`, donc l’historique survit à la suppression de l’enregistrement du worker (worker record). La relation fonctionne logiquement par identifiant.

Meta/result présente le modèle Eloquent comme un tableau ; le stockage réel est du long texte, pas du JSON natif.

## `s_supervisor_states`

| Colonne | Type/attributs | But |
| --- | --- | --- |
| `id` | BIGINT PK | ID de ligne d’état |
| `worker_id` | BIGINT non signé, indexé | propriétaire `s_workers.id` sans FK |
| `identifier` | chaîne, indexée | Clé de travail dénormalisée |
| `supervisor_key` | chaîne | Identité du processus de l’adaptateur |
| `key_hash` | char(64) unique | sha256 identifiant ouvrier + clé |
| `state` | string(24), par défaut stoppé, indexé | État du cycle de vie |
| `pid` | BIGINT non signé nul | ID de processus |
| `heartbeat_at` | Annulable en horodatage | Dernier battement de cœur |
| `supervisor_started_at` | Annulable en horodatage | Début du processus |
| `uptime_seconds` | BIGINT non signé nul | Disponibilité de l’adaptateur |
| `message` | Texte annulable | diagnostic |
| `fingerprint` | char(64) annulable | empreinte digitale dédupée |
| `last_transition_at` | Annulable en horodatage | Transition d’état |
| `last_seen_at` | Annulable timestamp, indexé | Dernière observation du planificateur |
| `repeat_count` | unsigned int par défaut 0 | Comptage répété des empreintes digitales |
| `launch_requested_at` | Annulable en horodatage | Curseur de grâce de démarrage |
| horodatages | créé/mis à jour | Audit des archives |

### Cycle de vie

`firstOrNew(key_hash)` garantit une ligne active par identifiant de worker + clé stable. Chaque passage met à jour la dernière vue ; La même empreinte digitale augmente le nombre de répétitions, la nouvelle empreinte la réinitialise à zéro. La transition d’état met à jour `last_transition_at`.

Le statut sain purifie `launch_requested_at`. Les autres statuts conservent la demande de lancement précédente ou actuelle.

### Croissance & Purification

L’historique des battements de cœur n’accumule pas de lignes. La croissance signifie de nouvelles identifiants ou clés de worker. Il n’y a pas de rétention automatique, il n’y a pas de clé étrangère ou de cascade.

Nettoyage en toute sécurité :

1. Inventorier les workers réels et les clés adaptatrices ;
2. Assurez-vous que Daemon avec Vieille Clé ne fonctionne pas ;
3. archiver le diagnostic si nécessaire ;
4. Supprimer uniquement les identifiants/hachages exacts des orphelins.

Ne faites pas de `TRUNCATE` pendant la planification active.

## Tables d’autorisations

Migration, si des tables système existent :

- trouve/crée un groupe `sTask`;
- `stask` de permission à l’upsert avec `disabled = 0`;
- ajoute role_permissions pour le rôle `1`;
- sur PostgreSQL, il est capable de restaurer la séquence après un conflit d’insertion.

La migration désactive l’envelopper de transaction Laravel car PostgreSQL annule la transaction après l’écho de l’instruction ratée, et le code a un chemin de réessayage.

## Portabilité

Schema est axé sur MySQL/MariaDB/PostgreSQL/SQLite via Laravel Schema Builder. L’expression par défaut JSON `JSON_ARRAY()` est sensible à la base de données ; Effectuez des tests de migration sur le moteur cible. La migration des permissions gère séparément la séquence PostgreSQL.
