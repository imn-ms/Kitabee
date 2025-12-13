# Kitabee

**Kitabee** est une plateforme web communautaire dédiée aux passionnés de lecture.  
Elle permet de rechercher des livres, gérer une bibliothèque personnelle, interagir avec des amis, rejoindre des clubs de lecture et consulter des actualités littéraires.

> Projet réalisé dans un cadre pédagogique (développement web avancé).

---

## 🚀 Fonctionnalités

### 👤 Compte & Profil
- Inscription / Connexion
- Dashboard utilisateur
- Gestion avatar (stockage en BLOB puis choix d’avatar)
- Cookies (ex : première visite / préférences)

### 🤝 Social
- Système d’amis (ajout / gestion)
- Notifications (selon fonctionnalités)

### 👥 Clubs de lecture
- Création / consultation de clubs
- Gestion des membres
- Discussions / messages dans un club
- Association de livres à un club

### 📖 Livres
- Recherche et fiche détaillée via **Google Books API**
- Bibliothèque personnelle
- Wishlist
- Avis, notes et recommandations (selon implémentation)

### 📰 Actualités littéraires
- Récupération d’articles via **The Guardian API**

### 🌍 Traduction
- Traduction de certains contenus via **Google Translate API**

### ✉️ Contact
- Formulaire de contact

### 🏷️ Badges
- Système de badges (attribution / affichage)

### 🔐 Sécurité
- Protection des formulaires avec **Google reCAPTCHA**
- Requêtes préparées (PDO)
- Gestion des sessions

### 🌐 SEO & Divers
- Référencement (balises, structure, bonnes pratiques)
- `robots.txt`
- Carte avec Leaflet 
- Cron 

---

## 🔌 APIs & Services utilisés

### APIs externes
- **Google Books API** : recherche et affichage des informations de livres (titre, auteur, couverture, description, etc.)
- **The Guardian API** : récupération d’actualités / articles culturels
- **Google reCAPTCHA** : protection anti-bot sur les formulaires
- **Google Translate API** : traduction de contenus (ex : description, texte, etc.)

### Services internes (côté serveur)
- **Base de données MySQL** : stockage utilisateurs, amis, clubs, messages, badges, wishlist, bibliothèque, etc.
- **PHP (PDO, Sessions, Managers)** : logique métier, sécurité, échanges client/serveur

---

## 🧩 Répartition client / serveur

| Élément | Type | Côté |
|---|---|---|
| Google Books API | Externe | Serveur |
| The Guardian API | Externe | Serveur |
| Google reCAPTCHA | Externe | Client + vérification Serveur |
| Google Translate API | Externe | Client |
| MySQL | Interne | Serveur |
| PHP / Managers | Interne | Serveur |
| JS (AJAX) | Interne | Client |

---

## 🗄️ Base de données

La base de données stocke uniquement les données internes à la plateforme (utilisateurs, relations, clubs…).  
📌 **Aucune table `book`** : les livres proviennent de l’API Google Books.

### Tables principales 
- `users`
- `book_clubs`
- `badges`
- `notifications`

### Tables d’association
- `user_library`
- `user_wishlist`
- `user_friends`
- `user_badges`
- `book_club_members`
- `book_club_books`
- `book_club_messages`

---

## 🛠️ Technologies

- **Front-end** : HTML, CSS, JavaScript
- **Back-end** : PHP 8+, MySQL, PDO
- **Outils** : Git/GitHub, WordPress (rapport), PhpDoc (documentation)

---
## 👩‍💻 Répartition des tâches

### Imane
Imane a été principalement en charge des fonctionnalités liées à l’authentification, à la gestion des utilisateurs et aux interactions sociales.  
Ses responsabilités incluent :
- Développement du système de connexion et d’inscription
- Mise en place de la connexion à la base de données
- Gestion des avatars utilisateurs (stockage en BLOB)
- Gestion des cookies
- Développement des clubs de lecture
- Implémentation du système d’amis
- Intégration de Google reCAPTCHA
- Développement du dashboard utilisateur
- Optimisation du référencement (SEO)
- Rédaction de la documentation technique avec PhpDoc

---

### Odessa
Odessa a été principalement en charge de l’intégration des APIs externes et des fonctionnalités liées aux livres et aux contenus.  
Ses responsabilités incluent :
- Intégration des APIs externes :
  - API d’actualités littéraires
  - API de livres
  - API de traduction
- Développement de la bibliothèque personnelle
- Développement de la wishlist
- Gestion des avis et des notes
- Mise en place du système de recommandations
- Développement du formulaire de contact
- Gestion du système de badges
- Configuration du fichier `robots.txt`
- Intégration de cartes interactives avec Leaflet
- Mise en place de tâches planifiées (cron)
- Système de choix d’avatar
- Validations
- Montage vidéo de présentation du projet

---

### Travail en collaboration (Imane & Odessa)
Certaines parties du projet ont été réalisées conjointement :
- Développement et intégration du site WordPress servant de rapport
- Conception et gestion du design CSS
- Conception, modélisation et gestion de la base de données

## ⚙️ Installation (local)

1. Cloner le projet :
```bash
git clone https://github.com/<ton-compte>/<ton-repo>.git
