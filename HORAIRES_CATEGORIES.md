# 🕐 Horaires de Disponibilité des Catégories

## Vue d'ensemble

Cette fonctionnalité permet de définir des horaires de disponibilité pour chaque catégorie de plats. Par exemple, la catégorie "Brunch" peut être disponible uniquement de 10h00 à 15h00.

## 📋 Fonctionnalités

### Pour l'administrateur

✅ Définir des horaires de début et de fin pour chaque catégorie
✅ Activer/désactiver la limitation horaire par catégorie
✅ Voir en un coup d'œil quelles catégories ont des horaires
✅ Modifier les horaires à tout moment

### Pour les clients

✅ Les catégories fermées sont grisées et non cliquables
✅ Affichage des horaires sur chaque bouton de catégorie
✅ Impossible d'ajouter au panier un plat d'une catégorie fermée
✅ Message informatif si tentative d'ajout hors horaires

## 🚀 Installation

### Étape 1: Migration de la base de données

1. Connectez-vous à l'admin du restaurant
2. Accédez à: `http://localhost/restaurant/admin/migrate_category_hours.php`
3. Cliquez sur le bouton pour lancer la migration
4. Vérifiez que les 3 colonnes ont été ajoutées:
   - `heure_debut` (TIME)
   - `heure_fin` (TIME)
   - `disponibilite_active` (TINYINT)

### Étape 2: Configuration initiale

1. Allez dans **Admin → Catégories de Plats**
2. Créez une nouvelle catégorie ou modifiez une existante
3. Testez la fonctionnalité

## 📖 Utilisation

### Créer une catégorie avec horaires

1. **Accédez à la page des catégories:**
   - Menu Admin → Catégories de Plats

2. **Remplissez le formulaire:**
   - Nom: `Brunch`
   - Description: `Nos délicieux brunch du week-end`

3. **Activez les horaires:**
   - ☑️ Cochez "Limiter la disponibilité par horaires"
   - Les champs horaires apparaissent

4. **Définissez les horaires:**
   - Heure début: `10:00`
   - Heure fin: `15:00`

5. **Sauvegardez:**
   - Cliquez sur "Créer la catégorie"

### Modifier les horaires d'une catégorie existante

1. Dans la liste des catégories, cliquez sur **Modifier** (icône crayon)
2. Ajustez les horaires ou décochez pour rendre disponible 24h/24
3. Sauvegardez

### Désactiver les horaires

Pour rendre une catégorie disponible en permanence:
1. Décochez "Limiter la disponibilité par horaires"
2. Les horaires restent sauvegardés mais ne sont plus appliqués

## 💡 Exemples d'utilisation

### Exemple 1: Brunch du week-end
```
Nom: Brunch
Horaires: 10:00 - 15:00
Statut: ☑️ Actif
```
**Résultat:** Les clients ne peuvent commander du brunch qu'entre 10h et 15h

### Exemple 2: Menu du déjeuner
```
Nom: Déjeuner
Horaires: 11:30 - 14:30
Statut: ☑️ Actif
```
**Résultat:** Menu déjeuner disponible uniquement sur la plage horaire du midi

### Exemple 3: Menu de nuit
```
Nom: Midnight Snacks
Horaires: 22:00 - 02:00
Statut: ☑️ Actif
```
**Résultat:** Menu spécial disponible en soirée

### Exemple 4: Menu permanent
```
Nom: Carte principale
Horaires: -
Statut: ☐ Inactif
```
**Résultat:** Disponible 24h/24

## 🎨 Interface Utilisateur

### Page Admin - Liste des catégories

Le tableau affiche une colonne **Disponibilité** qui montre:

| Badge | Signification |
|-------|---------------|
| 🔵 `10:00 - 15:00` | Catégorie avec horaires actifs |
| 🟢 `24h/24` | Catégorie toujours disponible |

### Page Public - Menu

Les boutons de catégories affichent:

**Catégorie disponible avec horaires:**
```
┌─────────────────┐
│    Brunch       │
│ 🕐 10:00-15:00  │
└─────────────────┘
```

**Catégorie fermée:**
```
┌─────────────────┐
│    Brunch       │
│ 🔒 Fermé        │
└─────────────────┘
(Grisé, non cliquable)
```

## 🔒 Sécurité

### Protection côté serveur

Le système vérifie la disponibilité à DEUX niveaux:

1. **Affichage (Frontend):**
   - Catégories fermées: grisées, non cliquables
   - Horaires affichés sur les boutons

2. **Ajout au panier (Backend):**
   - Vérification lors de `add_to_cart`
   - Message d'erreur si catégorie fermée
   - Impossible de contourner côté client

### Exemple de protection

Si un utilisateur tente de contourner JavaScript et d'ajouter un produit d'une catégorie fermée:

```json
{
  "success": false,
  "message": "Cette catégorie n'est pas disponible actuellement. Horaires: 10:00 - 15:00"
}
```

## 📁 Fichiers modifiés/créés

### Nouveaux fichiers

| Fichier | Description |
|---------|-------------|
| `admin/migrate_category_hours.php` | Script de migration BDD |
| `admin/sql/add_category_hours.sql` | Script SQL manuel |
| `includes/category_availability.php` | Fonctions de vérification |
| `HORAIRES_CATEGORIES.md` | Cette documentation |

### Fichiers modifiés

| Fichier | Modifications |
|---------|---------------|
| `admin/categories_plats.php` | Formulaire + affichage horaires |
| `public/menu.php` | Vérification + affichage badges |

## 🛠️ Fonctions disponibles

Le fichier `includes/category_availability.php` contient:

### `isCategoryAvailable($categorie)`
Vérifie si une catégorie est disponible maintenant.

```php
$categorie = ['disponibilite_active' => 1, 'heure_debut' => '10:00:00', 'heure_fin' => '15:00:00'];
if (isCategoryAvailable($categorie)) {
    echo "Catégorie disponible !";
}
```

### `getCategoryAvailabilityMessage($categorie)`
Retourne un message de disponibilité.

```php
echo getCategoryAvailabilityMessage($categorie);
// Affiche: "Disponible de 10:00 à 15:00"
```

### `getTimeUntilCategoryUnavailable($categorie)`
Temps restant avant fermeture.

```php
$remaining = getTimeUntilCategoryUnavailable($categorie);
// Retourne: "2h 30min"
```

### `getTimeUntilCategoryAvailable($categorie)`
Temps avant ouverture.

```php
$until = getTimeUntilCategoryAvailable($categorie);
// Retourne: "Dans 1h 15min"
```

### `filterCategoriesByAvailability($categories, $onlyAvailable)`
Filtre un tableau de catégories.

```php
$available_only = filterCategoriesByAvailability($categories, true);
// Retourne uniquement les catégories disponibles
```

## 🐛 Dépannage

### Problème: Les colonnes n'existent pas

**Symptôme:** Erreur SQL "Unknown column 'heure_debut'"

**Solution:**
1. Exécutez le script de migration: `admin/migrate_category_hours.php`
2. Ou exécutez manuellement le SQL dans `admin/sql/add_category_hours.sql`

### Problème: Les horaires ne s'affichent pas

**Vérifiez:**
1. La case "Limiter la disponibilité" est cochée
2. Les heures sont bien renseignées
3. Le cache du navigateur (Ctrl+F5)

### Problème: Catégorie toujours bloquée

**Vérifiez:**
1. L'heure actuelle du serveur: `<?php echo date('H:i:s'); ?>`
2. Les horaires configurés dans la BDD
3. Le fuseau horaire PHP dans `php.ini`

## 📊 Base de données

### Structure de la table `categories`

```sql
CREATE TABLE categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(255) NOT NULL,
    description TEXT,
    heure_debut TIME DEFAULT NULL,
    heure_fin TIME DEFAULT NULL,
    disponibilite_active TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### Exemple de données

```sql
-- Brunch disponible de 10h à 15h
INSERT INTO categories (nom, description, disponibilite_active, heure_debut, heure_fin)
VALUES ('Brunch', 'Nos brunch du week-end', 1, '10:00:00', '15:00:00');

-- Menu principal toujours disponible
INSERT INTO categories (nom, description, disponibilite_active, heure_debut, heure_fin)
VALUES ('Carte principale', 'Notre menu classique', 0, NULL, NULL);
```

## 🎯 Cas d'usage avancés

### Forcer une catégorie en permanence

```sql
UPDATE categories SET disponibilite_active = 0 WHERE id = 1;
```

### Changer temporairement les horaires

```sql
-- Prolonger le brunch jusqu'à 17h aujourd'hui
UPDATE categories
SET heure_fin = '17:00:00'
WHERE nom = 'Brunch';
```

### Désactiver tous les horaires

```sql
UPDATE categories SET disponibilite_active = 0;
```

## ✅ Checklist de mise en production

- [ ] Migration BDD exécutée avec succès
- [ ] Test création catégorie avec horaires
- [ ] Test catégorie disponible (dans les horaires)
- [ ] Test catégorie fermée (hors horaires)
- [ ] Test ajout au panier catégorie disponible
- [ ] Test blocage ajout au panier catégorie fermée
- [ ] Vérification affichage mobile
- [ ] Vérification affichage desktop
- [ ] Test modification horaires existants
- [ ] Test désactivation horaires

## 📞 Support

En cas de problème, vérifiez:
1. Les logs PHP: `tail -f /var/log/php_errors.log`
2. Les logs Apache: `tail -f /var/log/apache2/error.log`
3. La console JavaScript du navigateur (F12)

---

**Version:** 1.0
**Date:** 26 octobre 2025
**Auteur:** Claude Code Assistant
