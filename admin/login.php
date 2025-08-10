<?php
session_start();
require_once '../config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$error = '';
$success = '';
$show2FAForm = false;

// Configuration de sécurité
const MAX_LOGIN_ATTEMPTS = 5;
const LOCKOUT_TIME = 900; // 15 minutes
const CODE_EXPIRY_TIME = 300; // 5 minutes
const MAX_CODE_ATTEMPTS = 3;

// Fonction pour nettoyer les tentatives expirées
function cleanExpiredAttempts($conn) {
    $stmt = $conn->prepare("DELETE FROM login_attempts WHERE attempt_time < ?");
    $stmt->execute([time() - LOCKOUT_TIME]);
}

// Fonction pour vérifier si l'IP est bloquée
function isIpBlocked($conn, $ip) {
    cleanExpiredAttempts($conn);
    $stmt = $conn->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND attempt_time > ?");
    $stmt->execute([$ip, time() - LOCKOUT_TIME]);
    return $stmt->fetchColumn() >= MAX_LOGIN_ATTEMPTS;
}

// Fonction pour enregistrer une tentative échouée
function recordFailedAttempt($conn, $ip, $username = null) {
    $stmt = $conn->prepare("INSERT INTO login_attempts (ip_address, username, attempt_time) VALUES (?, ?, ?)");
    $stmt->execute([$ip, $username, time()]);
}

// Fonction pour supprimer les tentatives après succès
function clearFailedAttempts($conn, $ip) {
    $stmt = $conn->prepare("DELETE FROM login_attempts WHERE ip_address = ?");
    $stmt->execute([$ip]);
}

// Fonction pour générer un token CSRF
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Fonction pour vérifier le token CSRF
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Fonction pour hacher les mots de passe (à utiliser lors de la création des comptes)
function hashPassword($password) {
    return password_hash($password, PASSWORD_ARGON2ID, [
        'memory_cost' => 65536, // 64 MB
        'time_cost' => 4,       // 4 iterations
        'threads' => 3          // 3 threads
    ]);
}

// FONCTION DE CORRECTION AUTOMATIQUE DU MOT DE PASSE
function fixPassword($conn, $username, $password) {
    $correctHash = hashPassword($password);
    $stmt = $conn->prepare("UPDATE admin SET password = ? WHERE username = ?");
    $result = $stmt->execute([$correctHash, $username]);
    return $result;
}

// Fonction pour générer un code 2FA plus sécurisé
function generate2FACode() {
    return str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
}

// Fonction d'envoi d'email 2FA améliorée
function send2FACode($email, $code, $username) {
    $mail = new PHPMailer(true);

    try {
        // Configuration SMTP sécurisée
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'mulhomabiala29@gmail.com';
        $mail->Password   = 'khli pyzj ihte qdgu'; // À déplacer dans un fichier de config sécurisé !
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('no-reply@tonrestaurant.com', 'Ton Restaurant - Sécurité');
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = 'Code de vérification - Connexion Admin';
        
        // Template HTML plus professionnel
        $mail->Body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
            <div style='background: linear-gradient(135deg, #ff7200, #ff9500); color: white; padding: 20px; border-radius: 10px 10px 0 0; text-align: center;'>
                <h2 style='margin: 0;'>Code de Vérification</h2>
            </div>
            <div style='background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; border: 1px solid #ddd;'>
                <p>Bonjour <strong>$username</strong>,</p>
                <p>Votre code de vérification pour la connexion admin est :</p>
                <div style='background: #fff; border: 2px solid #ff7200; border-radius: 10px; padding: 20px; text-align: center; margin: 20px 0;'>
                    <span style='font-size: 32px; font-weight: bold; color: #ff7200; letter-spacing: 5px;'>$code</span>
                </div>
                <p><strong>Important :</strong></p>
                <ul style='color: #666;'>
                    <li>Ce code est valable pendant <strong>5 minutes</strong></li>
                    <li>Ne partagez jamais ce code avec personne</li>
                    <li>Si vous n'avez pas demandé ce code, ignorez cet email</li>
                </ul>
                <hr style='margin: 20px 0; border: none; border-top: 1px solid #ddd;'>
                <p style='font-size: 12px; color: #999; text-align: center;'>
                    Cet email a été envoyé automatiquement, ne pas répondre.<br>
                    © " . date('Y') . " Ton Restaurant - Tous droits réservés
                </p>
            </div>
        </div>";

        $mail->AltBody = "Bonjour $username,\n\nVotre code de vérification est : $code\n\nCe code est valable 5 minutes.\n\nNe partagez jamais ce code avec personne.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        throw new Exception("Erreur lors de l'envoi de l'email : {$mail->ErrorInfo}");
    }
}

// Obtenir l'IP du client
$clientIP = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'];

// Vérifier si l'IP est bloquée
if (isIpBlocked($conn, $clientIP)) {
    $error = 'Trop de tentatives de connexion. Veuillez réessayer dans 15 minutes.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Vérification du token CSRF
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token de sécurité invalide. Veuillez recharger la page.';
    } else {
        
        if (isset($_POST['step']) && $_POST['step'] === '2fa') {
            // Étape 2 : Vérification du code 2FA
            $userCode = trim($_POST['2fa_code'] ?? '');
            
            if (empty($userCode)) {
                $error = 'Veuillez saisir le code.';
            } elseif (!isset($_SESSION['2fa_code']) || !isset($_SESSION['2fa_expiry'])) {
                $error = 'Aucune demande de code 2FA en cours.';
            } elseif (time() > $_SESSION['2fa_expiry']) {
                $error = 'Le code a expiré. Veuillez vous reconnecter.';
                session_unset();
            } else {
                // Vérifier le nombre de tentatives pour le code 2FA
                if (!isset($_SESSION['2fa_attempts'])) {
                    $_SESSION['2fa_attempts'] = 0;
                }
                
                if ($_SESSION['2fa_attempts'] >= MAX_CODE_ATTEMPTS) {
                    $error = 'Trop de tentatives incorrectes. Veuillez vous reconnecter.';
                    session_unset();
                } elseif (!hash_equals($_SESSION['2fa_code'], $userCode)) {
                    $_SESSION['2fa_attempts']++;
                    $remainingAttempts = MAX_CODE_ATTEMPTS - $_SESSION['2fa_attempts'];
                    $error = "Code incorrect. Il vous reste $remainingAttempts tentative(s).";
                } else {
                    // Code correct - connexion réussie
                 // Code correct - connexion réussie
             
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_username'] = $_SESSION['2fa_username'];
                $_SESSION['login_time'] = time();

               if (isset($_SESSION['admin_data'])) {
    $admin = $_SESSION['admin_data'];
    $_SESSION['admin_id'] = (int)$admin['id'];
    $_SESSION['admin_username'] = $admin['username'];
    $_SESSION['admin_email'] = $admin['email'];
    unset($_SESSION['admin_data']); // Nettoyer les données temporaires
} else {
    // Récupérer les données depuis la base si admin_data n'existe pas
    try {
        $stmt = $conn->prepare("SELECT id, username, email FROM admin WHERE username = ?");
        $stmt->execute([$_SESSION['2fa_username']]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($admin) {
            $_SESSION['admin_id'] = (int)$admin['id'];
            $_SESSION['admin_username'] = $admin['username']; // ou un champ 'name' si il existe
            $_SESSION['admin_email'] = $admin['email'];
        } else {
            error_log("Impossible de récupérer les données admin pour: " . $_SESSION['2fa_username']);
            header('Location: logout.php');
            exit;
        }
    } catch (PDOException $e) {
        error_log("Erreur DB lors de la récupération admin après 2FA : " . $e->getMessage());
        header('Location: logout.php');
        exit;
    }
}


                    // Supprimer les données 2FA et tentatives
                    unset($_SESSION['2fa_code'], $_SESSION['2fa_expiry'], $_SESSION['2fa_username'], $_SESSION['2fa_attempts']);
                    clearFailedAttempts($conn, $clientIP);
                    
                    // Log de connexion réussie
                    error_log("Admin login successful: " . $_SESSION['admin_username'] . " from IP: " . $clientIP);
                    
                    header('Location: dashboard.php');
                    exit;
                }
            }
            $show2FAForm = true;
            
        } elseif (isset($_POST['step']) && $_POST['step'] === 'resend') {
            // Renvoyer le code 2FA
            if (isset($_SESSION['2fa_username'], $_SESSION['2fa_email'])) {
                if (!isset($_SESSION['last_resend']) || time() - $_SESSION['last_resend'] > 60) {
                    try {
                        $newCode = generate2FACode();
                        $_SESSION['2fa_code'] = $newCode;
                        $_SESSION['2fa_expiry'] = time() + CODE_EXPIRY_TIME;
                        $_SESSION['2fa_attempts'] = 0; // Reset attempts
                        $_SESSION['last_resend'] = time();
                        
                        send2FACode($_SESSION['2fa_email'], $newCode, $_SESSION['2fa_username']);
                        $success = 'Un nouveau code a été envoyé.';
                        $show2FAForm = true;
                    } catch (Exception $e) {
                        $error = "Erreur lors du renvoi : " . $e->getMessage();
                        $show2FAForm = true;
                    }
                } else {
                    $error = 'Veuillez attendre 60 secondes avant de renvoyer un code.';
                    $show2FAForm = true;
                }
            }
            
        } else {
            // Étape 1 : Connexion username + password
            $username = trim($_POST['username'] ?? '');
            $password = trim($_POST['password'] ?? '');

            if ($username === '' || $password === '') {
                $error = 'Veuillez remplir tous les champs.';
                recordFailedAttempt($conn, $clientIP, $username);
            } else {
                // Requête sécurisée avec préparation
               $stmt = $conn->prepare("SELECT id, username, password, email, failed_attempts, locked_until FROM admin WHERE username = ? AND active = 1");
                $stmt->execute([$username]);
                $admin = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($admin) {
                    // DÉBOGAGE : Afficher les informations
                    error_log("User found: " . $username);
                    error_log("Password from DB: " . substr($admin['password'], 0, 50) . "...");
                    error_log("Entered password: " . $password);
                    
                    // Vérifier si le compte est verrouillé
                    if ($admin['locked_until'] && time() < $admin['locked_until']) {
                        $error = 'Compte temporairement verrouillé. Réessayez plus tard.';
                        recordFailedAttempt($conn, $clientIP, $username);
                    } 
                    // CORRECTION AUTOMATIQUE pour "mulho" avec mot de passe "1010"
                    elseif ($username === 'mulho' && $password === '1010' && strpos($admin['password'], '[HASH_SERA_GÉNÉRÉ]') !== false) {
                        // Le hash est incorrect, le corriger automatiquement
                        fixPassword($conn, $username, $password);
                        $success = 'Mot de passe corrigé automatiquement. Veuillez vous reconnecter.';
                        error_log("Password fixed for user: $username");
                    }
                    elseif (password_verify($password, $admin['password'])) {
                        // Mot de passe correct - réinitialiser les tentatives échouées
                        $stmt = $conn->prepare("UPDATE admin SET failed_attempts = 0, locked_until = NULL WHERE id = ?");
                        $stmt->execute([$admin['id']]);
                        // Sauvegarder les infos admin pour l'étape 2FA
                        $_SESSION['admin_data'] = $admin;

                        // Générer et envoyer le code 2FA
                        $code = generate2FACode();
                        $_SESSION['2fa_code'] = $code;
                        $_SESSION['2fa_expiry'] = time() + CODE_EXPIRY_TIME;
                        $_SESSION['2fa_username'] = $username;
                        $_SESSION['2fa_email'] = $admin['email'];
                        $_SESSION['2fa_attempts'] = 0;

                        try {
                            send2FACode($admin['email'], $code, $username);
                            $show2FAForm = true;
                            $success = 'Un code de vérification a été envoyé à votre adresse email.';
                        } catch (Exception $e) {
                            $error = "Erreur lors de l'envoi de l'email : " . $e->getMessage();
                        }
                    } else {
                        // Mot de passe incorrect - incrémenter les tentatives
                        $newFailedAttempts = $admin['failed_attempts'] + 1;
                        $lockedUntil = null;
                        
                        if ($newFailedAttempts >= 5) {
                            $lockedUntil = time() + 1800; // Verrouiller 30 minutes
                        }
                        
                        $stmt = $conn->prepare("UPDATE admin SET failed_attempts = ?, locked_until = ? WHERE id = ?");
                        $stmt->execute([$newFailedAttempts, $lockedUntil, $admin['id']]);
                        
                        $error = 'Mot de passe incorrect.';
                        recordFailedAttempt($conn, $clientIP, $username);
                        
                        // DÉBOGAGE supplémentaire
                        error_log("Password verification failed for user: $username");
                    }
                } else {
                    $error = 'Nom d\'utilisateur introuvable.';
                    recordFailedAttempt($conn, $clientIP, $username);
                    error_log("User not found: $username");
                }
            }
        }
    }
}

// Générer un nouveau token CSRF si nécessaire
$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion Admin - Sécurisée</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        *{
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'poppins', sans-serif;
        }
        
        body {
            background-image: url('bg.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            width: 100%;
            font-family: 'Poppins', sans-serif;
        }
        
        .container{
            height: 100vh;
            width: 100%;
            animation: anime 20s linear infinite;
        } 
        
        @keyframes anime {
            100% {
                filter: hue-rotate(360deg);
            }  
        }
        
        form{
            display: flex;
            align-items: center;
            justify-content: center;
            margin-top: 5%;
        }
        
        .content{
            width: 400px;
            height: auto;
            min-height: 450px;
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(15px);
            border: 2px solid rgba(255, 114, 0, 0.4);
            border-radius: 25px;
            padding: 50px 40px;
            box-shadow: 0 25px 45px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
        }
        
        h2{
            width: 100%;
            text-align: center;
            color: #ff7200;
            font-size: 24px;
            font-weight: 700;
            background: linear-gradient(135deg, #fff, #f8f9fa);
            border-radius: 15px;
            padding: 15px;
            margin-bottom: 10px;
            box-shadow: 0 10px 25px rgba(255, 114, 0, 0.2);
            border: 1px solid rgba(255, 114, 0, 0.2);
        }
        
        ::placeholder{
            color: #fff;
        }
        
        .inputBox{
            position: relative;
            width: 100%;
            margin-top: 25px;
        }
        
        .inputBox input {
            width: 100%;
            padding: 20px 15px 10px;
            background: rgba(255, 255, 255, 0.1);
            border: none;
            border-bottom: 2px solid rgba(255, 114, 0, 0.5);
            border-radius: 10px 10px 0 0;
            font-size: 16px;
            outline: none;
            color: #fff;
            transition: all 0.3s ease;
        }
        
        .inputBox input:focus {
            background: rgba(255, 255, 255, 0.15);
            border-bottom-color: #ff7200;
            box-shadow: 0 5px 15px rgba(255, 114, 0, 0.3);
        }
        
        input[type="submit"] {
            width: 100%;
            border: none;
            background: linear-gradient(135deg, #ff7200, #ff9500);
            color: #fff;
            font-size: 18px;
            font-weight: 600;
            border-radius: 25px;
            margin-top: 25px;
            padding: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 8px 25px rgba(255, 114, 0, 0.3);
        }
        
        input[type="submit"]:hover {
            background: linear-gradient(135deg, #ff9500, #ffb347);
            transform: translateY(-2px);
            box-shadow: 0 12px 35px rgba(255, 114, 0, 0.4);
            letter-spacing: 1px;
        }
        
        .error {
            color: #ff4757 !important;
            background: linear-gradient(135deg, #fff, #f8f9fa) !important;
            text-align: center !important;
            margin-top: 15px !important;
            padding: 12px !important;
            border-radius: 15px !important;
            border: 1px solid rgba(255, 71, 87, 0.3) !important;
            box-shadow: 0 5px 15px rgba(255, 71, 87, 0.2) !important;
            font-weight: 600 !important;
        }
        
        .success {
            color: #2ed573 !important;
            background: linear-gradient(135deg, #fff, #f8f9fa) !important;
            text-align: center !important;
            margin-top: 15px !important;
            padding: 12px !important;
            border-radius: 15px !important;
            border: 1px solid rgba(46, 213, 115, 0.3) !important;
            box-shadow: 0 5px 15px rgba(46, 213, 115, 0.2) !important;
            font-weight: 600 !important;
        }
        
        .resend-btn {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 114, 0, 0.5);
            color: #fff;
            padding: 10px 20px;
            border-radius: 15px;
            cursor: pointer;
            margin-top: 15px;
            transition: all 0.3s ease;
            font-size: 14px;
        }
        
        .resend-btn:hover {
            background: rgba(255, 114, 0, 0.2);
            border-color: #ff7200;
        }
        
        .back-link {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            background: linear-gradient(135deg, #ff7200, #ff9500);
            color: #fff;
            padding: 15px 30px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            border: 2px solid transparent;
            box-shadow: 0 8px 32px rgba(255, 114, 0, 0.3);
            backdrop-filter: blur(10px);
        }
        
        .back-link:hover {
            background: linear-gradient(135deg, #ff9500, #ffb347);
            transform: translateX(-50%) translateY(-8px);
            box-shadow: 0 16px 48px rgba(255, 114, 0, 0.5);
        }
        
        .info-text {
            color: #fff;
            text-align: center;
            margin-top: 15px;
            font-size: 14px;
            opacity: 0.9;
        }
        
        /* Compteur de temps restant */
        .countdown {
            color: #ff7200;
            font-weight: bold;
            text-align: center;
            margin-top: 10px;
            font-size: 14px;
        }
        
        /* Indicateur de force du mot de passe */
        .password-strength {
            height: 4px;
            width: 100%;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 2px;
            margin-top: 8px;
            overflow: hidden;
        }
        
        .password-strength-bar {
            height: 100%;
            width: 0%;
            transition: all 0.3s ease;
            border-radius: 2px;
        }
        
        .strength-weak { background: #ff4757; width: 25%; }
        .strength-fair { background: #ffa502; width: 50%; }
        .strength-good { background: #ff7200; width: 75%; }
        .strength-strong { background: #2ed573; width: 100%; }
    </style>
</head>
<body>

<div class="container">
    <form method="post" action="" id="loginForm">
        <div class="content">
            <h2><?= $show2FAForm ? 'Vérification 2FA' : 'Connexion Admin' ?></h2>

            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

            <?php if ($error): ?>
                <div class="error">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="success">
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <?php if ($show2FAForm): ?>
                <input type="hidden" name="step" value="2fa">
                <div class="inputBox">
                    <input type="text" name="2fa_code" id="2fa_code" maxlength="6" required 
                           placeholder="Code de vérification (6 chiffres)" pattern="[0-9]{6}"
                           autocomplete="one-time-code">
                </div>
                <div class="inputBox">
                    <input type="submit" value="Valider le code">
                </div>
                
                <!-- Compteur de temps restant -->
                <div id="countdown" class="countdown"></div>
                
                <!-- Bouton de renvoi -->
                <div style="text-align: center;">
                    <button type="submit" name="step" value="resend" class="resend-btn">
                        📧 Renvoyer le code
                    </button>
                </div>
                
                <p class="info-text">
                    🔒 Un code à 6 chiffres vous a été envoyé.<br>
                    Vérifiez vos spams si nécessaire.
                </p>

            <?php else: ?>
                <div class="inputBox">
                    <input type="text" name="username" id="username" required 
                           placeholder="Nom d'utilisateur" autocomplete="username">
                </div>
                <div class="inputBox">
                    <input type="password" name="password" id="password" required 
                           placeholder="Mot de passe" autocomplete="current-password">
                    <div class="password-strength">
                        <div class="password-strength-bar" id="strengthBar"></div>
                    </div>
                </div>
                <div class="inputBox">
                    <input type="submit" value="Se connecter">
                </div>
                
                <p class="info-text">
                    🔐 Connexion sécurisée avec authentification à deux facteurs
                </p>
            <?php endif; ?>
        </div>
    </form>
    
    <a href="../index.php" class="back-link">← Retour au site</a>
</div>

<script>
// Compteur pour le code 2FA
<?php if ($show2FAForm && isset($_SESSION['2fa_expiry'])): ?>
function updateCountdown() {
    const expiry = <?= $_SESSION['2fa_expiry'] ?>;
    const now = Math.floor(Date.now() / 1000);
    const remaining = expiry - now;
    
    const countdownElement = document.getElementById('countdown');
    
    if (remaining > 0) {
        const minutes = Math.floor(remaining / 60);
        const seconds = remaining % 60;
        countdownElement.textContent = `⏱️ Code valable encore ${minutes}:${seconds.toString().padStart(2, '0')}`;
        setTimeout(updateCountdown, 1000);
    } else {
        countdownElement.innerHTML = '<span style="color: #ff4757;">⚠️ Code expiré - Veuillez vous reconnecter</span>';
    }
}
updateCountdown();
<?php endif; ?>

// Indicateur de force du mot de passe
document.addEventListener('DOMContentLoaded', function() {
    const passwordInput = document.getElementById('password');
    const strengthBar = document.getElementById('strengthBar');
    
    if (passwordInput && strengthBar) {
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;
            
            // Critères de force
            if (password.length >= 8) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            
            // Appliquer les classes CSS
            strengthBar.className = 'password-strength-bar';
            if (strength >= 1) strengthBar.classList.add('strength-weak');
            if (strength >= 2) strengthBar.classList.add('strength-fair');
            if (strength >= 3) strengthBar.classList.add('strength-good');
            if (strength >= 4) strengthBar.classList.add('strength-strong');
        });
    }
    
    // Auto-focus sur le champ de code 2FA
    const codeInput = document.getElementById('2fa_code');
    if (codeInput) {
        codeInput.focus();
        
        // Formater automatiquement le code (espaces tous les 3 chiffres)
        codeInput.addEventListener('input', function() {
            let value = this.value.replace(/\D/g, ''); // Garder seulement les chiffres
            if (value.length > 6) value = value.substring(0, 6);
            this.value = value;
        });
    }
});

// Protection contre les attaques par force brute côté client
let failedAttempts = 0;
document.getElementById('loginForm').addEventListener('submit', function(e) {
    if (failedAttempts >= 3) {
        const delay = Math.pow(2, failedAttempts - 3) * 1000; // Délai exponentiel
        e.preventDefault();
        alert(`Trop de tentatives. Veuillez attendre ${delay/1000} secondes.`);
        setTimeout(() => {
            this.querySelector('input[type="submit"]').disabled = false;
        }, delay);
        this.querySelector('input[type="submit"]').disabled = true;
    }
});

// Incrémenter le compteur en cas d'erreur
<?php if ($error && !$show2FAForm): ?>
failedAttempts++;
<?php endif; ?>
</script>

</body>
</html>