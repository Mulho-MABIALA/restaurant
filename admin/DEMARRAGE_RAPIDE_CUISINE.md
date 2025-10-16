# 🚀 Démarrage Rapide - Système Cuisine

## Installation en 5 minutes

### Étape 1 : Vérifier les fichiers (30 secondes)

Assurez-vous que tous ces fichiers existent dans `admin/` :

```
admin/
├── cuisine.php                          ✅ Interface cuisine
├── api_cuisine_notifications.php        ✅ API notifications
├── js/
│   └── cuisine_notifications.js         ✅ Script JS
├── sql/
│   └── cuisine_setup.sql                ✅ Script SQL
├── README_CUISINE.md                    📖 Documentation
├── INTEGRATION_NOTIFICATIONS.md         📖 Guide intégration
├── test_cuisine_system.php              🧪 Tests
└── test_cuisine_api.php                 🧪 API tests
```

### Étape 2 : Exécuter le SQL (1 minute)

**Option A : Via phpMyAdmin**
1. Ouvrez phpMyAdmin
2. Sélectionnez votre base `restaurant`
3. Onglet SQL
4. Copiez/collez le contenu de `sql/cuisine_setup.sql`
5. Cliquez sur "Exécuter"

**Option B : Via ligne de commande**
```bash
mysql -u root -p restaurant < admin/sql/cuisine_setup.sql
```

### Étape 3 : Activer les notifications (30 secondes)

Ouvrez `admin/commandes.php` et ajoutez avant `</body>` :

```html
<!-- Système de notifications cuisine -->
<script src="js/cuisine_notifications.js"></script>
```

### Étape 4 : Tester (2 minutes)

1. Ouvrez : `http://localhost/restaurant/admin/test_cuisine_system.php`
2. Cliquez sur "Tous les tests"
3. Vérifiez que tous les tests sont ✅ verts

### Étape 5 : Utiliser ! (1 minute)

1. Ouvrez `admin/cuisine.php` → Interface cuisine
2. Ouvrez `admin/commandes.php` → Gestion commandes
3. Créez une commande test
4. Dans cuisine.php : Démarrer → Prêt
5. Dans commandes.php : Notification apparaît ! 🎉

---

## Utilisation Quotidienne

### Pour la Cuisine

**Accéder à l'interface :**
```
http://localhost/restaurant/admin/cuisine.php
```

**Workflow :**
1. Une commande apparaît (en attente)
2. Cliquez "Démarrer" quand vous commencez
3. Préparez la commande
4. Cliquez "Prêt" quand c'est fini
5. La commande disparaît et notifie le serveur

**Indicateurs :**
- 🟡 Carte jaune = En attente
- 🔵 Carte bleue (pulsante) = En préparation
- ⚠️ Temps rouge = Plus de 15 minutes

### Pour les Serveurs

**Page commandes.php automatiquement notifiée :**

1. **Badge apparaît** en haut à droite : "X commande(s) prête(s)"
2. **Son joue** (si activé)
3. **Toast notification** s'affiche temporairement
4. **Cliquez sur le badge** → Modal avec détails
5. **"Marquer comme servie"** → Badge disparaît

---

## Scénarios Courants

### Scénario 1 : Commande Client Normal

```
Client scanne QR code
    ↓
Commander.php (géolocalisation OK)
    ↓
Commande créée (statut: "En cours")
    ↓
Apparaît dans cuisine.php automatiquement
    ↓
Cuisine clique "Démarrer" → "En préparation"
    ↓
Cuisine clique "Prêt" → "Prêt"
    ↓
Serveur reçoit notification
    ↓
Serveur clique "Marquer comme servie"
    ↓
Terminé !
```

### Scénario 2 : Commande Manuelle (Table)

```
Serveur dans commandes.php
    ↓
Clique "Nouvelle commande manuelle"
    ↓
Remplit formulaire (table, plats)
    ↓
Valide → Commande créée
    ↓
Apparaît immédiatement dans cuisine.php
    ↓
[Même workflow qu'une commande client]
```

### Scénario 3 : Problème avec une Commande

```
Cuisine voit commande problématique
    ↓
Clique bouton "Annuler" (❌)
    ↓
Entre raison d'annulation
    ↓
Valide → Statut "Annulée"
    ↓
Notification envoyée à l'admin
    ↓
Admin gère avec client
```

---

## Troubleshooting Rapide

### Problème : Commandes n'apparaissent pas dans cuisine.php

**Solution :**
1. Vérifiez que le statut est "En cours" ou "En préparation"
2. F12 → Console → Vérifiez les erreurs
3. Rafraîchir la page (F5)

### Problème : Notifications ne fonctionnent pas

**Solution :**
1. Vérifiez que le script est ajouté dans commandes.php
2. F12 → Console → Cherchez "🔔 Système de notifications cuisine activé"
3. Testez l'API : `api_cuisine_notifications.php?action=count_commandes_pretes`

### Problème : "Erreur de connexion"

**Solution :**
1. Vérifiez que vous êtes connecté en admin
2. Vérifiez config.php
3. Vérifiez que la base de données fonctionne

---

## Personnalisation Rapide

### Changer l'intervalle de rafraîchissement

**cuisine.php** (ligne ~562) :
```javascript
const AUTO_REFRESH_INTERVAL = 5000; // Changer ici (en millisecondes)
```

**cuisine_notifications.js** (ligne ~9) :
```javascript
this.checkInterval = 5000; // Changer ici
```

### Changer le seuil "urgent"

**cuisine.php** (ligne ~660) :
```javascript
const isUrgent = tempsEcoule > 15; // Changer 15 par votre valeur
```

### Désactiver le son

**cuisine_notifications.js** (ligne ~75) :
```javascript
playNotificationSound() {
    return; // Ajouter cette ligne pour désactiver
    // ...
}
```

---

## Points Important à Retenir

### ✅ À FAIRE

- ✅ Tester le système avant mise en production
- ✅ Former l'équipe cuisine et service
- ✅ Garder la page cuisine.php ouverte en permanence
- ✅ Autoriser les notifications navigateur pour les alertes
- ✅ Vérifier régulièrement que le système fonctionne

### ❌ À NE PAS FAIRE

- ❌ Ne pas fermer cuisine.php pendant le service
- ❌ Ne pas modifier les statuts manuellement dans la BDD
- ❌ Ne pas supprimer les colonnes de la table commandes
- ❌ Ne pas changer l'API sans tester

---

## Configuration Multi-Écrans

### Cuisine avec plusieurs écrans

Vous pouvez ouvrir `cuisine.php` sur plusieurs appareils :

1. **Écran principal cuisine** : `cuisine.php`
2. **Tablette chef** : `cuisine.php`
3. **Téléphone secours** : `cuisine.php`

Tous se synchroniseront automatiquement via la base de données.

### Service avec plusieurs serveurs

Chaque serveur peut avoir `commandes.php` ouvert :

- Tous reçoivent les notifications
- Chacun peut marquer "servi" indépendamment
- Pas de conflit, système géré par `vu_admin`

---

## Maintenance

### Nettoyage hebdomadaire

```sql
-- Supprimer les anciennes notifications (> 30 jours)
DELETE FROM notifications WHERE date < DATE_SUB(NOW(), INTERVAL 30 DAY);
```

### Vérification mensuelle

1. Testez le système avec `test_cuisine_system.php`
2. Vérifiez les performances (temps de chargement)
3. Nettoyez les vieilles notifications
4. Sauvegardez la base de données

---

## Support et Aide

### Où trouver de l'aide ?

1. **README_CUISINE.md** → Documentation complète
2. **INTEGRATION_NOTIFICATIONS.md** → Guide intégration
3. **test_cuisine_system.php** → Tests et diagnostics
4. **Console JavaScript (F12)** → Erreurs et logs

### Logs utiles

**Console JavaScript :**
```
🔔 Système de notifications cuisine activé
✅ Surveillance des commandes démarrée
🍽️ Nouvelle commande prête !
```

**Console PHP :**
Vérifiez les logs Apache/PHP pour les erreurs serveur.

---

## Checklist Mise en Production

Avant d'utiliser en production :

- [ ] Tous les fichiers sont en place
- [ ] SQL exécuté avec succès
- [ ] Tests passent (test_cuisine_system.php)
- [ ] Script ajouté dans commandes.php
- [ ] Testéavec une vraie commande
- [ ] Équipe formée sur le workflow
- [ ] Notifications fonctionnent
- [ ] Son fonctionne (optionnel)
- [ ] Plusieurs écrans testés
- [ ] Sauvegarde BDD effectuée

---

## Prochaines Améliorations Possibles

Idées pour l'avenir :

- 🔄 WebSockets pour notifications instantanées (< 1s)
- 🖨️ Impression automatique tickets cuisine
- 📊 Statistiques temps de préparation
- 📱 App mobile dédiée cuisine
- 🎨 Thème sombre pour la nuit
- 🔊 Alertes vocales ("Nouvelle commande table 5")
- 🏷️ Système de tags/catégories plats
- ⏱️ Timer par plat (différents temps cuisson)

---

**Version :** 1.0
**Date :** Janvier 2025
**Auteur :** Système de Gestion Restaurant

---

## Contact Rapide

**En cas de problème urgent :**

1. Vérifier `test_cuisine_system.php`
2. Consulter console (F12)
3. Redémarrer le navigateur
4. Vérifier connexion BDD
5. Contacter support technique

**Tout fonctionne ? Bon service ! 🍽️✨**
