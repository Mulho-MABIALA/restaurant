# Correctifs Paramètres Système - 26 Octobre 2025

## Problème résolu

**Symptôme:** Les modifications des paramètres (téléphone et email) dans l'interface admin ne s'affichaient pas sur les pages publiques.

**Cause:** Incompatibilité entre les noms de clés dans la base de données et ceux utilisés dans le code.

## Changements effectués

### 1. Correction des clés de paramètres

**Base de données (via admin/settings.php):**
- `contact_email` - Email de contact
- `contact_phone` - Téléphone de contact

**Code corrigé pour utiliser les bonnes clés:**

#### Fichier: `admin/includes/settings_loader.php`
```php
// AVANT (incorrect)
define('RESTAURANT_EMAIL', getSetting('restaurant_email', 'contact@restaurant.com'));
define('RESTAURANT_PHONE', getSetting('restaurant_phone', '+221 XX XXX XX XX'));

// APRÈS (correct)
define('RESTAURANT_EMAIL', getSetting('contact_email', 'contact@restaurant.com'));
define('RESTAURANT_PHONE', getSetting('contact_phone', '+221 XX XXX XX XX'));
```

#### Fichier: `public/index.php`
```php
// AVANT (incorrect)
getSetting('restaurant_email', ...)
getSetting('restaurant_phone', ...)

// APRÈS (correct)
getSetting('contact_email', ...)
getSetting('contact_phone', ...)
```

### 2. Fichiers modifiés

1. **admin/includes/settings_loader.php** (lignes 50, 53)
   - Constantes RESTAURANT_EMAIL et RESTAURANT_PHONE

2. **public/index.php** (lignes 1337, 1349)
   - Affichage du téléphone et de l'email dans la section contact

3. **public/test-settings.php** (lignes 124, 128)
   - Outil de diagnostic pour vérifier les paramètres

## Table des clés de paramètres

| Paramètre | Clé base de données | Constante PHP | Où l'utiliser |
|-----------|---------------------|---------------|---------------|
| Nom restaurant | `restaurant_name` | `RESTAURANT_NAME` | Partout |
| Email | `contact_email` | `RESTAURANT_EMAIL` | Contact |
| Téléphone | `contact_phone` | `RESTAURANT_PHONE` | Contact |
| Adresse | `restaurant_address` | `RESTAURANT_ADDRESS` | Contact |
| Devise | `currency` | `CURRENCY` | Commandes |

## Comment vérifier que ça fonctionne

### Méthode 1: Page de diagnostic
1. Ouvrir: `http://localhost/restaurant/public/test-settings.php`
2. Vérifier la section "4. Fonction getSetting()"
3. Les valeurs `contact_email` et `contact_phone` doivent afficher vos données

### Méthode 2: Tester sur la page publique
1. Aller dans **Admin → Paramètres**
2. Modifier:
   - Email de contact
   - Téléphone
3. Sauvegarder
4. Ouvrir la page publique: `http://localhost/restaurant/public/index.php`
5. Descendre à la section "Contactez-nous"
6. Vérifier que les nouvelles valeurs s'affichent

### Méthode 3: Footer admin
1. Le nom du restaurant dans le footer admin doit afficher la valeur modifiée
2. Exemple: Si vous changez le nom en "Tresor", le footer doit afficher "© 2025 Tresor - Tous droits réservés"

## Pour ajouter un nouveau paramètre

### Étape 1: Ajouter dans admin/settings.php
```php
<div>
    <label>Mon nouveau paramètre</label>
    <input type="text" name="settings[mon_parametre]"
           value="<?= htmlspecialchars($settings['mon_parametre'] ?? '') ?>" />
</div>
```

### Étape 2: Utiliser dans le code
```php
// Méthode 1: Via fonction
$maValeur = getSetting('mon_parametre', 'Valeur par défaut');

// Méthode 2: Via constante (ajouter dans settings_loader.php)
if (!defined('MON_PARAMETRE')) {
    define('MON_PARAMETRE', getSetting('mon_parametre', 'Valeur par défaut'));
}
```

## Important

### ✅ À FAIRE
- Utiliser `getSetting('contact_email')` pour l'email
- Utiliser `getSetting('contact_phone')` pour le téléphone
- Toujours échapper avec `htmlspecialchars()` lors de l'affichage
- Utiliser les constantes `RESTAURANT_*` quand c'est possible (plus simple)

### ❌ À NE PAS FAIRE
- Ne plus utiliser `restaurant_email` ou `restaurant_phone` (anciennes clés)
- Ne pas afficher directement sans `htmlspecialchars()`
- Ne pas modifier directement la base de données (passer par l'interface admin)

## Liste complète des paramètres disponibles

Depuis l'interface admin (`/admin/settings.php`), vous pouvez configurer:

### Paramètres généraux
- `restaurant_name` - Nom du restaurant
- `contact_email` - Email de contact
- `contact_phone` - Téléphone
- `restaurant_address` - Adresse complète

### Paramètres réservations
- `max_capacity` - Capacité maximale
- `booking_delay` - Délai minimum (heures)
- `opening_time` - Heure d'ouverture
- `closing_time` - Heure de fermeture

### Paramètres notifications
- `email_notifications` - Activer notifications email (1/0)
- `admin_email` - Email administrateur

## Résolution de problèmes

### Les changements ne s'affichent pas
1. Vider le cache du navigateur (Ctrl+F5)
2. Vérifier que les paramètres sont bien enregistrés dans admin/settings.php
3. Utiliser test-settings.php pour diagnostiquer
4. Vérifier que config.php charge bien settings_loader.php

### Erreur "getSetting not defined"
- Vérifier que `require_once '../config.php';` est présent en haut de page
- Vérifier que le fichier `admin/includes/settings_loader.php` existe

### Valeurs par défaut toujours affichées
- Les paramètres ne sont peut-être pas dans la base de données
- Vérifier avec test-settings.php section "5. Tous les paramètres en base"
- Sauvegarder les paramètres depuis l'interface admin

---

**Status:** ✅ RÉSOLU
**Date:** 26 octobre 2025
**Testeur:** À vérifier par l'utilisateur
