# 📝 Utilisation des Paramètres Système

## 🎯 Vue d'ensemble

Les paramètres système permettent de configurer votre application depuis l'interface admin sans modifier le code. Tous les paramètres sont stockés en base de données et chargés automatiquement.

---

## 🔧 Configuration

### 1. Fichiers impliqués

```
restaurant/
├── config.php                                 # Charge settings_loader.php
├── admin/
│   ├── settings.php                           # Interface de gestion
│   └── includes/
│       └── settings_loader.php                # Chargeur de paramètres
```

### 2. Table base de données

```sql
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

---

## 📖 Utilisation dans votre code

### Méthode 1: Utiliser les constantes (Recommandé)

Les paramètres principaux sont disponibles comme constantes:

```php
<?php
// Ces constantes sont disponibles partout
echo RESTAURANT_NAME;      // "Restaurant Mulho"
echo RESTAURANT_EMAIL;     // "contact@restaurant.com"
echo RESTAURANT_PHONE;     // "+221 XX XXX XX XX"
echo RESTAURANT_ADDRESS;   // "Dakar, Sénégal"
echo CURRENCY;            // "FCFA"
?>
```

**Exemple dans une page:**
```php
<!DOCTYPE html>
<html>
<head>
    <title><?php echo RESTAURANT_NAME; ?> - Accueil</title>
</head>
<body>
    <h1>Bienvenue chez <?php echo RESTAURANT_NAME; ?></h1>
    <p>Email: <?php echo RESTAURANT_EMAIL; ?></p>
    <p>Téléphone: <?php echo RESTAURANT_PHONE; ?></p>
</body>
</html>
```

### Méthode 2: Utiliser la fonction getSetting()

Pour les paramètres personnalisés:

```php
<?php
// Récupérer un paramètre avec valeur par défaut
$taxRate = getSetting('tax_rate', '0');
$deliveryFee = getSetting('delivery_fee', '0');
$minOrder = getSetting('min_order_amount', '0');

// Paramètres personnalisés
$openingHours = getSetting('opening_hours', '9h-22h');
$facebookUrl = getSetting('facebook_url', '#');
?>
```

### Méthode 3: Récupérer tous les paramètres

```php
<?php
$allSettings = getAllSettings();

foreach ($allSettings as $key => $value) {
    echo "$key: $value<br>";
}
?>
```

---

## ✅ Exemples concrets

### Exemple 1: Footer avec nom du restaurant

**Fichier: `admin/includes/footer.php`**
```php
<footer>
    <p>© <?php echo date('Y'); ?> <?php echo RESTAURANT_NAME; ?></p>
    <p>Contact: <?php echo RESTAURANT_EMAIL; ?></p>
</footer>
```

### Exemple 2: Calcul de commande avec frais

**Fichier: `public/checkout.php`**
```php
<?php
$subtotal = 5000; // Sous-total de la commande

// Récupérer les paramètres
$taxRate = getSetting('tax_rate', '0') / 100;
$deliveryFee = getSetting('delivery_fee', '0');

// Calculs
$tax = $subtotal * $taxRate;
$total = $subtotal + $tax + $deliveryFee;
?>

<div class="order-summary">
    <p>Sous-total: <?php echo $subtotal; ?> <?php echo CURRENCY; ?></p>
    <p>TVA (<?php echo getSetting('tax_rate'); ?>%): <?php echo $tax; ?> <?php echo CURRENCY; ?></p>
    <p>Frais de livraison: <?php echo $deliveryFee; ?> <?php echo CURRENCY; ?></p>
    <p><strong>Total: <?php echo $total; ?> <?php echo CURRENCY; ?></strong></p>
</div>
```

### Exemple 3: Affichage conditionnel

```php
<?php
$minOrder = getSetting('min_order_amount', '0');
$cartTotal = 2500; // Total du panier
?>

<?php if ($cartTotal < $minOrder): ?>
    <div class="alert alert-warning">
        Montant minimum de commande: <?php echo $minOrder; ?> <?php echo CURRENCY; ?>
        <br>
        Il vous manque: <?php echo ($minOrder - $cartTotal); ?> <?php echo CURRENCY; ?>
    </div>
<?php else: ?>
    <button class="btn-checkout">Passer la commande</button>
<?php endif; ?>
```

### Exemple 4: Meta tags dynamiques

**Fichier: `public/index.php`**
```php
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?php echo RESTAURANT_NAME; ?> - Commande en ligne</title>
    <meta name="description" content="Commandez en ligne chez <?php echo RESTAURANT_NAME; ?>. <?php echo RESTAURANT_ADDRESS; ?>">
    <meta property="og:title" content="<?php echo RESTAURANT_NAME; ?>">
    <meta property="og:description" content="<?php echo getSetting('site_description', 'Restaurant de qualité'); ?>">
</head>
<body>
    <!-- Contenu -->
</body>
</html>
```

---

## 🎛️ Paramètres disponibles par défaut

| Clé | Description | Exemple |
|-----|-------------|---------|
| `restaurant_name` | Nom du restaurant | "Restaurant Mulho" |
| `restaurant_email` | Email de contact | "contact@restaurant.com" |
| `restaurant_phone` | Téléphone | "+221 XX XXX XX XX" |
| `restaurant_address` | Adresse complète | "Dakar, Sénégal" |
| `currency` | Devise | "FCFA" |
| `tax_rate` | Taux de TVA (%) | "0" |
| `delivery_fee` | Frais de livraison | "0" |
| `min_order_amount` | Montant minimum | "0" |

---

## ➕ Ajouter un nouveau paramètre

### Via l'interface admin

1. Allez dans **Admin → Paramètres** (`/admin/settings.php`)
2. Ajoutez un nouveau champ dans le formulaire
3. Sauvegardez

### Via SQL

```sql
INSERT INTO settings (setting_key, setting_value)
VALUES ('facebook_url', 'https://facebook.com/votre-page');
```

### Utiliser le nouveau paramètre

```php
<?php
$facebookUrl = getSetting('facebook_url', '#');
?>

<a href="<?php echo $facebookUrl; ?>" target="_blank">
    <i class="fab fa-facebook"></i> Facebook
</a>
```

---

## 🔄 Mise à jour des paramètres

### Depuis l'interface admin

1. `/admin/settings.php`
2. Modifier les valeurs
3. Cliquer "Enregistrer"
4. **Les changements sont immédiats** sur tout le site!

### Via code PHP

```php
<?php
// Dans un script admin
$stmt = $conn->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES (?, ?)");
$stmt->execute(['restaurant_name', 'Nouveau Nom']);
?>
```

---

## ⚠️ Important

### Cache

Les paramètres sont chargés à chaque requête via `config.php`. Aucun cache n'est utilisé actuellement.

**Pour ajouter un cache (optionnel):**
```php
// Dans settings_loader.php
$cacheFile = __DIR__ . '/../../cache/settings.json';

if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 300) {
    // Utiliser le cache (5 minutes)
    $system_settings = json_decode(file_get_contents($cacheFile), true);
} else {
    // Charger depuis DB
    // ... code existant ...
    // Sauvegarder en cache
    file_put_contents($cacheFile, json_encode($system_settings));
}
```

### Sécurité

- ✅ Les valeurs sont échappées avec `htmlspecialchars()` lors de la sauvegarde
- ✅ Les clés sont validées avec `preg_replace()`
- ✅ Utilisez toujours `htmlspecialchars()` lors de l'affichage

**Mauvais:**
```php
echo getSetting('restaurant_name'); // ❌ Risque XSS
```

**Bon:**
```php
echo htmlspecialchars(getSetting('restaurant_name')); // ✅
// Ou utilisez les constantes (déjà sécurisées)
echo RESTAURANT_NAME; // ✅
```

---

## 📝 Checklist d'intégration

Pour utiliser les paramètres sur une nouvelle page:

- [ ] `require_once '../config.php';` est appelé
- [ ] Les paramètres sont disponibles via constantes ou `getSetting()`
- [ ] Les valeurs affichées sont échappées
- [ ] Test: changer un paramètre dans admin et vérifier l'affichage

---

## 🎯 Cas d'usage avancés

### Paramètres JSON

```php
// Sauvegarder un tableau
$socialLinks = [
    'facebook' => 'https://facebook.com/page',
    'instagram' => 'https://instagram.com/page',
    'twitter' => 'https://twitter.com/page'
];

$stmt = $conn->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES (?, ?)");
$stmt->execute(['social_links', json_encode($socialLinks)]);

// Récupérer
$socialLinks = json_decode(getSetting('social_links', '{}'), true);
?>

<div class="social-links">
    <?php foreach ($socialLinks as $platform => $url): ?>
        <a href="<?php echo htmlspecialchars($url); ?>">
            <i class="fab fa-<?php echo $platform; ?>"></i>
        </a>
    <?php endforeach; ?>
</div>
```

### Paramètres multilingues

```php
// Sauvegarder
$stmt = $conn->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES (?, ?)");
$stmt->execute(['welcome_message_fr', 'Bienvenue']);
$stmt->execute(['welcome_message_en', 'Welcome']);

// Utiliser
$lang = $_SESSION['lang'] ?? 'fr';
$welcomeMessage = getSetting("welcome_message_$lang", 'Bienvenue');
?>
```

---

**Créé le:** 2025-10-25
**Dernière mise à jour:** 2025-10-25
**Version:** 1.0.0
**Status:** ✅ Production Ready
