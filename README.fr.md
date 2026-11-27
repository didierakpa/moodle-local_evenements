# Événements et billetterie pour Moodle

Plugin Moodle de type *local* pour organiser des événements (portes ouvertes, remise des diplômes,
forum des métiers), de la réservation en ligne au contrôle à l'entrée. Les visiteurs réservent sans
compte Moodle et reçoivent par e-mail des billets nominatifs avec QR code.

- Version 1.0.1 (stable) — Moodle 5.0 ou ultérieur
- Licence : GNU GPL v3 ou ultérieure
- English: [README.md](README.md)

## Fonctions

- **Réservation** : pages publiques, catégories de billets avec quotas, créneaux, liste d'attente,
  questions à la réservation ; mode « liste d'invités » pour les événements nominatifs (un lien personnel par personne).
- **Billets** : PDF avec QR code, plusieurs formats, personnalisables ; page personnelle pour télécharger,
  ajouter à l'agenda ou annuler ; rappel par e-mail avant l'événement.
- **Contrôle à l'entrée** : page de contrôle et application installable pour les bénévoles (sans compte Moodle),
  scan par caméra, recherche par nom, mode hors ligne.
- **Organisation** : gestion des participants avec journal des modifications, actions groupées, ajout depuis
  une cohorte ou un cours, messages, exports, badges, liste d'émargement.
- **Bilan** : présence, heures d'arrivée, rythme des réservations, enquête, comparaison avec l'édition précédente.
- **RGPD** : API Privacy de Moodle, données personnelles effacées automatiquement après l'événement.

## Installation

1. Copier le plugin dans `local/evenements` (Moodle 5.0) ou `public/local/evenements` (Moodle 5.1 et suivants),
   ou installer le zip depuis *Administration du site > Plugins > Installer des plugins*.
2. Aller dans *Administration du site > Notifications*.
3. Régler le plugin dans *Administration du site > Plugins > Plugins locaux > Événements et billetterie*.
4. Le cron de Moodle doit tourner (rappels, enquêtes, liste d'attente, purge).

Pour lire les QR codes avec la caméra sur iPhone (Safari), ajouter `jsqr.min.js` (jsQR, Apache-2.0) dans le dossier `pwa/`.

## Droits

- `local/evenements:manage` — créer et gérer les événements
- `local/evenements:viewregistrations` — voir les participants
- `local/evenements:scan` — contrôler les billets à l'entrée

Les trois sont donnés par défaut au rôle Gestionnaire.

## Licence

GNU General Public License v3 ou ultérieure. © 2026 Didier AKPA
