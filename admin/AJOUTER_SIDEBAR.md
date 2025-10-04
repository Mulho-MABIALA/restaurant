# 📋 Plan d'Ajout de la Sidebar sur Toutes les Pages

## ✅ Pages AVEC Sidebar (Bon Chemin)

1. ✅ dashboard.php - `<?php include 'sidebar.php'; ?>`
2. ✅ gestion_plats.php - `<?php include 'sidebar.php'; ?>`
3. ✅ reservations.php - `<?php include 'sidebar.php'; ?>`
4. ✅ commandes.php - `<?php include 'sidebar.php'; ?>`
5. ✅ admin_gestion.php - `<?php include 'sidebar.php'; ?>`
6. ✅ gallery.php - `<?php include 'sidebar.php'; ?>`
7. ✅ admin_evenements.php - `<?php include 'sidebar.php'; ?>`
8. ✅ gestion_stock.php - `<?php include 'sidebar.php'; ?>`
9. ✅ categories_plats.php - `<?php include 'sidebar.php'; ?>`

## ❌ Pages SANS Sidebar (À Ajouter)

### Priorité HAUTE
1. ❌ gestion_employe.php
2. ❌ gestion_postes.php
3. ❌ gestion_paie.php
4. ❌ badgeuse.php

### Priorité MOYENNE
5. ❌ admin_newsletter.php
6. ❌ admin_newsletter_compose.php
7. ❌ presence.php
8. ❌ planification_horaires.php
9. ❌ horaires.php
10. ❌ statistiques.php

## 🎯 Structure HTML Requise

Pour ajouter la sidebar, la page doit avoir cette structure :

```html
<body>
    <div class="flex h-screen overflow-hidden">
        <!-- SIDEBAR ICI -->
        <?php include 'sidebar.php'; ?>

        <!-- CONTENU PRINCIPAL -->
        <div class="flex-1 overflow-y-auto">
            <!-- Votre contenu ici -->
        </div>
    </div>
</body>
```

## 🔧 Types de Pages Identifiés

### Type A: Pages avec structure flex existante
- dashboard.php
- gestion_plats.php
- → Juste ajouter `<?php include 'sidebar.php'; ?>`

### Type B: Pages avec navigation custom
- gestion_employe.php (a sa propre navbar)
- → Remplacer navbar par sidebar + ajuster structure

### Type C: Pages Bootstrap
- gestion_postes.php
- → Adapter pour flex layout + sidebar
