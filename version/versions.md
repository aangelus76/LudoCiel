# Changelog

## Format de Version
**V-02.B5.24.2**
- **V** = Version
- **02** = Semaine 2
- **B** = Mardi (A=Lundi, B=Mardi, C=Mercredi, D=Jeudi, E=Vendredi, F=Samedi, G=Dimanche)
- **5** = 5ème modification du jour
- **24** = 2024 (l'année)
- **2** = Numéro de modification

## Légende
### Types de modifications
- **[Fixé]** - Correction de bug
- **[Ajout]** - Nouvelle fonctionnalité
- **[Modif]** - Modification existante
- **[Correction]** - Amélioration/correctif
- **[Systeme]** - Refactorisation

### Modules
- **(Freq)** - Fréquentation
- **(Surdim)** - Réservation de surdim
- **(Anim)** - Animation
- **(Stat)** - Statistique

### Catégories
`Bug` `Code` `Fonctionnalité` `Correctif` `Ressources` `Interface`

---

## Version 2

### 38.F1.24.39
**[Correction]** - *(Freq)* - `Code`  
Refactorisation du code

### 38.G1.24.40
**[Ajout]** - *(Freq)* - `Fonctionnalité`  
Filtre l'affichage pour l'après-midi

### 39.C1.24.41
**[Fixé]** - *(Freq)* - `Bug`  
Checkbox décoché à la mise à jour de la liste

### 39.C2.24.42
**[Ajout]** - *(Freq)* - `Interface`  
Mise à jour automatique de la liste à 13H

### 39.D1.24.43
**[Fixé]** - *(Freq)* - `Fonctionnalité`  
Duplication de code qui exécutait 2 fois les actions (+ - …)

### 39.D2.24.44
**[Ajout]** - *(Freq)* - `Fonctionnalité`  
Indiquer qu'un individu a quitté la structure

### 42.F1.24.45
**[Ajout]** - *(Freq)* - `Interface`  
Visibilité des individus présents en salle

### 42.G1.24.46
**[Correction]** - *(Freq)* - `Correctif`  
Renforcement côté PHP pour les ID de groupe unique

### 42.G2.24.47
**[Correction]** - *(Freq)* - `Correctif`  
Désactivation du bouton "ajout de partenaire" à la soumission pour éviter les doubles enregistrements

### 42.G3.24.48
**[Correction]** - *(Freq)* - `Correctif`  
Affichage du bouton + des participants quand ils sont sortis (pour éditer le temps de présence quand même)

### 43.D1.24.49
**[Ajout]** - *(Freq)* - `Interface`  
Ajout de la possibilité de mettre un prénom aux individus, visible par survol sur l'icône

### 43.E1.24.50
**[Ajout]** - *(Freq)* - `Interface`  
Ajout de pilules sous les stats, qui affiche les partenaires présents du jour

### 43.E2.24.51
**[Fixé]** - *(Freq)* - `Bug`  
Le prompt pour enregistrer un prénom devenait persistant sur tous les autres prompts

### 43.E3.24.52
**[Correction]** - *(Freq)* - `Correctif`  
Oubli de l'utilisation "confirmer" une suppression de partenaire ou d'individu

### 43.E4.24.53
**[Ajout]** - *(Freq)* - `Interface`  
Amélioration de la customisation des messages dans les Alertes/Prompts

---

## Version 3

### 44.G1.24.54
**[Ajout]** - *(Freq)* - `Code`  
Modification complète du code javascript/api.php, utilisation de sessionStorage pour la fluidité de l'interface

### 45.D1.24.55
**[Fixé]** - *(Freq)* - `Bug`  
Apparition de la liste complète après l'ajout d'un Prénom/Commentaire

### 45.D2.24.56
**[Ajout]** - *(Freq)* - `Interface`  
Possibilité de marquer les partenaires comme partis dans les pills, ajout des partenaires encore présents dans le compteur de présence

### 45.E1.24.57
**[Ajout]** - *(Freq)* - `Interface`  
Amélioration des affichages Confirm()/Alert()

### 45.E2.24.58
**[Correction]** - *(Freq)* - `Correctif`  
Clignotement des données et données parfois pas prises en compte, problème de synchronisation et de file d'attente

### 45.F1.24.59
**[Ajout]** - *(Freq)* - `Interface`  
Indicateur 'spinner' pour montrer aux utilisateurs que des données sont en attente d'envoi au serveur (désactivation du menu pendant ce temps)

### 45.F2.24.60
**[Fixé]** - *(Freq)* - `Bug`  
Checkbox décoché à la mise à jour de la liste (2)

### 46.B1.24.61
**[Modif]** - *(Freq)* - `Interface`  
Amélioration visuelle Liste/Boutons/Pills/Stats

### 46.C1.24.62
**[Fixé]** - *(Freq)* - `Bug`  
Message dans alert() qui affichait le contenu du confirm() quand un ID de groupe n'existe pas à la saisie

### 48.B1.24.63
**[Correction]** - *(Freq)* - `Correctif`  
Oubli du focus sur l'input dans la fenêtre d'ajout de commentaire de groupe

### 48.E1.24.64
**[Ajout]** - *(Surdim)* - `Interface`  
Ajout d'un bouton pour indiquer qu'un jeu a été retourné et disponible

### 08.B1.25.65
**[Ajout]** - *(Freq)* - `Interface`  
Outils de stats des fréquentations

### 09.D1.25.66
**[Ajout]** - *(Freq)* - `Interface`  
Ajout affichage heures d'affluence

### 10.B1.25.67
**[Ajout]** - *(Freq)* - `Interface`  
Ajout affichage heures d'affluence par jours/semaine

### 10.B2.25.68
**[Correction]** - *(Freq)* - `Correctif`  
Tranche horaire dans les graphiques d'affluence d'heure changée, de tranche 1H on est à 30min

### 13.B1.25.69
**[Ajout]** - *(Freq)* - `Interface`  
Ajout du bouton qui permet d'accéder aux statistiques, mais doit être ouvert dans un navigateur récent.

### 13.B2.25.70
**[Correction]** - *(Surdim)* - `Interface`  
Taille de bouton trop gros, décalage du texte dans les boutons corrigé

### 20.C1.25.71
**[Fixé]** - *(Surdim)* - `Correctif`  
Relation entre les données de la BDD et l'affichage des ressources dans la liste

### 20.C2.25.72
**[Ajout]** - *(Surdim)* - `Interface`  
Ajout de ligne dans les cellules pour indiquer des réservations existantes sur 2 mois

### 20.C3.25.73
**[Ajout]** - *(Surdim)* - `Interface`  
Ajout de tag qui permet de voir la liste de réservation pour un nom sur le mois en cours

### 24.C1.25.74
**[Ajout]** - *(Surdim)* - `Interface`  
Ajout de bouton "Suivant/Retour" dans la liste des tags si la liste est trop longue

### 29.A1.25.75
**[Modif]** - *(Launcher)* - `Code`  
Amélioration du code pour l'ajout/supression de lien, lien version déplacé à droite

### 29.A2.25.76
**[Ajout]** - *(AutoFiche)* - `Interface`  
Ajout du systeme de création de fiche semi-automatisé pour le catalogue classeur. /!\ Module désactivé, Probleme de proxy!

### 41.C2.25.77
**[Ajout]** - *(Surdim)* - `Interface`  
-Ajout d'un systeme de vérification de conflit sur les réservation
-Correction d'un bug qui pouvais faire disparaitre une réservation qui venais d'être fait

### 41.E1.25.78
**[Ajout]** - *(Surdim)* - `Interface`  
Amélioration visuelle du selecteur multi-jour.

## Version 4

### 43.B1.25.79
**[Systeme]** - *(Freq)* - `Refactorisation`  
Conversion de la méthode SSE en Websocket

### 43.C1.25.80
**[Fixé]** - *(Freq)* - `Interface`  
Icone Prénom/Commentaire de groupe pas placé sur la bonne ligne

### 44.E1.25.81
**[Correction]** - *(Freq)* - `Interface`  
-Correction de l'icone commentaire des individus associé, le commentaire disparaissais quand ont regroupé un nouvelle individus
-Amélioration de l'affichage du status de connection au serveur WS

### 45.C1.25.82
**[Fixé]** - *(Freq)* - `Correctif`  
-Ajout d'un check de l'existance d'un serveur WS
-Amélioration du lancement serveur WS, systeme anti Zombie

### 45.F1.25.83
**[Fixé]** - *(Freq)* - `Correctif`  
-Total d'heure de présence des partenaire à la semaine, mauvaise méthode de filtrage corrigé

### 46.E1.25.84
**[Fixé]** - *(Freq)* - `Correctif`  
-Stabilité de l'interface améliorée lors du chargement de données massives (évite le crash), fiabilisation de l'encodage/décodage des paquets de communication

## Version 5

### 47.B1.25.85
**[Modif]** - *(Freq)* - `Refactorisation`  
-Recodage des pages statistiques périodes/annuel

### 47.C1.25.86
**[Ajout]** - *(Anim)* - `Interface`  
-Ajout d'un systeme d'inscription aux animations
-Comptage des soirées dans les statiqtiques de fréquentation

### 47.D1.25.87
**[Ajout]** - *(Stat)* - `Interface`  
-Ajout d'un systeme de gestion des périodes annuel (vacances, inventaire...)

### 47.E1.25.88
**[Ajout]** - *(Stat)* - `Interface`  
-Ajout de coloration dans le récap annuel (vert -> période de vacances / Rouge -> cas particulier "Inventaire/Fermeture/journée spécial..."

### 48.B1.25.89
**[Systeme]** - *(Anim)* - `Fonctionnalité`  
-Ajout de la connexion WS pour synchronisation entre les clients

### 48.E1.25.90
**[Fixé]** - *(Anim)* - `Code``  
-Le système d'animation etait encore sous AJAX, oublie de convertir en WS

### 01.E2.26.91
**[Fixé]** - *(Anim)* - `Code``  
-Les invités etait pas enregistré
-Ajouter un invité a quelqu'un le passais en "Attente" même si il y avais des places disponible

### 01.F2.26.92
**[Fixé]** - *(Stat)* - `Code``  
-Semaine 53 compté dans le rapport annuel
-Les jours de décembre de la semaine 53 etait pas compté dans la semaine 1 du rapport annuel

### 01.F3.26.93
**[Ajout]** - *(Anim)* - `Fonctionalité``  
-Possibilité de modifier le commentaire d'une inscription
-Possibilité d'ajouter un commentaire sur un invité

### 06.A1.26.94
**[Ajout]** - *(Surdim)* - `Interface`  
Ajout de la possibilité de faire des réservations multiple

### 06.E1.26.95
**[Fixé]** - *(Freq)* - `Correctif`  
-Oublie de l'appel de la liste des partenaire dans l'API

### 08.E1.26.96
**[Fixé]** - *(Anim)* - `Correctif`  
-Oublie de la gestion de mise en liste d'attente pour les inscription animation dans l'API