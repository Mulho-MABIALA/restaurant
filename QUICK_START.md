# ⚡ QUICK START - Utilisation Immédiate

Ce guide vous permet d'utiliser les nouveaux systèmes de sécurité **MAINTENANT**, sans tout migrer.

## 🎯 Ce Que Vous Pouvez Utiliser Immédiatement

✅ **Système de Sécurité** - `admin/includes/security.php`
✅ **Service d'Emails** - `admin/classes/EmailService.php`
✅ **Upload Sécurisé** - `admin/classes/SecureUploadService.php`
✅ **Constantes** - `config/constants.php`

---

## 📧 1. UTILISER LE SERVICE D'EMAIL

### Étape 1: Configurer .env

```bash
# Créer le fichier .env
cp .env.example .env
```

Éditer `.env`:
```env
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_ENCRYPTION=tls
SMTP_USERNAME=votre-email@gmail.com
SMTP_PASSWORD=votre-mot-de-passe-app
SMTP_FROM_EMAIL=noreply@restaurant.com
SMTP_FROM_NAME=Restaurant Mulho
```

### Étape 2: Utiliser dans Votre Code

**Remplacer dans `commander.php` ou autre:**

```php
<?php
// ANCIEN CODE (à supprimer)
/*
$mail = new PHPMailer(true);
$mail->isSMTP();
$mail->Host = 'smtp.gmail.com';
$mail->Username = 'mulhomabiala29@gmail.com';
$mail->Password = 'khli pyzj ihte qdgu';
// ... beaucoup de lignes ...
*/

// NOUVEAU CODE (ajouter en haut du fichier)
require_once __DIR__ . '/../admin/classes/EmailService.php';

// Créer le service
$emailService = new EmailService();

// Envoyer un email de confirmation de commande
$result = $emailService->sendOrderConfirmation($client_email, [
    'order_id' => $commande_id,
    'client_name' => $nom_client,
    'items' => [
        ['name' => 'Thiéboudienne', 'quantity' => 2, 'price' => 2500, 'total' => 5000],
        ['name' => 'Yassa Poulet', 'quantity' => 1, 'price' => 2000, 'total' => 2000]
    ],
    'total' => 7000,
    'pickup_mode' => 'Sur place',
    'estimated_time' => '30 minutes'
]);

if ($result) {
    echo "Email envoyé avec succès!";
}
?>
```

**C'est tout!** Le service gère automatiquement:
- Configuration SMTP
- Templates HTML professionnels
- Logging des emails
- Gestion des erreurs

---

## 🔒 2. SÉCURISER VOS SESSIONS

### Dans TOUS vos fichiers admin

**Au lieu de:**
```php
<?php
session_start();

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}
?>
```

**Utiliser:**
```php
<?php
require_once __DIR__ . '/includes/security.php';

// Cette seule ligne:
// - Démarre une session sécurisée
// - Vérifie l'authentification
// - Gère le timeout
// - Protège contre session hijacking
SecurityManager::requireAuthentication();
?>
```

**Gain:** 6 lignes → 2 lignes + sécurité maximale

---

## 🖼️ 3. UPLOAD SÉCURISÉ D'IMAGES

### Dans `ajouter_plat.php` ou `modifier_plat.php`

**Remplacer:**
```php
// ANCIEN CODE (DANGEREUX)
if (isset($_FILES['image'])) {
    $extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
    $nomFichier = time() . '_' . $_FILES['image']['name'];
    move_uploaded_file($_FILES['image']['tmp_name'], '../uploads/' . $nomFichier);
}
```

**Par:**
```php
// NOUVEAU CODE (SÉCURISÉ)
require_once __DIR__ . '/classes/SecureUploadService.php';

if (isset($_FILES['image'])) {
    $uploadService = new SecureUploadService('../uploads/', 2097152); // 2MB max
    $result = $uploadService->uploadImage($_FILES['image'], 'plats');

    if ($result['success']) {
        $image_filename = $result['filename'];
        // Utiliser $image_filename pour l'INSERT en DB
        echo "Image uploadée: " . $image_filename;
    } else {
        echo "Erreur: " . $result['message'];
    }
}
```

**Sécurité ajoutée:**
- ✅ Validation type MIME
- ✅ Validation contenu image
- ✅ Nom de fichier aléatoire sécurisé
- ✅ Optimisation automatique
- ✅ Protection contre scripts malveillants

---

## 🔢 4. UTILISER LES CONSTANTES

### Dans n'importe quel fichier

**Ajouter en haut:**
```php
<?php
require_once __DIR__ . '/../config/constants.php';
?>
```

**Utiliser:**
```php
// Au lieu de chiffres magiques
if ($distance > 150) { // 150 quoi?? Pourquoi 150??

// Utiliser
if ($distance > GEOFENCE_RADIUS_METERS) { // Clair!

// Au lieu de
if ($people > 50) {

// Utiliser
if ($people > MAX_RESTAURANT_CAPACITY) {

// Formater une devise
echo formatCurrency(15000); // Affiche: 15 000 FCFA

// Formater une date
echo formatDate($date); // Affiche: 24/01/2025

// Logger un message
logMessage("Commande créée", 'info');
```

---

## 🛡️ 5. PROTECTION CSRF (5 MINUTES)

### Pour TOUS vos formulaires

**Ajouter dans le formulaire:**
```html
<form method="POST" action="update.php">
    <!-- TOKEN CSRF (1 ligne à ajouter) -->
    <input type="hidden" name="csrf_token"
           value="<?= SecurityManager::generateCSRFToken() ?>">

    <!-- Vos champs existants -->
    <input type="text" name="nom">
    <button type="submit">Envoyer</button>
</form>
```

**Dans le fichier de traitement (update.php):**
```php
<?php
require_once __DIR__ . '/includes/security.php';

SecurityManager::requireAuthentication();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // VALIDATION CSRF (2 lignes à ajouter)
    if (!SecurityManager::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        die('Token CSRF invalide');
    }

    // Votre code existant...
    $nom = $_POST['nom'];
    // ...
}
?>
```

**Protection contre:** Attaques CSRF qui permettraient à un site malveillant de faire des actions en votre nom.

---

## 🎨 6. EXEMPLE COMPLET: Sécuriser `supprimer_plat.php`

### AVANT (30 secondes de travail)

```php
<?php
require_once('../config.php');

$id = $_GET['id'] ?? null; // DANGEREUX!

if ($id) {
    $stmt = $conn->prepare("DELETE FROM plats WHERE id = ?");
    $stmt->execute([$id]);
}

header("Location: gestion_plats.php");
```

**Problèmes:**
- ❌ Pas d'authentification
- ❌ Utilise GET au lieu de POST
- ❌ Pas de CSRF
- ❌ Pas de validation

### APRÈS (VERSION SÉCURISÉE)

```php
<?php
require_once '../config.php';
require_once 'includes/security.php';

// Vérifier auth
SecurityManager::requireAuthentication();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérifier CSRF
    if (!SecurityManager::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        die('Token invalide');
    }

    // Valider l'ID
    $id = SecurityManager::sanitizeInput($_POST['id'], 'int');

    if ($id > 0) {
        try {
            $stmt = $conn->prepare("DELETE FROM plats WHERE id = ?");
            $stmt->execute([$id]);

            $_SESSION['success'] = 'Plat supprimé';
        } catch (PDOException $e) {
            error_log("Delete error: " . $e->getMessage());
            $_SESSION['error'] = 'Erreur lors de la suppression';
        }
    }
}

header("Location: gestion_plats.php");
exit;
```

**Modifier le lien de suppression:**
```php
<!-- AVANT -->
<a href="supprimer_plat.php?id=<?= $plat_id ?>">Supprimer</a>

<!-- APRÈS -->
<form method="POST" action="supprimer_plat.php" style="display:inline;">
    <input type="hidden" name="csrf_token"
           value="<?= SecurityManager::generateCSRFToken() ?>">
    <input type="hidden" name="id" value="<?= $plat_id ?>">
    <button type="submit" onclick="return confirm('Supprimer ce plat?')">
        Supprimer
    </button>
</form>
```

---

## ⚡ CHECKLIST RAPIDE

Pour chaque fichier que vous modifiez:

### Fichiers Admin
- [ ] Ajouter `require_once 'includes/security.php'`
- [ ] Remplacer `session_start()` par `SecurityManager::requireAuthentication()`
- [ ] Ajouter token CSRF dans tous les formulaires
- [ ] Valider le token dans le traitement

### Uploads de Fichiers
- [ ] Remplacer par `SecureUploadService`
- [ ] Vérifier les erreurs retournées
- [ ] Utiliser le filename retourné

### Emails
- [ ] Remplacer PHPMailer direct par `EmailService`
- [ ] Configurer `.env` avec vos credentials
- [ ] Supprimer les credentials hardcodés

### Nombres Magiques
- [ ] Ajouter `require_once 'config/constants.php'`
- [ ] Remplacer les nombres par des constantes

---

## 🔥 ACTIONS URGENTES (FAIRE MAINTENANT!)

### 1. Sécuriser les Credentials (5 minutes)

```bash
# Créer .env
cp .env.example .env

# Éditer et remplir
nano .env
```

**Aller sur:** https://myaccount.google.com/apppasswords
1. Créer nouveau mot de passe d'application
2. Copier le mot de passe dans `.env`
3. **SUPPRIMER les anciens mots de passe du code!**

### 2. Vérifier .gitignore

```bash
# Vérifier que .env n'est PAS commité
git status

# Si .env apparaît, c'est DANGEREUX!
# Ajouter dans .gitignore:
echo ".env" >> .gitignore
git add .gitignore
git commit -m "Add .env to gitignore"
```

### 3. Premier Test

```bash
# Tester l'envoi d'email
php -r "
require 'admin/classes/EmailService.php';
\$email = new EmailService();
echo 'Test envoi email...';
"
```

---

## 📞 BESOIN D'AIDE?

### Problème avec EmailService

```bash
# Activer le mode debug
# Dans EmailService.php, ligne ~150
$this->mailer->SMTPDebug = 2;
```

### Problème avec Sessions

```bash
# Vérifier les logs
tail -f logs/app.log
```

### Problème avec Upload

```bash
# Vérifier permissions
chmod 755 uploads/
ls -la uploads/
```

---

## 🎓 PROCHAINES ÉTAPES

Une fois que vous êtes à l'aise:

1. ✅ **Lire** [SECURITY_IMPROVEMENTS.md](SECURITY_IMPROVEMENTS.md) - Vue d'ensemble
2. ✅ **Suivre** [MIGRATION_GUIDE.md](MIGRATION_GUIDE.md) - Migration complète
3. ✅ **Refactoriser** login.php avec AuthenticationManager
4. ✅ **Tester** tous les flux utilisateur
5. ✅ **Déployer** en production

---

**💡 Astuce:** Commencez par sécuriser **un seul fichier** (par exemple `gestion_plats.php`), testez-le bien, puis répliquez sur les autres.

**⚠️ Rappel:** Les mots de passe actuels sont **COMPROMIS**. Régénérez-les MAINTENANT!

---

Créé le: 2025-01-24
Durée estimée: 30-60 minutes pour les bases
