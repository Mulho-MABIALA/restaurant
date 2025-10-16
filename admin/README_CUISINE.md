# 🍽️ Système de Gestion Cuisine

## Description

Le système de gestion cuisine permet à l'équipe de cuisine de recevoir, traiter et préparer les commandes en temps réel. Il communique automatiquement avec la page `commandes.php` pour notifier quand une commande est prête.

## Fichiers créés

1. **cuisine.php** - Interface principale de la cuisine
2. **api_cuisine_notifications.php** - API pour les notifications en temps réel
3. **js/cuisine_notifications.js** - Script JavaScript pour les notifications

## Fonctionnalités

### Page Cuisine (cuisine.php)

#### Vue d'ensemble
- Affichage en temps réel de toutes les commandes en attente
- Rafraîchissement automatique toutes les 5 secondes
- Vue en grille avec cartes pour chaque commande
- Statistiques en direct (commandes en attente / en préparation)

#### Actions disponibles

1. **Démarrer la préparation**
   - Passe une commande de "En cours" à "En préparation"
   - Animation visuelle pour distinguer les commandes en préparation

2. **Marquer comme prêt**
   - Passe une commande en statut "Prêt"
   - Envoie une notification automatique à la page commandes.php
   - Crée une notification dans la base de données

3. **Annuler une commande**
   - Permet d'annuler une commande avec une raison
   - Notifie l'administration

#### Informations affichées par commande

- Numéro de commande
- Temps écoulé depuis la création (indicateur d'urgence si > 15 min)
- Origine (Commande manuelle / Client)
- Nom du client
- Numéro de table (si applicable)
- Total de la commande
- Liste détaillée des plats avec quantités
- Statut actuel

### Système de Notifications

#### Sur la page commandes.php

Pour activer les notifications, ajoutez cette ligne dans `commandes.php` avant la fermeture de `</body>` :

```html
<script src="js/cuisine_notifications.js"></script>
```

#### Fonctionnalités des notifications

1. **Vérification automatique** - Toutes les 5 secondes
2. **Badge de notification** - Affiche le nombre de commandes prêtes
3. **Son d'alerte** - Joue un son quand une commande est prête
4. **Toast notification** - Notification visuelle en haut à droite
5. **Notification navigateur** - Si autorisée par l'utilisateur
6. **Modal détaillé** - Cliquer sur le badge affiche les détails

#### Types de notifications

- **Toast** : Notification temporaire en haut à droite (5 secondes)
- **Badge persistant** : Reste visible tant qu'il y a des commandes prêtes
- **Navigateur** : Notification système si l'utilisateur a donné l'autorisation
- **Son** : Alerte sonore configurable

## Workflow complet

### 1. Réception de commande

```
Client commande (commander.php)
    ↓
Base de données (table commandes, statut: "En cours")
    ↓
Cuisine.php affiche la commande
```

OU

```
Admin crée commande manuelle (commandes.php)
    ↓
Base de données (table commandes, statut: "En cours")
    ↓
Cuisine.php affiche la commande
```

### 2. Traitement en cuisine

```
Cuisine voit la commande ("En cours")
    ↓
Clique sur "Démarrer" → Statut: "En préparation"
    ↓
Animation visuelle (pulsation de la carte)
    ↓
Clique sur "Prêt" → Statut: "Prêt"
    ↓
Notification créée dans la base
    ↓
Commande disparaît de la vue cuisine
```

### 3. Notification du serveur

```
API vérifie les commandes "Prêt" (toutes les 5s)
    ↓
Nouvelle commande détectée
    ↓
Badge affiché sur commandes.php
    ↓
Son d'alerte joué
    ↓
Toast notification affichée
    ↓
Serveur clique sur le badge
    ↓
Modal avec détails de la commande
    ↓
Serveur clique "Marquer comme servie"
    ↓
vu_admin = 1 dans la base
    ↓
Badge disparaît
```

## Structure de la base de données

### Table commandes

```sql
-- Champs principaux utilisés
id INT PRIMARY KEY
nom_client VARCHAR
num_table VARCHAR
total DECIMAL
statut ENUM('En cours', 'En préparation', 'Prêt', 'Livrée', 'Annulée')
vu_admin TINYINT (0 = non vu, 1 = vu)
type_commande VARCHAR ('manuelle' ou NULL pour client)
created_at DATETIME
updated_at DATETIME
```

### Table commande_details

```sql
id INT PRIMARY KEY
commande_id INT
nom_plat VARCHAR
quantite INT
prix DECIMAL
```

### Table notifications

```sql
id INT PRIMARY KEY
message TEXT
type VARCHAR ('info', 'success', 'warning', 'danger')
date DATETIME
vue TINYINT (0 = non vue, 1 = vue)
```

## Configuration

### Intervalles de rafraîchissement

**cuisine.php** :
```javascript
const AUTO_REFRESH_INTERVAL = 5000; // 5 secondes
```

**cuisine_notifications.js** :
```javascript
this.checkInterval = 5000; // 5 secondes
```

### Seuil d'urgence

**cuisine.php** :
```javascript
const isUrgent = tempsEcoule > 15; // 15 minutes
```

## Installation

1. **Copier les fichiers** dans le répertoire `admin/`
   - cuisine.php
   - api_cuisine_notifications.php
   - js/cuisine_notifications.js

2. **Ajouter le script dans commandes.php**

Avant `</body>`, ajoutez :
```html
<script src="js/cuisine_notifications.js"></script>
```

3. **Ajouter un lien vers cuisine.php dans le menu**

Dans `sidebar.php` ou votre menu de navigation :
```html
<li>
    <a href="cuisine.php">
        <i class="fas fa-utensils"></i>
        Cuisine
    </a>
</li>
```

4. **Vérifier les permissions**

Assurez-vous que les utilisateurs de la cuisine ont accès à :
- cuisine.php (permission 'commandes')
- api_cuisine_notifications.php (connexion admin requise)

## Tests

### Test manuel

1. **Créer une commande** depuis commander.php ou commandes.php
2. **Ouvrir cuisine.php** - La commande doit apparaître
3. **Cliquer "Démarrer"** - Le statut passe à "En préparation"
4. **Ouvrir commandes.php** dans un autre onglet
5. **Cliquer "Prêt"** dans cuisine.php
6. **Vérifier** que la notification apparaît dans commandes.php

### Points de vérification

- ✅ La commande apparaît dans cuisine.php après création
- ✅ Le temps écoulé se met à jour
- ✅ Le statut change correctement
- ✅ La notification apparaît dans commandes.php
- ✅ Le son joue (si autorisé)
- ✅ Le badge affiche le bon nombre
- ✅ Le modal s'ouvre avec les détails
- ✅ Marquer comme servi fonctionne

## Dépannage

### Les commandes n'apparaissent pas

1. Vérifier la connexion à la base de données
2. Vérifier que le statut est "En cours" ou "En préparation"
3. Regarder la console JavaScript (F12)

### Les notifications ne fonctionnent pas

1. Vérifier que le script est bien inclus dans commandes.php
2. Vérifier la console JavaScript pour les erreurs
3. Autoriser les notifications navigateur si nécessaire
4. Vérifier que l'API répond : `api_cuisine_notifications.php?action=count_commandes_pretes`

### Le rafraîchissement ne fonctionne pas

1. Vérifier la console JavaScript
2. Vérifier que les requêtes AJAX aboutissent
3. Augmenter l'intervalle de rafraîchissement si le serveur est lent

## Améliorations futures possibles

- [ ] WebSockets pour des notifications instantanées
- [ ] Impression automatique des tickets de cuisine
- [ ] Gestion des priorités de commandes
- [ ] Statistiques de temps de préparation
- [ ] Intégration avec un écran de cuisine dédié
- [ ] Mode sombre pour la cuisine
- [ ] Support multi-langues
- [ ] Application mobile dédiée

## Support

Pour toute question ou problème :
1. Vérifier ce README
2. Consulter les logs de la console navigateur
3. Vérifier les logs PHP du serveur
4. Contacter l'administrateur système

## Licence

Ce système fait partie du projet de gestion de restaurant.
Tous droits réservés.
