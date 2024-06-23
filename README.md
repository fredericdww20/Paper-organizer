# Document Management System

Bienvenue dans notre système de gestion de documents, développé avec Symfony. Cette application est conçue pour fournir une solution complète de gestion des documents personnels, permettant aux utilisateurs de créer, organiser, numériser et gérer leurs fichiers de manière sécurisée et intuitive.

## Fonctionnalités

### Gestion des Utilisateurs
- **Inscription et Connexion**
- **Rôles Utilisateurs**
- **Vérification des Emails**
- **Réinitialisation de Mot de Passe**

### Gestion des Dossiers et Documents
- **Création et Organisation**
- **Déplacement de Documents**
- **Suppression de Documents**
- **Téléchargement de Documents**
- **Upload avec Drag and Drop**
- **Aperçu des Documents**
- **Recherche**
- **Étiquettes et Catégorisation**
- **Partage de Documents**

### Sécurité et Permissions
- **Sécurisation des Données**
- **Firewall**

### Gestion des Fichiers
- **Stockage des Fichiers**
- **Support AWS S3**

### Pagination
- **Pagination**

### Interface Utilisateur
- **Interface Responsive**
- **Notifications et Alertes**
- **Tableau de Bord Utilisateur**
- **Drag-and-Drop Amélioré**

### Fonctionnalités de Numérisation
- **Numérisation de Documents**

### Optimisations Techniques
- **Chargement Asynchrone**
- **Optimisation de la Base de Données**
- **Gestion des Fichiers en Arrière-Plan**
- **Cache et Performance**

## Prérequis
- PHP 7.4 ou supérieur
- Composer
- MySQL ou autre base de données compatible
- Node.js et npm (pour gérer les dépendances frontend)

## Installation

```bash
# Cloner le dépôt
git clone https://github.com/votre-utilisateur/document-management-system.git

# Aller dans le répertoire du projet
cd document-management-system

# Installer les dépendances PHP
composer install

# Configurer la base de données
cp .env .env.local
# Modifier .env.local pour configurer DATABASE_URL

# Créer la base de données et les schémas
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate

# Installer les dépendances frontend
npm install

# Compiler les assets frontend
npm run dev

# Démarrer le serveur Symfony
symfony server:start
