# local_evenements — Événements et billetterie pour Moodle

Plugin Moodle (type *local*) pour organiser les événements du lycée (JPO, remise des diplômes,
forum des métiers…) : réservation en ligne, **billets nominatifs avec QR code**, billet PDF
personnalisable, contrôle à l'entrée, export d'émargement.

Version : **1.0.0 (stable)** — en service sur un Moodle de lycée (Moodle 5.0).
Licence : GNU GPL v3 ou ultérieure.

## Nouveautés de la 0.14.0

- **Bilan** (menu de l'événement, sous « Vue d'ensemble ») :
  - billets réservés, présents et taux de présence, absents (avec un lien vers leur liste), liste d'attente, taux de réponse à l'enquête ;
  - présence par catégorie et par créneau, heures d'arrivée par quart d'heure avec le pic, rythme des réservations de J-60 au jour J ;
  - entrées par opérateur, billets refusés ou présentés deux fois ;
  - version imprimable.
- **Comparaison avec l'édition précédente** : un événement dupliqué garde le lien vers l'original. Le bilan affiche les écarts et superpose les deux courbes de réservation.
- **Chiffres conservés après la purge RGPD** : avant d'effacer les données personnelles, le plugin garde une copie anonyme des chiffres (sans nom de participant ni d'opérateur). Le bilan reste consultable et sert de référence pour l'édition suivante.
- **Ajouter depuis Moodle** (Participants) : les membres d'une cohorte ou les inscrits d'un cours (filtrés par rôle, élève par défaut), avec un aperçu de la liste avant l'ajout.
  - Événement sur liste : les personnes vont dans la liste d'invités (catégorie et détail au choix), puis on envoie leurs liens depuis la liste.
  - Autre événement : chaque personne reçoit un billet rattaché à son compte Moodle, avec envoi par e-mail en option. Les personnes qui ont déjà un billet sont ignorées.
  - L'ajout est noté dans le journal des modifications.
- **Rappel de la veille** : il invite à annuler par le lien personnel pour libérer la place au profit de la liste d'attente.

Mise à jour : remplacer le dossier, puis *Administration du site > Notifications* (champ `sourceid` et table `local_evenements_report`).

## Nouveautés de la 0.13.0

- **Gestion des participants** (*Inscrits*) : recherche, filtres (actifs, valides, entrés, annulés, catégorie), fiche de chaque participant pour modifier le nom, l'e-mail, la catégorie, la mention, le contact et le créneau, puis renvoyer le billet.
  - *Ajouter un participant* à la main (réservation au téléphone, invité de dernière minute). La jauge, les quotas et les créneaux s'appliquent, sauf si l'on coche « Ignorer les limites ».
  - *Annuler le billet* : la place est libérée, le billet est refusé à l'entrée et l'historique des entrées reste. Un billet annulé peut être *rétabli* avec le même QR code.
  - *Supprimer définitivement* (RGPD), après confirmation : le billet, ses réponses et ses contrôles d'entrée sont effacés, et le journal garde une ligne sans nom.
  - Actions groupées : cocher plusieurs participants pour renvoyer, annuler, rétablir, changer de catégorie ou de créneau, ou supprimer.
  - *Journal des modifications* (qui a fait quoi et quand), en bas de la page et sur chaque fiche.
  - Événements sur liste : annuler le dernier billet d'une réservation remet la personne à « non réservé » ; rétablir le billet la relie de nouveau.
- La console d'organisation reste dans Moodle, avec son menu latéral.

Mise à jour : remplacer le dossier, puis *Administration du site > Notifications* (la table `local_evenements_changelog` est créée).

## Mise à jour depuis la 0.1.x / 0.2.0

Remplacer le dossier, puis *Administration du site > Notifications* : les tables des questionnaires (0.2.0), le champ « billets par personne » (0.2.1) et la table de la liste d'attente (0.3.0) et les deux indicateurs d'envoi des e-mails (0.4.0) sont créés.

## Installation

1. Le dossier du plugin doit s'appeler `evenements`.
2. Soit installer le zip via *Administration du site > Plugins > Installer des plugins*,
   soit copier le dossier dans `local/evenements` (Moodle 5.0) ou `public/local/evenements` (Moodle 5.1 et suivants).
3. Aller dans *Administration du site > Notifications* pour créer les tables.
4. *Administration du site > Plugins > Plugins locaux > Événements et billetterie* : régler la durée
   de conservation (30 jours par défaut), la limite anti-spam et le texte d'information RGPD.

## Utilisation

La barre de menu principale de Moodle affiche **Événements** et **Gérer les événements** aux gestionnaires seulement (réglage par défaut, pendant le développement). Dans les réglages du plugin, « Entrées dans le menu principal » permet de les masquer ou de les montrer à tout le monde.

| Adresse | Rôle |
| --- | --- |
| `/local/evenements/index.php` | Page publique : événements ouverts (sans compte Moodle) |
| `/local/evenements/manage.php` | Gestion : créer, modifier, supprimer, inscrits, export CSV |
| `/local/evenements/registrations.php?id=…` | Participants : recherche, modification, annulation, suppression, actions groupées, journal |
| `/local/evenements/participant.php?id=…` | Ajouter un participant à la main (`&t=…` : fiche d'un participant) |
| `/local/evenements/report.php?id=…` | Bilan de l'événement, comparaison avec l'édition précédente |
| `/local/evenements/moodleimport.php?id=…` | Ajouter des personnes depuis une cohorte ou un cours |
| `/local/evenements/scan.php` | Contrôle à l'entrée (le QR code d'un billet ouvre cette page) |
| `/local/evenements/questions.php?id=…` | Questions d'un événement (lien « Questionnaires » dans la gestion) |
| `/local/evenements/results.php?id=…` | Résultats des questionnaires |

Droits (rôle *Gestionnaire* par défaut) : `local/evenements:manage`, `local/evenements:scan`,
`local/evenements:viewregistrations`. Pour les agents d'accueil, créer un rôle système avec uniquement `scan`.

### Parcours de réservation
1. L'invité choisit l'événement, puis le nombre de billets.
2. Il saisit ses coordonnées et, pour **chaque invité**, nom, prénom, catégorie, e-mail facultatif.
3. Il accepte le texte RGPD et valide : un e-mail part avec les billets (un PDF, une page par invité)
   et un **lien personnel** pour retélécharger les billets ou annuler.

### Personnalisation du billet (par événement)
Image (colonne de gauche), couleur, titre du bandeau, texte libre en bas. Format 210 × 90 mm,
un QR code unique et un numéro par billet.

## Ce qui est fait

- Tables, droits, réglages, chaînes en français et en anglais
- Événements, catégories de billets avec quotas, jauge globale, fenêtre d'ouverture
- Limites de billets : **par réservation** (1 à 20) et **par personne** (même e-mail de contact ou même compte Moodle, toutes réservations confondues, billets annulés exclus ; 0 = sans limite)
- Réservation nominative (verrou contre les doubles réservations), anti-spam (honeypot + limite par IP)
- Billet PDF (TCPDF intégré à Moodle, aucune bibliothèque externe), e-mail de confirmation
- Page personnelle : téléchargement, annulation
- Liste d'attente : quand l'événement est complet, le visiteur laisse nom, e-mail et nombre de billets. À chaque annulation (ou augmentation de la capacité), les premiers inscrits dont la demande tient dans les places libres reçoivent un e-mail avec un lien personnel. Les places ne sont pas bloquées : la première personne qui réserve les obtient. Liste visible dans *Inscrits*, purgée avec les autres données
- Contrôle d'entrée : scan par la caméra (Chrome sur Android, page en HTTPS), saisie du code, ou appareil photo du téléphone qui ouvre le QR code ; double scan détecté, journal des contrôles
- Liste des inscrits et export CSV (avec colonne d'émargement et une colonne par question)
- Questionnaires : questions à la réservation (une fois par réservation ou pour chaque invité : régime alimentaire, filière visée…) et enquête de satisfaction après l'événement, via le lien personnel ; types texte court, texte long, choix unique, choix multiples ; page de résultats
- Rappel par e-mail avant l'événement (24 h par défaut, réglable, 0 = désactivé) et enquête de satisfaction envoyée après l'événement aux réservations dont un billet a été contrôlé : tâche planifiée « Envoyer les rappels… » toutes les 15 minutes (le cron de Moodle doit tourner)
- Duplication d'un événement (brouillon, dates décalées de 52 semaines, catégories, questions, image), message groupé aux inscrits, ajout au calendrier (.ics) depuis la page personnelle, compteur d'entrées en direct sur la page de contrôle
- Interface : cartes d'événements (pastille de date, jauge de places), page événement avec bandeau et panneau de réservation, étapes de réservation, billets en cartes, tableau de bord de gestion ; couleurs reprises du thème Moodle (`styles.css`)
- RGPD : API Privacy de Moodle, purge automatique après l'événement

## À faire (prochaines étapes)

- Créneaux (la table `session` existe, l'interface reste à écrire)
- Bibliothèque de modèles de billets partagés entre événements, aperçu en direct
- Statistiques, invitations par liste CSV, Apple/Google Wallet, mode hors ligne du contrôle, blocage temporaire des places pour la liste d'attente
- Tests PHPUnit et Behat

## Points à vérifier sur votre serveur

- **Pages publiques** : elles n'appellent pas `require_login()`. Vérifier qu'elles s'affichent sans connexion,
  y compris si le réglage `forcelogin` est actif.
- **Envoi d'e-mails** : le serveur de messagerie doit accepter les envois avec pièce jointe et en nombre
  (la veille d'une JPO, par exemple).
- **IP partagée** : sur le Wi-Fi du lycée, plusieurs personnes partagent la même adresse IP ; ajuster la limite anti-spam.
- **Données des mineurs** : faire valider le texte d'information par le délégué à la protection des données.
- Les visiteurs sans compte ne sont pas retrouvables par l'API Privacy (pas d'identifiant) : leurs données
  sont supprimées par la purge automatique, ou à la main via *Gérer les événements*.

## Espace organisateur (0.6.0)

Tout se fait dans le plugin, sans passer par l'administration du site :

- menu **Gérer les événements** (visible des gestionnaires uniquement par défaut) ;
- **+ Créer un événement** ;
- tableau de bord de l'événement : liste « Préparez votre événement », bouton **Publier**, chiffres clés, courbe des réservations ;
- onglets : Inscrits, Questionnaires, Résultats, Message, Réglages, Contrôle d'entrée ;
- **Réglages généraux** (menu, rappels, conservation, anti-abus, mention RGPD) dans `preferences.php`.

## Nouveautés 0.7.0

- **Créneaux horaires** (onglet « Créneaux ») : plusieurs créneaux par événement avec leurs places ; le visiteur en choisit un, son billet l'indique.
- **Import CSV** d'invités (NOM ; Prénom ; e-mail ; catégorie) avec envoi des billets.
- **Liste d'émargement imprimable**.
- **Équipe** : co-organisateurs limités à un événement.
- **Scan hors ligne** : liste téléchargée dans le téléphone, synchronisation au retour du réseau.
- **Invités** : « Retrouver mes billets » par e-mail, modification du nom sur un billet (jusqu'à N heures avant), ajout à Google Agenda.
- **Billet** : logo et police au choix, aperçu en direct.
- Non fait : Apple/Google Wallet (nécessite des certificats et comptes développeur).

## Espace opérateurs et application de scan (0.8.0)

- Onglet **Opérateurs** : un bénévole = un code d'accès personnel (lien ou QR code d'invitation), sans compte Moodle ; retrait d'accès, nouveau code, badges imprimables. L'accès expire 24 h après la fin de l'événement.
- **Application PWA** `local/evenements/operator.php` : installable sur le téléphone, connexion par code, scan caméra, recherche par nom, saisie de code, annulation d'une validation (5 min), liste hors ligne et synchronisation automatique, compteur en direct.
- Tableau de bord : entrées en direct (par opérateur, derniers passages).
- iPhone/Safari ne lit pas les QR par caméra nativement : déposer `jsqr.min.js` (licence Apache-2.0, projet jsQR) dans `pwa/` pour activer la lecture. Sans lui, la recherche par nom et la saisie de code restent disponibles.

## Console organisateur (0.9.0)

Menu latéral sombre sur toutes les pages organisateur : bouton « Créer un événement », carte de l'événement (statut, jauge de remplissage, entrées en direct), rubriques repliables (Billetterie, Participants, Questionnaires, Communication, Accueil des invités), recherche rapide `Ctrl+K`, tiroir sur téléphone. Nouvelle page **Exports**.

## Formats de billets et badges (0.10.0)

- **Billets plus compacts** : *Compact* (148 × 68 mm, par défaut pour les nouveaux événements), *Carte d'embarquement* (175 × 74 mm, talon avec QR) et *Concert* (210 × 90 mm, conservé pour les événements existants). Les billets compacts peuvent s'imprimer en planche A4 avec traits de découpe (lien « Imprimer sur feuille A4 » sur la page du billet).
- **Badges** (onglet « Badges », rubrique Accueil des invités) : création automatique des badges du jour J à partir de l'événement (nom, date, lieu, couleur, police, logo). Sources : équipe (staff, accueil, bénévoles… ajout un à un ou collage « Prénom NOM ; Rôle »), opérateurs de scan, co-organisateurs, invités inscrits (par catégorie, QR du billet en option), badges vierges. Formats A6 (4 par page) et carte 90 × 55 mm (10 par page), bande de couleur selon le rôle, repère de trou pour cordon.

## Réservation sur liste d'invités (0.11.0)

Pour les événements nominatifs (remise des diplômes…), réglage **Mode de réservation = Sur liste d'invités** :

- onglet **Liste des invités** : import (NOM ; Prénom ; e-mail ; Catégorie ; Détail ; Invités, collage depuis Excel possible) pour les diplômés mais aussi partenaires, enseignants, AESH, direction ; suivi (pas invité, lien envoyé, identité confirmée, a réservé), envoi / relance des liens en un clic, nouveau lien, annulation de réservation, export avec les liens ;
- **un lien personnel par personne**, envoyé à l'adresse de la liste (jamais à une adresse saisie par l'élève) ; la page publique « Recevoir mon lien personnel » répond toujours la même chose, que le nom soit connu ou non ;
- parcours : confirmation d'identité (nom, catégorie, diplôme) puis réservation **unique** de son billet et de ceux de ses invités (quota contrôlé côté serveur, sous verrou), questionnaire facultatif (ex. buffet) ;
- billet invité nominatif « Invité de Prénom NOM » ; billet du diplômé avec son diplôme ; texte par catégorie imprimé en gras (ex. « Pièce d'identité obligatoire pour le retrait du diplôme ») : format `Nom|quota|texte` dans les catégories ;
- la réservation ouverte (`book.php`, liste d'attente) est refusée sur ces événements ; les données de la liste sont effacées avec les autres données personnelles.

Menu latéral compacté : une seule rubrique ouverte à la fois.

## 0.12.0

- **Modèles de questionnaires** : 10 questionnaires prêts à l'emploi (avant : buffet, accessibilité, « comment nous avez-vous connus », venue, partenaires, infos par invité ; après : satisfaction, après portes ouvertes, après cérémonie, prochaine édition), ajoutables en un clic depuis la page « Questionnaires » (sans doublons).
- **Images par défaut** : 4 thèmes (général, portes ouvertes, remise des diplômes, forum) choisis dans la fiche de l'événement ; utilisés sur le site et les billets quand aucune image n'est téléversée.
- **Logo par défaut** : logo de l'événement → logo du plugin (Réglages) → logo du site Moodle → logo fourni ; utilisé sur billets et badges.
- Mise en page plus compacte et professionnelle (cartes, tableaux, formulaires, largeur de page, menu latéral jamais tronqué).
