# 📱 Icônes PWA - Guide de Génération

## 🎯 Icônes Requises

Vous devez créer les icônes suivantes pour que la PWA fonctionne correctement:

| Fichier | Taille | Usage | Priorité |
|---------|--------|-------|----------|
| icon-72x72.png | 72×72 | Petite icône | Moyenne |
| icon-96x96.png | 96×96 | Standard | Moyenne |
| icon-128x128.png | 128×128 | Standard | Moyenne |
| icon-144x144.png | 144×144 | Windows Tile | Haute |
| icon-152x152.png | 152×152 | iOS | Haute |
| icon-192x192.png | 192×192 | **Android (obligatoire)** | **CRITIQUE** |
| icon-384x384.png | 384×384 | Large | Haute |
| icon-512x512.png | 512×512 | **Splash screen** | **CRITIQUE** |

## 🚀 Méthode 1: Générateur en Ligne (RECOMMANDÉ)

### Étape par étape:

1. **Créer votre logo source:**
   - Dimension: **1024×1024 pixels minimum**
   - Format: PNG avec fond transparent OU fond de couleur
   - Assurez-vous que le logo est centré et avec marges

2. **Utiliser RealFaviconGenerator:**
   - Aller sur: https://realfavicongenerator.net/
   - Cliquer "Select your Favicon image"
   - Upload votre logo 1024×1024

3. **Configurer les options:**

   **Android Chrome:**
   - Theme color: `#10b981` (ou votre couleur)
   - Background: White ou votre couleur
   - Marges: 10-15%

   **iOS:**
   - Dedicated picture: Utiliser l'image par défaut
   - Background: White
   - Marges: 10%

   **Windows:**
   - Background: `#10b981`
   - Tile image: Utiliser l'image par défaut

4. **Générer et télécharger:**
   - Cliquer "Generate your Favicons and HTML code"
   - Télécharger le package
   - Extraire les fichiers PNG dans ce dossier

5. **Renommer les fichiers:**
   ```
   android-chrome-192x192.png → icon-192x192.png
   android-chrome-512x512.png → icon-512x512.png
   apple-touch-icon.png → icon-152x152.png
   favicon-32x32.png → icon-72x72.png (redimensionner)
   etc.
   ```

## 🛠️ Méthode 2: ImageMagick (Ligne de commande)

Si vous avez ImageMagick installé:

```bash
# Depuis un logo source 1024x1024
convert logo-1024.png -resize 72x72 icon-72x72.png
convert logo-1024.png -resize 96x96 icon-96x96.png
convert logo-1024.png -resize 128x128 icon-128x128.png
convert logo-1024.png -resize 144x144 icon-144x144.png
convert logo-1024.png -resize 152x152 icon-152x152.png
convert logo-1024.png -resize 192x192 icon-192x192.png
convert logo-1024.png -resize 384x384 icon-384x384.png
convert logo-1024.png -resize 512x512 icon-512x512.png
```

## 🎨 Méthode 3: Photoshop/GIMP

1. Ouvrir votre logo source (min 1024×1024)
2. Pour chaque taille:
   - Image → Image Size → Entrer la taille (ex: 192×192)
   - Export As → PNG
   - Nommer: `icon-{taille}.png`

## 📋 Template Logo (si vous n'en avez pas)

Vous pouvez temporairement utiliser le logo du restaurant:

1. Prendre le logo existant dans `/public/assets/img/logo.png`
2. Le redimensionner aux tailles requises
3. Le remplacer plus tard par votre vrai logo

## ✅ Vérification

Une fois les icônes créées, vérifier:

```bash
# Liste des fichiers
ls -lh *.png

# Devrait afficher:
# icon-72x72.png
# icon-96x96.png
# icon-128x128.png
# icon-144x144.png
# icon-152x152.png
# icon-192x192.png
# icon-384x384.png
# icon-512x512.png
```

**Tailles minimales acceptables:**
- 192×192: **OBLIGATOIRE**
- 512×512: **OBLIGATOIRE**
- Autres: Fortement recommandées

## 🚨 Icônes Manquantes?

Si vous ne pouvez pas générer toutes les icônes maintenant:

**Minimum viable:**
1. Créer uniquement `icon-192x192.png` et `icon-512x512.png`
2. Les copier pour les autres tailles:
   ```bash
   cp icon-192x192.png icon-72x72.png
   cp icon-192x192.png icon-96x96.png
   cp icon-192x192.png icon-128x128.png
   cp icon-192x192.png icon-144x144.png
   cp icon-192x192.png icon-152x152.png
   cp icon-512x512.png icon-384x384.png
   ```

⚠️ **Note:** Ce n'est pas optimal mais ça fonctionne temporairement.

## 📱 Icônes Additionnelles (Optionnel)

### Screenshots pour Store Listing

Si vous voulez que la PWA apparaisse joliment dans les stores:

| Fichier | Taille | Format |
|---------|--------|--------|
| screenshot-menu.png | 540×720 | Portrait mobile |
| screenshot-commande.png | 540×720 | Portrait mobile |
| screenshot-suivi.png | 540×720 | Portrait mobile |

Référencées dans `manifest.json` lignes 43-63.

### Shortcuts Icons

Pour les raccourcis app (menu contextuel):

| Fichier | Taille | Usage |
|---------|--------|-------|
| shortcut-order.png | 96×96 | Raccourci "Commander" |
| shortcut-orders.png | 96×96 | Raccourci "Mes Commandes" |
| shortcut-reservation.png | 96×96 | Raccourci "Réserver" |

### Badge Icon

Pour les notifications:

| Fichier | Taille | Usage |
|---------|--------|-------|
| badge.png | 96×96 | Badge monochrome pour notifs |

## 🎯 Checklist Finale

- [ ] Logo source 1024×1024 créé
- [ ] 8 icônes principales générées (72 à 512)
- [ ] Icônes placées dans ce dossier
- [ ] Manifest.json vérifié (chemins corrects)
- [ ] Test dans Chrome DevTools → Application → Manifest
- [ ] Toutes les icônes s'affichent dans la preview

## 📞 Aide

**Besoin d'aide pour créer le logo?**

Services gratuits de design:
- Canva: https://www.canva.com/
- Figma: https://www.figma.com/
- Photopea: https://www.photopea.com/ (Photoshop online gratuit)

**Générateurs d'icônes:**
- https://realfavicongenerator.net/ (RECOMMANDÉ)
- https://www.pwabuilder.com/
- https://favicon.io/

---

**Status:** ⚠️ **ICÔNES À GÉNÉRER**
**Priorité:** 🔴 **HAUTE** (requis pour PWA fonctionnelle)
