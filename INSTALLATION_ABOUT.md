# Installation de la section À Propos Dynamique

## 📋 Étapes d'installation

### 1. Créer la table dans la base de données

Exécutez le fichier SQL suivant dans phpMyAdmin:

**Fichier:** `admin/sql/about_section.sql`

```sql
CREATE TABLE IF NOT EXISTS about_section (
    id INT PRIMARY KEY AUTO_INCREMENT,
    titre VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    image VARCHAR(255) DEFAULT NULL,
    sous_titre VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO about_section (titre, description, sous_titre) VALUES
(
    'À propos de Mulho',
    'Bienvenue chez Mulho, où l''authenticité sénégalaise rencontre l''excellence culinaire. Depuis notre création, nous nous efforçons de vous offrir une expérience gastronomique unique, mêlant saveurs traditionnelles et touches modernes.',
    'Où l''authenticité sénégalaise rencontre l''excellence culinaire'
);
```

### 2. Ajouter la permission dans la table permissions

```sql
INSERT INTO permissions (page_name, display_name, description, category)
VALUES ('gestion_about', 'Gestion À Propos', 'Gérer la section À propos', 'Contenu');
```

### 3. Accéder à la page admin

URL: `http://localhost/restaurant/admin/gestion_about.php`

## ✨ Fonctionnalités

### Page Admin (`/admin/gestion_about.php`)

- ✅ Modifier le titre principal
- ✅ Modifier le sous-titre
- ✅ Modifier la description
- ✅ Upload d'image
- ✅ **Statistiques automatiques:**
  - Années d'existence (calculé automatiquement)
  - Nombre de plats disponibles
  - Nombre de clients (basé sur les commandes)

### Affichage sur la page d'accueil

La section À propos (`/public/index.php`) affiche maintenant:
- ✅ Titre dynamique
- ✅ Sous-titre dynamique
- ✅ Description dynamique
- ✅ Image dynamique
- ✅ **Compteurs automatiques:**
  - Années d'existence
  - Plats disponibles
  - Clients satisfaits

## 🎯 Variables PHP disponibles

Dans `public/index.php`, vous avez accès à:

```php
$aboutData  // Données de la section À propos
    - ['titre']        // Titre principal
    - ['sous_titre']   // Sous-titre
    - ['description']  // Description
    - ['image']        // Nom du fichier image

$anneesExistence  // Nombre d'années depuis 2020
$totalPlats       // Nombre de plats disponibles
$totalClients     // Nombre de clients uniques
```

## 📝 Modifier l'année de création du restaurant

Dans le fichier `public/index.php` ligne 54:

```php
$anneeCreation = 2020; // Modifier cette valeur selon votre restaurant
```

## 🖼️ Emplacement des images

Les images sont stockées dans: `public/uploads/`

## 🔄 Pour rendre la section dynamique dans index.php

Cherchez la ligne 1431 et remplacez:

```php
<!-- Ancien code statique -->
<h1 class="hero-title fade-in">À propos de Mulho</h1>
<p class="hero-subtitle fade-in">Où l'authenticité sénégalaise rencontre l'excellence culinaire</p>
```

Par:

```php
<!-- Nouveau code dynamique -->
<h1 class="hero-title fade-in"><?= htmlspecialchars($aboutData['titre'] ?? 'À propos de Mulho') ?></h1>
<p class="hero-subtitle fade-in"><?= htmlspecialchars($aboutData['sous_titre'] ?? 'Où l\'authenticité sénégalaise rencontre l\'excellence culinaire') ?></p>
```

Et pour la description (ligne ~1440):

```php
<!-- Remplacer le texte statique par -->
<p class="mb-4" style="font-size: 1.2rem; line-height: 1.8; opacity: 0.9;">
    <?= nl2br(htmlspecialchars($aboutData['description'] ?? '')) ?>
</p>
```

Et pour les statistiques (lignes 1453, 1459, 1465):

```php
<!-- Années -->
<div class="stat-number" data-count="<?= $anneesExistence ?>">0</div>

<!-- Plats -->
<div class="stat-number" data-count="<?= $totalPlats ?>">0</div>

<!-- Clients -->
<div class="stat-number" data-count="<?= $totalClients ?>">0</div>
```

Et pour l'image (ligne 1445):

```php
<?php if (!empty($aboutData['image'])): ?>
    <img src="uploads/<?= htmlspecialchars($aboutData['image']) ?>" alt="<?= htmlspecialchars($aboutData['titre']) ?>">
<?php else: ?>
    <img src="assets/img/apropos.jpg" alt="Restaurant Mulho">
<?php endif; ?>
```

## 🎨 Interface admin

L'interface admin permet de:
1. Voir les statistiques en temps réel
2. Modifier le titre et sous-titre
3. Modifier la description
4. Upload une nouvelle image
5. Sauvegarder les modifications

Les statistiques se mettent à jour automatiquement en fonction:
- Des plats ajoutés
- Des commandes passées
- De l'année actuelle

## 📧 Support

En cas de problème, vérifiez:
1. ✅ La table `about_section` existe
2. ✅ Les permissions sont bien définies
3. ✅ Le dossier `public/uploads/` est accessible en écriture
4. ✅ Les variables PHP sont bien initialisées dans `index.php`
