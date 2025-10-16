# 📚 Guide d'utilisation du Sidebar Optimisé

## ✅ Nouveau Sidebar : `sidebar_new.php`

Le nouveau sidebar a été optimisé pour être **100% responsive** et **facile à intégrer** dans n'importe quelle page.

---

## 🚀 Comment utiliser le sidebar dans vos pages

### Structure HTML recommandée :

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
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ma Page - Restaurant Jungle</title>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <!-- Alpine.js (pour les dropdowns) -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <!-- Vos styles personnalisés -->
    <style>
        /* Vos styles ici */
    </style>
</head>
<body class="bg-gray-50">

    <!-- Incluez le sidebar ici -->
    <?php include 'sidebar_new.php'; ?>

    <!-- Contenu principal -->
    <main class="lg:ml-[280px] transition-all duration-300" id="main-content">
        <div class="p-6">
            <h1 class="text-3xl font-bold text-gray-800 mb-6">Ma Page</h1>

            <!-- Votre contenu ici -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <p>Contenu de votre page...</p>
            </div>
        </div>
    </main>

    <!-- Script pour ajuster la marge du contenu quand le sidebar est réduit -->
    <script>
        const sidebar = document.getElementById('sidebar-restaurant');
        const mainContent = document.getElementById('main-content');

        // Observer les changements de classe du sidebar
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.attributeName === 'class') {
                    if (sidebar.classList.contains('collapsed')) {
                        mainContent.classList.remove('lg:ml-[280px]');
                        mainContent.classList.add('lg:ml-[80px]');
                    } else {
                        mainContent.classList.remove('lg:ml-[80px]');
                        mainContent.classList.add('lg:ml-[280px]');
                    }
                }
            });
        });

        observer.observe(sidebar, { attributes: true });
    </script>

</body>
</html>
```

---

## 🎨 Fonctionnalités du Sidebar

### 1. **Mode Desktop** 💻
- Sidebar à gauche (280px)
- Bouton pour le réduire (80px)
- État sauvegardé dans localStorage
- Animation fluide

### 2. **Mode Mobile** 📱
- Sidebar caché par défaut
- Bouton hamburger en haut à gauche
- Overlay sombre au clic
- Swipe pour fermer

### 3. **Mode Réduit** (Desktop)
- Largeur : 80px
- Icônes uniquement
- Textes cachés
- Tooltip au survol (à implémenter si besoin)

---

## 📐 Classes CSS Importantes

| Classe | Description |
|--------|-------------|
| `#sidebar-restaurant` | Conteneur principal du sidebar |
| `.collapsed` | Mode réduit (80px) |
| `.mobile-open` | Sidebar ouvert sur mobile |
| `#sidebar-overlay` | Overlay pour mobile |
| `.sidebar-text` | Texte caché en mode réduit |
| `.nav-item-sidebar` | Item de navigation |
| `.active-nav-sidebar` | Item actif (page courante) |

---

## 🔧 Fonctions JavaScript

### `toggleSidebarMobile()`
Ouvre/ferme le sidebar sur mobile

### `toggleSidebarDesktop()`
Réduit/agrandit le sidebar sur desktop

### LocalStorage
- Clé : `sidebarCollapsed`
- Valeur : `true` ou `false`

---

## 📱 Breakpoints Responsive

| Taille d'écran | Comportement |
|----------------|--------------|
| < 1024px | Sidebar en overlay (mobile) |
| ≥ 1024px | Sidebar fixe (desktop) |

---

## 🎯 Avantages du Nouveau Sidebar

✅ **Pas de conflits HTML** (pas de `<html>`, `<head>`, `<body>`)
✅ **Styles isolés** avec préfixes (`-sidebar`, `-restaurant`)
✅ **100% responsive** (mobile + desktop)
✅ **Mode réduit** (gain d'espace)
✅ **État persistant** (localStorage)
✅ **Animations fluides**
✅ **Facile à intégrer** (1 seul include)
✅ **Compatible avec toutes vos pages**

---

## 🔄 Migration de l'ancien sidebar

### Remplacer :
```php
<?php include 'sidebar.php'; ?>
```

### Par :
```php
<?php include 'sidebar_new.php'; ?>
```

### Ajuster le contenu principal :
```html
<main class="lg:ml-[280px]" id="main-content">
    <!-- Votre contenu -->
</main>
```

---

## 🐛 Résolution de problèmes

### Le sidebar ne s'affiche pas
- Vérifiez que Font Awesome est chargé
- Vérifiez que Tailwind CSS est chargé
- Vérifiez la session admin

### Le contenu est caché derrière le sidebar
- Ajoutez `lg:ml-[280px]` à votre `<main>`
- Ajoutez le script d'ajustement automatique

### Le sidebar ne se ferme pas sur mobile
- Vérifiez que le script est bien inclus
- Vérifiez la console pour les erreurs JS

---

## 📞 Support

Pour toute question, vérifiez :
1. La console du navigateur (F12)
2. Les permissions dans `permissions.php`
3. La session admin est active

---

**Créé le :** Octobre 2025
**Version :** 2.0
**Auteur :** Assistant IA

---

## 🎉 Prêt à utiliser !

Votre sidebar est maintenant **optimisé**, **responsive** et **prêt à être intégré** dans toutes vos pages sans aucun conflit ! 🚀
