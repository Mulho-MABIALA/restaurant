# Intégration des notifications cuisine dans commandes.php

## Méthode 1 : Intégration simple (recommandée)

Ajoutez simplement cette ligne **avant la balise `</body>`** dans votre fichier `commandes.php` :

```html
<!-- Système de notifications cuisine -->
<script src="js/cuisine_notifications.js"></script>
```

C'est tout ! Le système se mettra en route automatiquement.

---

## Méthode 2 : Intégration avec vérification de création du dossier

Si le dossier `js/` n'existe pas dans `admin/`, créez-le d'abord :

### Sur Windows (WAMP) :
```bash
mkdir c:\wamp64\www\restaurant\admin\js
```

### Sur Linux/Mac :
```bash
mkdir -p /var/www/restaurant/admin/js
```

Ensuite, déplacez le fichier `cuisine_notifications.js` dans ce dossier.

---

## Méthode 3 : Intégration avec configuration personnalisée

Si vous voulez personnaliser le système, ajoutez ceci avant l'inclusion du script :

```html
<script>
// Configuration personnalisée (optionnel)
window.cuisineConfig = {
    checkInterval: 10000,  // Vérifier toutes les 10 secondes au lieu de 5
    soundEnabled: true,    // Activer/désactiver le son
    toastEnabled: true,    // Activer/désactiver les toasts
    browserNotifications: true  // Activer/désactiver les notifications navigateur
};
</script>
<script src="js/cuisine_notifications.js"></script>
```

---

## Exemple d'intégration complète dans commandes.php

Voici où placer le script dans votre structure HTML :

```html
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion des commandes</title>
    <!-- Vos autres CSS -->
</head>
<body>
    <!-- Votre contenu de page -->
    <div class="container">
        <!-- Tableau des commandes, etc. -->
    </div>

    <!-- VOS SCRIPTS EXISTANTS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- ⭐ NOUVEAU : Système de notifications cuisine -->
    <script src="js/cuisine_notifications.js"></script>

</body>
</html>
```

---

## Vérification de l'installation

### Test 1 : Vérifier que le fichier est accessible

Ouvrez dans votre navigateur :
```
http://localhost/restaurant/admin/js/cuisine_notifications.js
```

Vous devez voir le code JavaScript. Si vous avez une erreur 404, le fichier n'est pas au bon endroit.

### Test 2 : Vérifier dans la console

1. Ouvrez `commandes.php` dans votre navigateur
2. Appuyez sur `F12` pour ouvrir la console développeur
3. Vous devriez voir :
   ```
   🔔 Système de notifications cuisine activé
   ✅ Surveillance des commandes démarrée
   ```

### Test 3 : Test complet

1. Ouvrez `cuisine.php` dans un onglet
2. Ouvrez `commandes.php` dans un autre onglet
3. Dans `cuisine.php`, créez ou trouvez une commande
4. Cliquez sur "Démarrer" puis "Prêt"
5. Dans `commandes.php`, vous devriez voir :
   - Un badge en haut à droite avec "1 commande(s) prête(s)"
   - Une notification toast
   - Entendre un son (si activé)

---

## Dépannage

### Le script ne se charge pas

**Erreur dans la console :**
```
Failed to load resource: the server responded with a status of 404
```

**Solution :**
- Vérifiez que le fichier existe dans `admin/js/cuisine_notifications.js`
- Vérifiez le chemin dans votre balise `<script src="...">`
- Si nécessaire, utilisez un chemin absolu : `<script src="/restaurant/admin/js/cuisine_notifications.js"></script>`

### Le script se charge mais ne fonctionne pas

**Vérifier dans la console :**
```javascript
console.log(typeof cuisineNotifications);
```

Si ça retourne `"undefined"`, le script n'est pas initialisé correctement.

**Solutions :**
1. Vérifiez qu'il n'y a pas d'erreurs JavaScript dans la console
2. Assurez-vous que jQuery ou autre bibliothèque requise est chargée
3. Vérifiez que FontAwesome est chargé (pour les icônes)

### Les notifications n'apparaissent pas

**Vérifier l'API :**

Ouvrez dans votre navigateur :
```
http://localhost/restaurant/admin/api_cuisine_notifications.php?action=count_commandes_pretes
```

Vous devriez voir :
```json
{"success":true,"count":0}
```

Si vous avez une erreur, vérifiez :
- Que vous êtes connecté en tant qu'admin
- Que le fichier `api_cuisine_notifications.php` existe
- Que la connexion à la base de données fonctionne

### Le son ne joue pas

**Raisons possibles :**
1. Le navigateur bloque la lecture automatique
2. Le volume est coupé
3. Certains navigateurs requièrent une interaction utilisateur avant de jouer un son

**Solution :**
Cliquez n'importe où sur la page après le chargement, puis testez à nouveau.

---

## Personnalisation avancée

### Changer le son de notification

Remplacez dans `cuisine_notifications.js` :

```javascript
this.notificationSound = new Audio('data:audio/wav;base64,...');
```

Par :

```javascript
this.notificationSound = new Audio('/path/to/your/sound.mp3');
```

### Changer l'intervalle de vérification

Dans `cuisine_notifications.js`, ligne ~9 :

```javascript
this.checkInterval = 5000; // 5 secondes
```

Changez la valeur (en millisecondes) :
- 3000 = 3 secondes (plus réactif, plus de requêtes)
- 10000 = 10 secondes (moins réactif, moins de charge)

### Désactiver les notifications navigateur

Dans `cuisine_notifications.js`, commentez la ligne :

```javascript
// this.requestNotificationPermission();
```

### Personnaliser le style du badge

Dans `cuisine_notifications.js`, modifiez le style dans `createNotificationUI()` :

```javascript
badge.style.cssText = `
    position: fixed;
    top: 80px;
    right: 20px;
    background: #dc3545;  /* Rouge au lieu de vert */
    color: white;
    padding: 15px 30px;   /* Plus grand */
    /* ... */
`;
```

---

## Performance et optimisation

### Si vous avez beaucoup de commandes

Augmentez l'intervalle de vérification :

```javascript
this.checkInterval = 10000; // 10 secondes au lieu de 5
```

### Si vous avez plusieurs pages admin ouvertes

Le système fonctionne indépendamment dans chaque onglet. C'est normal que chaque onglet vérifie l'API.

Pour optimiser, vous pouvez utiliser `localStorage` pour synchroniser entre onglets (avancé).

---

## FAQ

### Q : Le système fonctionne-t-il si je ferme `cuisine.php` ?

**R :** Oui ! L'API `api_cuisine_notifications.php` interroge directement la base de données. La page `cuisine.php` n'a pas besoin d'être ouverte.

### Q : Puis-je avoir plusieurs écrans de cuisine ?

**R :** Oui ! Vous pouvez ouvrir `cuisine.php` sur plusieurs appareils/écrans. Ils se synchroniseront tous via la base de données.

### Q : Les notifications fonctionnent-elles sur mobile ?

**R :** Oui, le système est responsive. Les notifications navigateur peuvent ne pas fonctionner sur certains mobiles, mais les toasts et le badge fonctionnent partout.

### Q : Que se passe-t-il si je perds la connexion internet ?

**R :** Le système essaiera de continuer à vérifier, mais affichera des erreurs dans la console. Quand la connexion reviendra, tout reprendra automatiquement.

### Q : Puis-je intégrer ça dans une app mobile ?

**R :** Oui ! Le système utilise des API REST standard. Vous pouvez créer une app qui interroge `api_cuisine_notifications.php`.

---

## Support technique

Si vous rencontrez des problèmes :

1. ✅ Vérifiez ce guide d'intégration
2. ✅ Consultez le README_CUISINE.md
3. ✅ Regardez la console JavaScript (F12)
4. ✅ Vérifiez les logs PHP du serveur
5. ✅ Testez l'API directement dans le navigateur

---

## Checklist d'installation

- [ ] Fichier `cuisine.php` créé et accessible
- [ ] Fichier `api_cuisine_notifications.php` créé et accessible
- [ ] Dossier `admin/js/` créé
- [ ] Fichier `cuisine_notifications.js` dans `admin/js/`
- [ ] Script ajouté dans `commandes.php` avant `</body>`
- [ ] SQL exécuté (cuisine_setup.sql) si nécessaire
- [ ] Permissions configurées pour l'accès admin
- [ ] Test complet effectué
- [ ] Notifications fonctionnelles

---

Dernière mise à jour : 2025-01-XX
