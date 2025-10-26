# 📘 GUIDE DE MIGRATION - SYSTÈME SÉCURISÉ

Ce guide explique comment migrer votre code existant pour utiliser les nouveaux systèmes de sécurité.

---

## ✅ FICHIERS CRÉÉS

### 1. Système de Sécurité
📁 `admin/includes/security.php`
- Gestion des sessions sécurisées
- Protection CSRF
- Rate limiting
- Validation des entrées

### 2. Service d'Emails
📁 `admin/classes/EmailService.php`
- Envoi centralisé d'emails
- Templates HTML
- Logging automatique

### 3. Gestionnaire d'Authentification
📁 `admin/classes/AuthenticationManager.php`
- Login/Logout
- 2FA
- Réinitialisation de mot de passe
- Protection contre brute force

### 4. Upload Sécurisé
📁 `admin/classes/SecureUploadService.php`
- Validation MIME type
- Validation de contenu
- Optimisation automatique
- Noms de fichiers sécurisés

### 5. Configuration Centralisée
📁 `config/constants.php`
- Toutes les constantes du projet
- Fonctions utilitaires
- Configuration unique

---

## 🔄 MIGRATION ÉTAPE PAR ÉTAPE

### ÉTAPE 1: Sécuriser les Sessions

#### ❌ AVANT (dans chaque fichier)
```php
<?php
session_start();

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}
```

#### ✅ APRÈS
```php
<?php
require_once __DIR__ . '/includes/security.php';

// Initialise la session sécurisée ET vérifie l'authentification
SecurityManager::requireAuthentication();
```

**Fichiers à modifier:**
- Tous les fichiers admin (50+ fichiers)

---

### ÉTAPE 2: Refactoriser la Page de Login

#### ❌ AVANT (`admin/login.php` - 714 lignes)
```php
// Tout mélangé: HTML, PHP, SQL, emails...
```

#### ✅ APRÈS
```php
<?php
require_once '../config.php';
require_once 'classes/AuthenticationManager.php';

SecurityManager::initSecureSession();

$auth = new AuthenticationManager($conn);
$error = '';

// Protection CSRF
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!SecurityManager::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token de sécurité invalide';
    } else {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        // ÉTAPE 1: Authentification
        $result = $auth->authenticate($username, $password);

        if ($result['success']) {
            // ÉTAPE 2: Envoyer code 2FA
            $codeResult = $auth->sendTwoFactorCode($result['admin']);

            if ($codeResult['success']) {
                $_SESSION['2fa_code'] = $codeResult['code'];
                $_SESSION['2fa_expiry'] = time() + AuthenticationManager::CODE_EXPIRY;
                $_SESSION['pending_admin'] = $result['admin'];

                // Rediriger vers page 2FA
                header('Location: verify_2fa.php');
                exit;
            }
        }

        $error = $result['message'];
    }
}

// Token CSRF pour le formulaire
$csrf_token = SecurityManager::generateCSRFToken();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Connexion Admin</title>
</head>
<body>
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <input type="text" name="username" placeholder="Nom d'utilisateur" required>
        <input type="password" name="password" placeholder="Mot de passe" required>
        <button type="submit">Se connecter</button>
    </form>
</body>
</html>
```

---

### ÉTAPE 3: Sécuriser les Uploads

#### ❌ AVANT (`admin/ajouter_plat.php`)
```php
if (isset($_FILES['image'])) {
    $extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
    $nomFichier = time() . '_' . $_FILES['image']['name'];
    move_uploaded_file($_FILES['image']['tmp_name'], '../uploads/' . $nomFichier);
}
```

#### ✅ APRÈS
```php
<?php
require_once 'classes/SecureUploadService.php';

$uploadService = new SecureUploadService('../uploads/', MAX_IMAGE_UPLOAD_SIZE);

if (isset($_FILES['image'])) {
    $result = $uploadService->uploadImage($_FILES['image'], 'plats');

    if ($result['success']) {
        $image_filename = $result['filename'];
        // Continuer avec l'insertion en DB
    } else {
        $error = $result['message'];
    }
}
```

---

### ÉTAPE 4: Utiliser le Service d'Email

#### ❌ AVANT (code dupliqué dans 3 fichiers)
```php
$mail = new PHPMailer(true);
$mail->isSMTP();
$mail->Host = 'smtp.gmail.com';
$mail->Username = 'mulhomabiala29@gmail.com'; // EXPOSÉ!
$mail->Password = 'khli pyzj ihte qdgu';     // EXPOSÉ!
// ... 20 lignes de config ...
```

#### ✅ APRÈS
```php
<?php
require_once 'classes/EmailService.php';

$emailService = new EmailService();

// Envoyer une confirmation de commande
$emailService->sendOrderConfirmation('client@email.com', [
    'order_id' => 123,
    'client_name' => 'Jean Dupont',
    'items' => $cart_items,
    'total' => $total,
    'pickup_mode' => 'Sur place',
    'estimated_time' => '30 minutes'
]);
```

---

### ÉTAPE 5: Utiliser les Constantes

#### ❌ AVANT
```php
$restaurant_lat = 14.6806968;  // Quoi? Pourquoi?
$restaurant_lng = -17.4480072;
$allowed_radius = 150;         // 150 quoi?

if ($total > 50) {             // 50 quoi? Pourquoi 50?
    // ...
}
```

#### ✅ APRÈS
```php
<?php
require_once '../config/constants.php';

$restaurant_lat = RESTAURANT_LATITUDE;
$restaurant_lng = RESTAURANT_LONGITUDE;
$allowed_radius = GEOFENCE_RADIUS_METERS;

if ($total > MAX_RESTAURANT_CAPACITY) {
    // Message clair et configurable
}
```

---

### ÉTAPE 6: Protection CSRF sur Toutes les Actions

#### ❌ AVANT (`admin/supprimer_plat.php`)
```php
<?php
require_once('../config.php');

$id = $_GET['id'] ?? null; // DANGEREUX! Pas de CSRF!

if ($id) {
    $stmt = $conn->prepare("DELETE FROM plats WHERE id = ?");
    $stmt->execute([$id]);
}

header("Location: gestion_plats.php");
```

#### ✅ APRÈS
```php
<?php
require_once '../config.php';
require_once 'includes/security.php';

SecurityManager::requireAuthentication();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérifier le token CSRF
    if (!SecurityManager::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        die('Token CSRF invalide');
    }

    $id = SecurityManager::sanitizeInput($_POST['id'], 'int');

    if ($id > 0) {
        try {
            $stmt = $conn->prepare("DELETE FROM plats WHERE id = ?");
            $stmt->execute([$id]);

            $_SESSION['success_message'] = 'Plat supprimé avec succès';
        } catch (PDOException $e) {
            error_log("Delete error: " . $e->getMessage());
            $_SESSION['error_message'] = 'Erreur lors de la suppression';
        }
    }
}

header("Location: gestion_plats.php");
exit;
```

**Dans le formulaire HTML:**
```html
<form method="POST" action="supprimer_plat.php">
    <input type="hidden" name="csrf_token" value="<?= SecurityManager::generateCSRFToken() ?>">
    <input type="hidden" name="id" value="<?= $plat_id ?>">
    <button type="submit">Supprimer</button>
</form>
```

---

## 🔐 SÉCURISATION DU FICHIER .ENV

### 1. Créer `.env.example` (à commiter dans Git)
```env
# Configuration SMTP
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_ENCRYPTION=tls
SMTP_USERNAME=your-email@gmail.com
SMTP_PASSWORD=your-app-password
SMTP_FROM_EMAIL=noreply@restaurant.com
SMTP_FROM_NAME=Restaurant Mulho

# Email admin
ADMIN_EMAIL=admin@restaurant.com
```

### 2. Ajouter `.env` dans `.gitignore`
```gitignore
# Fichiers de configuration sensibles
.env
.env.local
.env.*.local

# Uploads
uploads/*
!uploads/.gitkeep

# Logs
logs/*
!logs/.gitkeep

# Cache
cache/*
!cache/.gitkeep
```

### 3. **RÉGÉNÉRER VOS MOTS DE PASSE!**
⚠️ Les mots de passe actuels sont COMPROMIS car dans Git!

1. Aller sur https://myaccount.google.com/apppasswords
2. Créer un nouveau mot de passe d'application
3. Mettre à jour `.env`
4. NE JAMAIS commiter `.env` dans Git

---

## 📊 EXEMPLE COMPLET: REFACTORISER `update_statut.php`

### ❌ AVANT
```php
<?php
require_once('../config.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $commande_id = (int) $_POST['commande_id'];
    $statut = $_POST['statut'];

    $stmt = $conn->prepare("UPDATE commandes SET statut = :statut WHERE id = :id");
    $stmt->execute([':statut' => $statut, ':id' => $commande_id]);

    echo json_encode(['success' => true]);
}
```

### ✅ APRÈS
```php
<?php
require_once '../config.php';
require_once '../config/constants.php';
require_once 'includes/security.php';

// Vérifier l'authentification
SecurityManager::requireAuthentication();

// Vérifier la permission
SecurityManager::requirePermission($conn, PERMISSION_MANAGE_ORDERS);

// Headers JSON
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Méthode non autorisée']);
    exit;
}

// Vérifier CSRF
if (!SecurityManager::validateCSRFToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'Token CSRF invalide']);
    exit;
}

// Valider les entrées
$commande_id = SecurityManager::sanitizeInput($_POST['commande_id'] ?? '', 'int');
$statut = SecurityManager::sanitizeInput($_POST['statut'] ?? '', 'string');

// Liste des statuts autorisés
$allowed_statuses = [
    ORDER_STATUS_PENDING,
    ORDER_STATUS_CONFIRMED,
    ORDER_STATUS_PREPARING,
    ORDER_STATUS_READY,
    ORDER_STATUS_DELIVERED,
    ORDER_STATUS_CANCELLED
];

if (!$commande_id || !in_array($statut, $allowed_statuses)) {
    http_response_code(400);
    echo json_encode(['error' => 'Paramètres invalides']);
    exit;
}

try {
    $stmt = $conn->prepare("UPDATE commandes SET statut = :statut WHERE id = :id");
    $stmt->execute([':statut' => $statut, ':id' => $commande_id]);

    // Logger l'action
    logMessage("Statut commande #{$commande_id} changé en '{$statut}' par admin #{$_SESSION['admin_id']}", 'info');

    echo json_encode(['success' => true, 'message' => 'Statut mis à jour']);

} catch (PDOException $e) {
    error_log("Update error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erreur lors de la mise à jour']);
}
```

---

## 🎯 CHECKLIST DE MIGRATION

### Phase 1: Préparation (1-2 heures)
- [ ] Créer une branche Git: `git checkout -b security-refactor`
- [ ] Backup de la base de données
- [ ] Copier `.env.example` vers `.env`
- [ ] Régénérer tous les mots de passe Gmail
- [ ] Mettre à jour `.gitignore`

### Phase 2: Fichiers Core (2-3 heures)
- [ ] Ajouter `require_once 'config/constants.php'` dans `config.php`
- [ ] Tester le chargement des constantes
- [ ] Remplacer tous les nombres magiques par des constantes
- [ ] Tester EmailService avec un email de test

### Phase 3: Authentification (4-6 heures)
- [ ] Créer `verify_2fa.php` (nouvelle page)
- [ ] Refactoriser `login.php` pour utiliser `AuthenticationManager`
- [ ] Ajouter `SecurityManager::requireAuthentication()` dans tous les fichiers admin
- [ ] Tester le flow complet de login

### Phase 4: Uploads (2-3 heures)
- [ ] Remplacer uploads dans `ajouter_plat.php`
- [ ] Remplacer uploads dans `modifier_plat.php`
- [ ] Remplacer uploads dans autres fichiers avec upload
- [ ] Tester uploads avec différents types de fichiers

### Phase 5: CSRF Protection (3-4 heures)
- [ ] Ajouter tokens CSRF dans tous les formulaires
- [ ] Modifier tous les endpoints POST pour valider CSRF
- [ ] Changer les actions DELETE de GET vers POST
- [ ] Tester toutes les actions CRUD

### Phase 6: Tests (2-3 heures)
- [ ] Tester login/logout
- [ ] Tester 2FA
- [ ] Tester upload d'images
- [ ] Tester CRUD complet (Create, Read, Update, Delete)
- [ ] Tester protection CSRF
- [ ] Tester rate limiting

### Phase 7: Déploiement
- [ ] Merge la branche dans main
- [ ] Déployer en production
- [ ] Monitorer les logs
- [ ] Vérifier les emails

---

## 📚 RESSOURCES

### Documentation
- [SecurityManager](admin/includes/security.php)
- [EmailService](admin/classes/EmailService.php)
- [AuthenticationManager](admin/classes/AuthenticationManager.php)
- [SecureUploadService](admin/classes/SecureUploadService.php)

### Support
- Issues GitHub: https://github.com/votre-repo/issues
- Documentation PHP: https://www.php.net/

---

## ⚠️ POINTS D'ATTENTION

1. **Ne JAMAIS commiter `.env`**
2. **Tester sur environnement de dev d'abord**
3. **Garder un backup de la DB**
4. **Monitorer les logs après migration**
5. **Tester TOUS les flux utilisateur**

---

Bonne migration! 🚀
