# 📚 Index - Système de Gestion Cuisine

## Vue d'ensemble

Le système de gestion cuisine est un module complet qui permet la communication en temps réel entre la cuisine, les serveurs et le système de commandes du restaurant.

---

## 📁 Structure des Fichiers

### Fichiers Principaux

| Fichier | Description | Priorité |
|---------|-------------|----------|
| [cuisine.php](cuisine.php) | Interface principale de la cuisine | ⭐⭐⭐ |
| [api_cuisine_notifications.php](api_cuisine_notifications.php) | API REST pour les notifications | ⭐⭐⭐ |
| [js/cuisine_notifications.js](js/cuisine_notifications.js) | Script JavaScript notifications | ⭐⭐⭐ |
| [sql/cuisine_setup.sql](sql/cuisine_setup.sql) | Script SQL d'installation | ⭐⭐⭐ |

### Documentation

| Fichier | Contenu | Pour qui ? |
|---------|---------|-----------|
| [DEMARRAGE_RAPIDE_CUISINE.md](DEMARRAGE_RAPIDE_CUISINE.md) | Installation en 5 minutes | 🚀 Débutants |
| [README_CUISINE.md](README_CUISINE.md) | Documentation complète | 📖 Tous |
| [INTEGRATION_NOTIFICATIONS.md](INTEGRATION_NOTIFICATIONS.md) | Guide d'intégration | 🔧 Développeurs |
| [INDEX_CUISINE.md](INDEX_CUISINE.md) | Ce fichier - Index général | 📚 Navigation |

### Fichiers de Test

| Fichier | Utilité |
|---------|---------|
| [test_cuisine_system.php](test_cuisine_system.php) | Interface de test visuelle |
| [test_cuisine_api.php](test_cuisine_api.php) | API pour les tests |

---

## 🎯 Par où commencer ?

### Je veux installer le système rapidement
👉 Lisez [DEMARRAGE_RAPIDE_CUISINE.md](DEMARRAGE_RAPIDE_CUISINE.md)

### Je veux comprendre comment ça fonctionne
👉 Lisez [README_CUISINE.md](README_CUISINE.md)

### Je veux intégrer les notifications
👉 Lisez [INTEGRATION_NOTIFICATIONS.md](INTEGRATION_NOTIFICATIONS.md)

### Je veux tester que tout fonctionne
👉 Ouvrez [test_cuisine_system.php](test_cuisine_system.php) dans votre navigateur

---

## 🔄 Workflow Complet

### 1️⃣ Création de Commande

**Source A : Client (commander.php)**
```
Scan QR code → Géolocalisation → Commande → BDD
```

**Source B : Manuelle (commandes.php)**
```
Admin crée → Formulaire → BDD
```

### 2️⃣ Réception en Cuisine

```
BDD → cuisine.php (refresh auto 5s)
Commande apparaît avec statut "En cours"
```

### 3️⃣ Traitement

```
Cuisine clique "Démarrer" → Statut: "En préparation"
    ↓
Cuisine prépare le plat
    ↓
Cuisine clique "Prêt" → Statut: "Prêt"
```

### 4️⃣ Notification Serveur

```
API détecte nouvelle commande "Prêt"
    ↓
Notification dans commandes.php
    ↓
Badge + Son + Toast
    ↓
Serveur clique "Marquer comme servie"
    ↓
vu_admin = 1
```

---

## 🎨 Fonctionnalités Clés

### Pour la Cuisine (cuisine.php)

✅ Affichage temps réel des commandes
✅ Rafraîchissement automatique (5s)
✅ Statuts : En cours / En préparation
✅ Indicateur de temps écoulé
✅ Distinction commandes manuelles/clients
✅ Boutons d'action rapides
✅ Vue en grille responsive
✅ Animation pour commandes en préparation
✅ Statistiques en temps réel

### Pour les Serveurs (commandes.php + notifications)

✅ Badge de notification persistant
✅ Compteur de commandes prêtes
✅ Notification sonore
✅ Toast notifications temporaires
✅ Notifications navigateur (si autorisées)
✅ Modal avec détails des commandes
✅ Action "Marquer comme servie"
✅ Vérification automatique (5s)

### Pour l'Administration

✅ API REST complète
✅ Logs et historique
✅ Tests automatisés
✅ Base de données optimisée (index)
✅ Triggers automatiques
✅ Vues SQL pré-configurées
✅ Nettoyage automatique des vieilles notifs

---

## 🗄️ Base de Données

### Tables Utilisées

| Table | Rôle |
|-------|------|
| `commandes` | Commandes principales |
| `commande_details` | Détails des plats |
| `notifications` | Notifications système |

### Colonnes Importantes

**Table `commandes` :**
- `statut` : En cours / En préparation / Prêt / Livrée / Annulée
- `vu_admin` : 0 (non vu) / 1 (vu) → Pour les notifications
- `type_commande` : NULL (client) / 'manuelle' (admin)
- `created_at` : Date création
- `updated_at` : Date dernière modification

### Vues SQL

- `v_commandes_cuisine` : Commandes pour la cuisine
- `v_commandes_pretes` : Commandes prêtes non vues

### Triggers

- `before_commandes_update` : Met à jour `updated_at` et gère `vu_admin`

### Index (pour performance)

- `idx_commandes_statut`
- `idx_commandes_vu_admin`
- `idx_cuisine_query`
- `idx_notifications_query`

---

## 🔌 API Endpoints

### api_cuisine_notifications.php

| Endpoint | Méthode | Description |
|----------|---------|-------------|
| `?action=get_commandes_pretes` | GET | Liste des commandes prêtes |
| `?action=count_commandes_pretes` | GET | Nombre de commandes prêtes |
| `?action=get_notifications` | GET | Notifications non lues |
| `action=marquer_vu` | POST | Marquer commande comme vue |

### cuisine.php (API interne)

| Action | Méthode | Description |
|--------|---------|-------------|
| `get_commandes_cuisine` | POST | Commandes en cours/préparation |
| `demarrer_preparation` | POST | Passer en préparation |
| `marquer_pret` | POST | Marquer comme prêt |
| `annuler_commande` | POST | Annuler une commande |

---

## 🛠️ Configuration

### Paramètres Modifiables

**Intervalle de rafraîchissement (cuisine.php) :**
```javascript
const AUTO_REFRESH_INTERVAL = 5000; // millisecondes
```

**Intervalle de vérification (cuisine_notifications.js) :**
```javascript
this.checkInterval = 5000; // millisecondes
```

**Seuil d'urgence (cuisine.php) :**
```javascript
const isUrgent = tempsEcoule > 15; // minutes
```

**Son de notification :**
```javascript
this.notificationSound = new Audio('votre-son.mp3');
```

---

## 📊 Statistiques et Monitoring

### Métriques Disponibles

- Temps moyen de préparation
- Nombre de commandes par statut
- Commandes en retard (> 15 min)
- Taux d'annulation
- Performance cuisine (temps réel)

### Où voir les stats ?

- Dans cuisine.php : Stats en haut (En attente / En préparation)
- Dans la BDD : Requêtes SQL sur la table commandes
- Logs : Console JavaScript (F12)

---

## 🧪 Tests

### Tests Manuels

1. Ouvrir [test_cuisine_system.php](test_cuisine_system.php)
2. Cliquer "Tous les tests"
3. Vérifier que tout est ✅ vert

### Tests Automatiques

Chaque test vérifie :
- ✅ Connexion BDD
- ✅ Structure tables
- ✅ API fonctionnelle
- ✅ Fichiers présents
- ✅ Notifications actives

### Créer une Commande Test

Bouton dans test_cuisine_system.php ou :

```sql
INSERT INTO commandes (nom_client, num_table, total, statut, created_at)
VALUES ('Test Client', '99', 15000, 'En cours', NOW());
```

---

## 🚨 Dépannage

### Problèmes Courants

| Problème | Solution |
|----------|----------|
| Commandes n'apparaissent pas | Vérifier statut = "En cours" ou "En préparation" |
| Notifications ne fonctionnent pas | Vérifier script ajouté dans commandes.php |
| Erreur 404 sur JS | Vérifier chemin : `js/cuisine_notifications.js` |
| API ne répond pas | Vérifier connexion admin + BDD |
| Son ne joue pas | Cliquer sur la page pour autoriser |

### Logs à Consulter

**Console JavaScript (F12) :**
```
🔔 Système de notifications cuisine activé
✅ Surveillance des commandes démarrée
```

**Console PHP :**
Vérifier logs Apache/PHP pour erreurs serveur

---

## 📱 Compatibilité

### Navigateurs Supportés

- ✅ Chrome/Edge (Recommandé)
- ✅ Firefox
- ✅ Safari
- ⚠️ Internet Explorer (non testé)

### Appareils

- ✅ PC/Mac Desktop
- ✅ Tablettes (iPad, Android)
- ✅ Smartphones (responsive)

### Résolution Recommandée

- Minimum : 1024x768
- Optimal : 1920x1080

---

## 🔐 Sécurité

### Authentification

- ✅ Session admin requise
- ✅ Vérification permissions
- ✅ Protection CSRF (à implémenter si nécessaire)

### Données

- ✅ Échappement HTML (`htmlspecialchars`)
- ✅ Requêtes préparées (PDO)
- ✅ Validation côté serveur

---

## 📈 Performance

### Optimisations Appliquées

- ✅ Index sur colonnes fréquemment requêtées
- ✅ Vues SQL pour requêtes complexes
- ✅ Rafraîchissement intelligent (silencieux)
- ✅ Pas de polling excessif (5s acceptable)

### Charge Serveur

- Requêtes par minute : ~24 (toutes les 5s × 2 pages)
- Impact : Faible (requêtes simples avec index)
- Scalabilité : OK jusqu'à ~50 commandes simultanées

### Améliorations Futures

- 🔄 WebSockets pour notifications instantanées
- 📦 Cache Redis pour réduire requêtes BDD
- ⚡ CDN pour fichiers statiques

---

## 🎓 Formation

### Pour la Cuisine

**Temps de formation : 10 minutes**

1. Comprendre l'interface
2. Utiliser "Démarrer" et "Prêt"
3. Gérer les annulations
4. Interpréter les indicateurs de temps

### Pour les Serveurs

**Temps de formation : 5 minutes**

1. Reconnaître le badge de notification
2. Cliquer pour voir les détails
3. Marquer comme servie
4. Autoriser le son (optionnel)

### Pour les Admins

**Temps de formation : 30 minutes**

1. Installation complète
2. Configuration avancée
3. Maintenance et nettoyage
4. Dépannage courant

---

## 📞 Support

### Niveaux de Support

**Niveau 1 : Auto-assistance**
- Consulter cette documentation
- Utiliser test_cuisine_system.php
- Vérifier console (F12)

**Niveau 2 : Technique**
- Vérifier logs serveur
- Tester API manuellement
- Réinstaller si nécessaire

**Niveau 3 : Développeur**
- Modifier code source
- Optimiser performances
- Ajouter fonctionnalités

---

## 🎯 Feuille de Route

### Version Actuelle : 1.0 ✅

- ✅ Interface cuisine complète
- ✅ Notifications temps réel
- ✅ API REST fonctionnelle
- ✅ Tests automatisés
- ✅ Documentation complète

### Version 1.1 (Prévue)

- 🔄 WebSockets
- 📊 Statistiques avancées
- 🖨️ Impression tickets
- 📱 PWA (Progressive Web App)

### Version 2.0 (Future)

- 🎨 Thème sombre
- 🔊 Alertes vocales
- 🌍 Multi-langues
- 📲 App mobile native

---

## 📜 Licence et Crédits

**Projet :** Système de Gestion Restaurant
**Module :** Gestion Cuisine v1.0
**Date :** Janvier 2025
**Technologies :** PHP, JavaScript, MySQL, Bootstrap, FontAwesome

---

## 🔗 Liens Rapides

### Documentation
- [📚 Index (ce fichier)](INDEX_CUISINE.md)
- [🚀 Démarrage Rapide](DEMARRAGE_RAPIDE_CUISINE.md)
- [📖 README Complet](README_CUISINE.md)
- [🔧 Guide Intégration](INTEGRATION_NOTIFICATIONS.md)

### Fichiers Système
- [🍽️ Interface Cuisine](cuisine.php)
- [📡 API Notifications](api_cuisine_notifications.php)
- [⚙️ Script JS](js/cuisine_notifications.js)
- [🗄️ SQL Setup](sql/cuisine_setup.sql)

### Tests
- [🧪 Tests Système](test_cuisine_system.php)
- [🔌 Tests API](test_cuisine_api.php)

---

**Dernière mise à jour :** Janvier 2025
**Version de l'index :** 1.0

---

## ✨ Remerciements

Merci d'utiliser le système de gestion cuisine !

Pour toute question ou suggestion d'amélioration, consultez la documentation ou contactez le support technique.

**Bon service ! 🍽️✨**
