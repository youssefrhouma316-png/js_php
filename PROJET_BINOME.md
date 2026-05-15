# WorkPods - Synthese pour la demo

## Cahier des charges couvert

- Site dynamique en PHP, JavaScript et MySQL.
- Inscription, connexion et consultation du compte utilisateur.
- Reservation d'un service : pods de coworking.
- Administration des clients.
- Administration des pods, avec upload obligatoire d'une image.
- Administration des reservations.
- Statistiques dans le tableau de bord.
- Projet binome : au moins 2 entites gerees hors `users`.

## Entites principales

1. `users` : clients et administrateurs.
2. `pods` : services/espace a reserver, avec image uploadable.
3. `reservations` : reservations des utilisateurs.
4. `contact_messages` : demandes envoyees depuis la page contact.

## Parcours utilisateur

1. Creer un compte avec informations de profil et photo.
2. Se connecter.
3. Consulter et modifier son compte.
4. Choisir un pod disponible.
5. Reserver un creneau.
6. Consulter ses reservations.
7. Contacter WorkPods via la page contact et Google Maps.

## Parcours administrateur

Identifiants de demo :

- Login : `admin`
- Mot de passe : `123`

Fonctionnalites :

- Voir les statistiques generales.
- Gerer les clients.
- Gerer les pods : ajouter, modifier, supprimer.
- Gerer les reservations : changer le statut.
- Gerer les messages de contact.

## Repartition conseillee pour le binome

Membre 1 :

- Authentification utilisateur.
- Profil utilisateur.
- Reservation et controle des disponibilites.

Membre 2 :

- Dashboard administrateur.
- Gestion des pods avec upload.
- Gestion clients, reservations et messages.

## Points forts a montrer

- Upload d'image obligatoire pour les pods.
- Photo de profil a l'inscription.
- Controle serveur contre les doubles reservations.
- Statistiques et calendrier admin.
- Contact avec stockage en base et carte Google Maps.
