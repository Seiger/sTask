# Guide utilisateur

## Ouvrir sTask

Ouvrez sTask depuis le manager Evolution CMS. Le nouveau panneau utilise les
assets locaux EvoUI. Si l'ecran apparait sans style, verifiez d'abord la
publication des assets `stask` et `evo-ui`.

## Tableau de bord

Le tableau de bord affiche les taches en attente, en cours, terminees, en erreur,
le total des taches et les workers actifs. Les dernieres taches s'ouvrent avec
l'action oeil ou un double-clic. Si aucune erreur n'existe, le bloc des erreurs
recentes est masque.

## Taches

L'onglet Taches supporte les vues table et liste. Filtres disponibles:

- worker;
- action;
- statut;
- priorite;
- tentatives;
- periode de creation.

Les colonnes couvrent ID, worker, identifiant, action, statut, priorite,
progression, tentatives, utilisateur de lancement, message, creation, debut,
fin et mise a jour.

## Workers

La liste des workers affiche titre, description, identifiant, scope, etat actif,
classe disponible, visibilite, nombre de taches, dernier statut, position et
mise a jour. Actions: editer, lancer, activer/desactiver.

## Logs

Les logs sont le panneau d'audit des taches et utilisent le meme modal de details
que l'onglet Taches. Filtrez par worker, statut et date de creation.

## Operations sures

Lancer un worker, executer Composer update, lancer des commandes Artisan ou
desactiver un worker modifie l'etat du systeme. Republiez les assets apres les
mises a jour et reconnectez-vous apres les nouvelles permissions.
