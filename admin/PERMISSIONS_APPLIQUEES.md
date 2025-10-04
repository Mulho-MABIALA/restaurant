# ✅ Système de Permissions Appliqué

**Date**: 04 octobre 2025
**Objectif**: Sécuriser toutes les pages admin avec le système de permissions

---

## 📊 Pages Modifiées (Permissions Appliquées)

### ✅ Pages Principales - TERMINÉ

| Fichier | Slug Permission | Statut |
|---------|----------------|--------|
| **dashboard.php** | `dashboard` | ✅ Modifié |
| **gestion_plats.php** | `gestion_plats` | ✅ Modifié |
| **reservations.php** | `reservations` | ✅ Déjà fait |
| **commandes.php** | `commandes` | ✅ Modifié |
| **gestion_employe.php** | `gestion_employes` | ✅ Modifié |
| **admin_gestion.php** | `admin_gestion` | ✅ Modifié |
| **gallery.php** | `gallery` | ✅ Modifié |
| **admin_evenements.php** | `admin_evenements` | ✅ Modifié |

---

## 🔧 Code Standard Appliqué

Sur chaque page, le code suivant a été ajouté **en haut du fichier** :

```php
<?php
session_start();
require_once '../config.php';
require_once './permissions.php';

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Vérifier que admin_id existe
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// Vérifier les permissions (SLUG adapté selon la page)
requireAccess($conn, $_SESSION['admin_id'], 'page_slug_ici');
```

---

## 📝 Pages à Modifier (TODO)

### Pages RH et Employés

| Fichier | Slug Suggéré | Priorité |
|---------|--------------|----------|
| `gestion_postes.php` | `gestion_postes` | 🔴 HAUTE |
| `gestion_paie.php` | `gestion_paie` | 🔴 HAUTE |
| `badgeuse.php` | `badgeuse` | 🟡 MOYENNE |
| `presence.php` | `presence` | 🟡 MOYENNE |
| `planification_horaires.php` | `planification_horaires` | 🟡 MOYENNE |
| `horaires.php` | `horaires` | 🟡 MOYENNE |
| `generer_bulletin.php` | `gestion_paie` | 🟡 MOYENNE |

### Pages Newsletter

| Fichier | Slug Suggéré | Priorité |
|---------|--------------|----------|
| `admin_newsletter.php` | `admin_newsletter` | 🔴 HAUTE |
| `admin_newsletter_compose.php` | `admin_newsletter` | 🔴 HAUTE |
| `admin_newsletter_campaigns.php` | `admin_newsletter` | 🟡 MOYENNE |
| `admin_newsletter_analytics.php` | `admin_newsletter` | 🟡 MOYENNE |

### Pages Stocks et Catégories

| Fichier | Slug Suggéré | Priorité |
|---------|--------------|----------|
| `gestion_stock.php` | `gestion_stocks` | 🔴 HAUTE |
| `ajouter_stock.php` | `gestion_stocks` | 🟡 MOYENNE |
| `categories_plats.php` | `gestion_categories` | 🟡 MOYENNE |

### Pages Configuration

| Fichier | Slug Suggéré | Priorité |
|---------|--------------|----------|
| `settings.php` | `settings` | 🔴 HAUTE |
| `config_security.php` | `config_security` | 🔴 HAUTE |
| `gestion_droits.php` | `gestion_droits` | 🔴 HAUTE |

### Pages Contenu

| Fichier | Slug Suggéré | Priorité |
|---------|--------------|----------|
| `gestion_contenu.php` | `gestion_contenu` | 🟡 MOYENNE |
| `avis_admin.php` | `avis_admin` | 🟢 BASSE |
| `statistiques.php` | `statistiques` | 🟡 MOYENNE |

---

## 🚫 Pages à NE PAS Modifier

Ces pages ne nécessitent **PAS** de permissions spécifiques (accès libre pour tous les admins connectés) :

- `login.php` - Connexion
- `logout.php` - Déconnexion
- `access_denied.php` - Page d'erreur
- `notifications.php` - Notifications générales
- `profile.php` - Profil personnel
- `sidebar.php` - Composant sidebar

---

## 📚 Fichiers de Support Créés

### 1. `PAGE_PERMISSIONS_MAP.php`
Mapping complet de toutes les pages vers leurs slugs de permissions.

### 2. `apply_permissions_template.txt`
Template de code à copier/coller pour ajouter rapidement les permissions sur une page.

### 3. `debug_permissions.php`
Outil de diagnostic pour vérifier :
- Votre rôle actuel
- Vos permissions
- L'accès aux pages
- État de la table `admin_pages`

### 4. `fix_permissions.sql`
Script SQL pour initialiser la table `admin_pages` avec toutes les pages principales.

---

## 🔐 Comment Fonctionne le Système

### Super Admin
- ✅ **Accès automatique à TOUTES les pages**
- ✅ Pas besoin d'entrées dans `admin_permissions`
- ✅ Vérifié via `role = 'superadmin'` dans la table `admin`

### Admin Normal
- ⚠️ **Accès selon les permissions dans `admin_permissions`**
- ⚠️ Doit avoir une ligne avec `can_view = 1` pour chaque page
- ⚠️ Géré depuis `admin_gestion.php` → Gestion des droits

### Fonction `canAccess()`
```php
function canAccess($conn, $adminId, $pageSlug) {
    // 1. Vérifier si superadmin → return true
    // 2. Sinon, vérifier dans admin_permissions
    // 3. Return true si can_view = 1
}
```

### Fonction `requireAccess()`
```php
function requireAccess($conn, $adminId, $pageSlug) {
    if (!canAccess($conn, $adminId, $pageSlug)) {
        header('Location: access_denied.php');
        exit;
    }
}
```

---

## 🛠️ Instructions pour Ajouter des Permissions

### Étape 1: Identifier le Fichier
Exemple : `gestion_stock.php`

### Étape 2: Choisir le Slug
Consultez `PAGE_PERMISSIONS_MAP.php` ou créez-en un :
```
gestion_stock.php → 'gestion_stocks'
```

### Étape 3: Ajouter le Code
En haut du fichier, après `<?php` :

```php
session_start();
require_once '../config.php';
require_once './permissions.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

requireAccess($conn, $_SESSION['admin_id'], 'gestion_stocks');
```

### Étape 4: Ajouter dans admin_pages
Exécuter dans phpMyAdmin :

```sql
INSERT INTO admin_pages (page_name, page_slug, description, is_active)
VALUES ('Gestion des Stocks', 'gestion_stocks', 'Gérer les stocks et inventaire', 1);
```

---

## ✅ Checklist de Vérification

Après avoir modifié une page :

- [ ] `session_start()` est appelé en premier
- [ ] `require_once './permissions.php'` est présent
- [ ] Vérification de `$_SESSION['admin_logged_in']`
- [ ] Vérification de `$_SESSION['admin_id']`
- [ ] `requireAccess()` avec le bon slug
- [ ] Entrée créée dans `admin_pages`
- [ ] Test d'accès en tant que super admin
- [ ] Test d'accès en tant qu'admin normal (sans permission)
- [ ] Test d'accès en tant qu'admin normal (avec permission)

---

## 🎯 Résultat Attendu

### ✅ Super Admin
- Accès à **TOUTES** les pages sans restriction
- Pas de redirection vers `access_denied.php`

### ✅ Admin Normal
- Accès uniquement aux pages autorisées dans `admin_permissions`
- Redirection vers `access_denied.php` si non autorisé

### ✅ Non Connecté
- Redirection vers `login.php` sur toutes les pages admin

---

## 🚀 Prochaines Étapes

1. ✅ **Tester** : Ouvrir `debug_permissions.php` et vérifier que vous êtes super admin
2. ✅ **Naviguer** : Tester l'accès à toutes les pages modifiées
3. ⚠️ **Compléter** : Ajouter les permissions sur les pages TODO (priorité HAUTE)
4. 📝 **Documenter** : Noter tout problème rencontré

---

## 📞 Support

### Problèmes Courants

**❌ "Access Denied" en tant que super admin**
→ Vérifier `admin.role` dans la base de données
→ Exécuter : `UPDATE admin SET role = 'superadmin' WHERE id = VOTRE_ID;`

**❌ "admin_id non défini"**
→ Se déconnecter et se reconnecter
→ Vérifier que `login.php` définit bien `$_SESSION['admin_id']`

**❌ "Table admin_pages vide"**
→ Exécuter le script `fix_permissions.sql`

---

**Créé par**: Claude Code Assistant
**Dernière mise à jour**: 04 octobre 2025
