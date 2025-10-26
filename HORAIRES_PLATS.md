# 🍽️ Horaires de Disponibilité des Plats

## Vue d'ensemble

Extension de la fonctionnalité des horaires de catégories: vous pouvez maintenant définir des horaires spécifiques pour **chaque plat individuellement**.

Par exemple: "Poulet rôti" disponible de 10h à 15h, même si sa catégorie "Plats Principaux" est disponible 24h/24.

## 🎯 Priorité des horaires

Le système vérifie la disponibilité dans cet ordre:

1. **Horaires du plat** (si activés) → Plus prioritaire
2. **Horaires de la catégorie** (si activés)
3. **Disponibilité du plat** (field `disponible` dans la BDD)

**Un plat est disponible UNIQUEMENT si:**
- ✅ Son horaire personnel le permet (si activ é)
- ✅ ET l'horaire de sa catégorie le permet (si activé)
- ✅ ET il est marqué comme `disponible = 1`

## 🚀 Installation

### Étape 1: Migration de la base de données

Exécutez le script de migration:
```
http://localhost/restaurant/admin/migrate_plats_hours.php
```

Cela ajoutera 3 colonnes à la table `plats`:
- `heure_debut` (TIME)
- `heure_fin` (TIME)
- `disponibilite_active` (TINYINT)

### Étape 2: Vérification

Après la migration, vous devriez voir un message de succès ✅

## 📖 Utilisation

### Ajouter un plat avec horaires

1. **Accédez à:** Admin → Ajouter un Plat
2. **Remplissez le formulaire normalement:**
   - Nom: `Poulet rôti`
   - Description: `Poulet rôti aux épices`
   - Prix: `3500`
   - Catégorie: `Plats Principaux`

3. **Section "Horaires de disponibilité":**
   - ☑️ Cochez "Limiter la disponibilité par horaires"
   - Heure début: `10:00`
   - Heure fin: `15:00`

4. **Sauvegardez**

### Modifier les horaires d'un plat existant

Via `admin/gestion_plats.php`:
1. Cliquez sur **Modifier** pour le plat
2. Ajustez les horaires
3. Sauvegardez

## 💡 Exemples d'utilisation

### Exemple 1: Plat disponible à horaires limités

```
Plat: Poulet rôti
Catégorie: Plats Principaux (24h/24)
Horaires plat: 10:00 - 15:00
```

**Résultat:**
- ✅ 10h00 → 15h00: **Disponible**
- ❌ 15h01 → 09h59: **Bloqué**

### Exemple 2: Double restriction (plat + catégorie)

```
Plat: Omelette
Catégorie: Brunch (10:00 - 15:00)
Horaires plat: 11:00 - 14:00
```

**Résultat:**
- ❌ 09h00: Catégorie fermée → **Bloqué**
- ❌ 10h30: Plat pas encore ouvert → **Bloqué**
- ✅ 12h00: Les deux ouverts → **Disponible**
- ❌ 14h30: Plat fermé → **Bloqué**
- ❌ 16h00: Catégorie fermée → **Bloqué**

### Exemple 3: Plat toujours disponible dans catégorie limitée

```
Plat: Salade César
Catégorie: Brunch (10:00 - 15:00)
Horaires plat: Aucun (24h/24)
```

**Résultat:**
- ❌ 09h00: Catégorie fermée → **Bloqué**
- ✅ 12h00: Catégorie ouverte → **Disponible**
- ❌ 16h00: Catégorie fermée → **Bloqué**

## 🎨 Interface Utilisateur

### Page Admin - Ajouter un plat

Le formulaire affiche une section bleue "Horaires de disponibilité":

```
┌────────────────────────────────────────────┐
│ ☐ Limiter la disponibilité par horaires   │
│                                            │
│ [Champs masqués par défaut]               │
└────────────────────────────────────────────┘
```

Quand cochée:

```
┌────────────────────────────────────────────┐
│ ☑ Limiter la disponibilité par horaires   │
│                                            │
│ ℹ Ce plat sera disponible uniquement      │
│ pendant ces horaires                       │
│                                            │
│ ▶️ Heure de début: [10:00]                │
│ ⏹️ Heure de fin:   [15:00]                │
│                                            │
│ 💡 Exemple: Poulet rôti 10:00 - 15:00     │
└────────────────────────────────────────────┘
```

### Page Public - Menu

Les plats indisponibles seront:
- Grisés/désactivés
- Non ajoutables au panier
- Avec message explicatif

## 🔒 Sécurité

### Protection multi-niveaux

1. **Frontend (JavaScript):**
   - Boutons désactivés visuellement
   - Messages informatifs

2. **Backend (PHP add_to_cart):**
   - Vérification horaires du plat
   - Vérification horaires de la catégorie
   - Message d'erreur JSON si bloqué

### Exemple de réponse serveur

Si tentative d'ajout d'un plat hors horaires:

```json
{
  "success": false,
  "message": "Ce plat n'est pas disponible actuellement. Disponible de 10:00 à 15:00"
}
```

## 📁 Fichiers modifiés/créés

### Nouveaux fichiers

| Fichier | Description |
|---------|-------------|
| `admin/migrate_plats_hours.php` | Script de migration BDD plats |
| `HORAIRES_PLATS.md` | Cette documentation |

### Fichiers modifiés

| Fichier | Modifications |
|---------|---------------|
| `admin/ajouter_plat.php` | Formulaire + champs horaires |
| `includes/category_availability.php` | Fonctions `isPlatAvailable()` etc. |
| `public/menu.php` | Vérification disponibilité plats |

## 🛠️ Fonctions disponibles

### `isPlatAvailable($plat, $categorie = null)`

Vérifie si un plat est disponible maintenant.

```php
$plat = [
    'disponibilite_active' => 1,
    'heure_debut' => '10:00:00',
    'heure_fin' => '15:00:00'
];

$categorie = [
    'disponibilite_active' => 1,
    'heure_debut' => '09:00:00',
    'heure_fin' => '16:00:00'
];

if (isPlatAvailable($plat, $categorie)) {
    echo "Plat disponible!";
}
```

**Logique:**
1. Vérifie d'abord l'horaire du plat
2. Puis vérifie l'horaire de la catégorie
3. Retourne `false` dès qu'un critère échoue

### `getPlatAvailabilityMessage($plat, $categorie = null)`

Retourne un message explicatif.

```php
echo getPlatAvailabilityMessage($plat, $categorie);
// "Disponible de 10:00 à 15:00"
// ou "Disponible maintenant"
```

### `filterPlatsByAvailability($plats, $categories_map, $onlyAvailable)`

Filtre un tableau de plats.

```php
$categories_map = [
    1 => ['id' => 1, 'disponibilite_active' => 1, ...]
];

$plats_disponibles = filterPlatsByAvailability($plats, $categories_map, true);
// Retourne uniquement les plats disponibles
```

## 📊 Base de données

### Structure table `plats`

```sql
ALTER TABLE plats
ADD COLUMN heure_debut TIME DEFAULT NULL,
ADD COLUMN heure_fin TIME DEFAULT NULL,
ADD COLUMN disponibilite_active TINYINT(1) DEFAULT 0;
```

### Exemple de données

```sql
-- Poulet rôti disponible 10h-15h
INSERT INTO plats (nom, prix, categorie_id, disponibilite_active, heure_debut, heure_fin)
VALUES ('Poulet rôti', 3500, 2, 1, '10:00:00', '15:00:00');

-- Salade toujours disponible
INSERT INTO plats (nom, prix, categorie_id, disponibilite_active, heure_debut, heure_fin)
VALUES ('Salade César', 2000, 1, 0, NULL, NULL);
```

## 🐛 Dépannage

### Erreur: Column not found 'heure_debut'

**Solution:** Exécutez la migration
```
http://localhost/restaurant/admin/migrate_plats_hours.php
```

### Le plat est toujours bloqué

**Vérifiez:**
1. Horaires du plat (si activés)
2. Horaires de la catégorie (si activés)
3. Champ `disponible` dans la table `plats`
4. L'heure actuelle du serveur: `<?php echo date('H:i:s'); ?>`

### Le plat affiche toujours "disponible"

**Vérifiez:**
1. La checkbox est bien cochée dans le formulaire
2. Les horaires sont bien sauvegardés en BDD
3. Le cache du navigateur (Ctrl+F5)

## 🎯 Cas d'usage pratiques

### Service du midi uniquement

Certains plats "fait maison" disponibles uniquement le midi:

```sql
UPDATE plats
SET disponibilite_active = 1,
    heure_debut = '11:30:00',
    heure_fin = '14:30:00'
WHERE nom IN ('Plat du jour', 'Menu ouvrier');
```

### Happy Hour

Plats promotionnels en soirée:

```sql
UPDATE plats
SET disponibilite_active = 1,
    heure_debut = '17:00:00',
    heure_fin = '19:00:00'
WHERE nom LIKE '%Happy%';
```

### Petit-déjeuner

```sql
UPDATE plats
SET disponibilite_active = 1,
    heure_debut = '07:00:00',
    heure_fin = '10:30:00'
WHERE categorie_id = (SELECT id FROM categories WHERE nom = 'Petit Déjeuner');
```

## ✅ Checklist de mise en production

- [ ] Migration BDD exécutée (plats)
- [ ] Migration BDD exécutée (catégories) ← Important!
- [ ] Test ajout plat avec horaires
- [ ] Test modification plat existant
- [ ] Test affichage menu.php
- [ ] Test blocage add_to_cart (horaire plat)
- [ ] Test blocage add_to_cart (horaire catégorie)
- [ ] Test combinaison plat + catégorie
- [ ] Vérification mobile
- [ ] Vérification desktop

## 🔗 Documentation liée

Voir aussi:
- [HORAIRES_CATEGORIES.md](HORAIRES_CATEGORIES.md) - Documentation horaires catégories
- `includes/category_availability.php` - Code source des fonctions

---

**Version:** 1.0
**Date:** 26 octobre 2025
**Complément de:** HORAIRES_CATEGORIES.md
