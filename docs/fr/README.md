# Documentation sTask

sTask est le paquet Evolution CMS pour les taches en arriere-plan. Il detecte
les workers, cree des taches en file, suit la progression, stocke les logs et les
resultats, et fournit un panneau manager EvoUI + Livewire.

## Guides

- [Guide utilisateur](03-manager/interface.md)
- [Guide developpeur](04-development/public-api.md)
- [Reference](06-reference/cli-statuses-routes.md)
- [Configuration](06-reference/configuration.md)
- [Depannage](05-operations/troubleshooting.md)
- [Migration des custom workers](05-operations/upgrade-1-to-2.md)
- [Guide frontend](01-getting-started/quick-start.md)
- [Guide backend](02-concepts/architecture-and-lifecycle.md)

## Surfaces manager

- Tableau de bord avec compteurs de taches et workers actifs.
- Dernieres taches avec action oeil et modal de details au double-clic.
- Taches en table/liste avec filtres worker, action, statut, priorite, tentatives et date de creation.
- Workers avec edition, lancement, activation/desactivation, statut et disponibilite de classe.
- Logs avec le meme modal de details que les taches.
- Onglet statistiques pour performance/cache.

## dDocs

Ce dossier est la source documentaire fichier-first. Les anciennes pages
Docusaurus restent historiques; dDocs doit commencer par les dossiers de langue.
