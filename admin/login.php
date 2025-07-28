<?php
require_once '../config.php';
session_start();
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


$error = '';
$show2FAForm = false;

function send2FACode($email, $code) {
    $mail = new PHPMailer(true);

    try {
        // Configuration SMTP (ici Gmail)
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'mulhomabiala29@gmail.com';     
        $mail->Password   = 'khli pyzj ihte qdgu';     
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('no-reply@tonrestaurant.com', 'Ton Restaurant');
        $mail->addAddress($email);

        $mail->isHTML(false);
        $mail->Subject = 'Votre code de connexion 2FA';
        $mail->Body    = "Votre code de connexion est : $code\nCe code est valable 5 minutes.";

        $mail->send();
    } catch (Exception $e) {
        throw new Exception("Le message n'a pas pu être envoyé. Erreur: {$mail->ErrorInfo}");
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        } elseif ($userCode !== $_SESSION['2fa_code']) {
            $error = 'Code incorrect.';
        } else {
            // Code correct
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $_SESSION['2fa_username'];
            // Supprimer données 2FA
            unset($_SESSION['2fa_code'], $_SESSION['2fa_expiry'], $_SESSION['2fa_username']);
            header('Location: dashboard.php');
            exit;
        }
        $show2FAForm = true; // pour réafficher le formulaire en cas d'erreur
    } else {
        // Étape 1 : connexion username + password
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if ($username === '' || $password === '') {
            $error = 'Veuillez remplir tous les champs.';
        } else {
            $stmt = $conn->prepare("SELECT * FROM admin WHERE username = ?");
            $stmt->execute([$username]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($admin) {
                // IMPORTANT : remplacer comparaison simple par password_verify si tu stockes haché !
                // Ex : if (password_verify($password, $admin['password'])) {
                if ($password === $admin['password']) {
                    // Générer un code 2FA (6 chiffres)
                    $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                    $_SESSION['2fa_code'] = $code;
                    $_SESSION['2fa_expiry'] = time() + 300; // valable 5 minutes
                    $_SESSION['2fa_username'] = $username;

                    // Essayer d'envoyer le mail
                    try {
                        send2FACode($admin['email'], $code);
                        $show2FAForm = true;
                    } catch (Exception $e) {
                        $error = "Erreur lors de l'envoi de l'email : " . $e->getMessage();
                    }
                } else {
                    $error = 'Mot de passe incorrect.';
                }
            } else {
                $error = 'Nom d\'utilisateur introuvable.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
       *{
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'poppins', sans-serif;

}
body {
    background-image: url('bg.jpg'); /* mets ici ton image */
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

    width: 380px;
    height: 420px;
    background: transparent;
    border: 2px solid #ff7200;
    border-radius: 8px;
    padding: 50px 40px;
    border-radius: 20px;
}
h2{
    width: 295px;
    text-align: center;
    color: #ff7200;
    font-size: 22px;
    background: #fff;
    border-radius: 10px;
    padding: 8px;
}
::placeholder{
    color: #fff;
}
.inputBox{
    position: relative;
    width: 300px;
    margin-top: 35px;
}
.inputBox input {
    width: 100%;
    padding: 20px 10px 10px;
    background: transparent;
    border: none;
    border-bottom: 1px solid #ff7200;
    font-size: 1em;
    outline: none;
    color: #fff;
}
input[type="submit"] {
    border: 1px solid #fff;
    background: #ff7200;
    color: #fff;
    font-size: 20px;
    border-radius: 20px;
    margin-top: 20px;
    cursor: pointer;
    transition: 0.5s;
}
input[type="submit"]:hover{
    letter-spacing: 5px;
}

/* Style amélioré pour le lien de retour */
.back-link {
    position: fixed;
    bottom: 30px;
    left: 40%;
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
    position: relative;
    overflow: hidden;
    top: 50px;
}

.back-link::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
    transition: left 0.5s;
}

.back-link:hover::before {
    left: 100%;
}

.back-link:hover {
    background: linear-gradient(135deg, #ff9500, #ffb347);
    transform: translateX(-50%) translateY(-8px);
    box-shadow: 0 16px 48px rgba(255, 114, 0, 0.5);
    letter-spacing: 1px;
}

.back-link:active {
    transform: translateX(-50%) translateY(-4px);
    box-shadow: 0 8px 24px rgba(255, 114, 0, 0.4);
}

/* Amélioration du formulaire */
.content {
    width: 400px;
    height: 450px;
    background: rgba(255, 255, 255, 0.08);
    backdrop-filter: blur(15px);
    border: 2px solid rgba(255, 114, 0, 0.4);
    border-radius: 25px;
    padding: 50px 40px;
    box-shadow: 0 25px 45px rgba(0, 0, 0, 0.1);
    position: relative;
    overflow: hidden;
}

.content::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255, 114, 0, 0.1) 0%, transparent 70%);
    animation: rotate 20s linear infinite;
}

@keyframes rotate {
    0% {
        transform: rotate(0deg);
    }
    100% {
        transform: rotate(360deg);
    }
}

.content > * {
    position: relative;
    z-index: 1;
}

h2 {
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

input[type="submit"]:active {
    transform: translateY(0);
    box-shadow: 0 6px 20px rgba(255, 114, 0, 0.3);
}

/* Amélioration des messages d'erreur */
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

/* ===== MEDIA QUERIES POUR LA RESPONSIVITÉ ===== */

/* Tablettes (768px et moins) */
@media (max-width: 768px) {
    .container {
        padding: 20px;
    }
    
    form {
        margin-top: 0;
        width: 100%;
    }
    
    .content {
        width: 100%;
        max-width: 350px;
        height: auto;
        min-height: 420px;
        padding: 40px 30px;
        margin: 0 auto;
    }
    
    .inputBox {
        width: 100%;
        margin-top: 25px;
    }
    
    h2 {
        font-size: 20px;
        padding: 12px;
    }
    
    .inputBox input {
        font-size: 16px;
        padding: 18px 12px 8px;
    }
    
    input[type="submit"] {
        font-size: 16px;
        padding: 12px;
        margin-top: 20px;
    }
    
    .back-link {
        position: fixed;
        bottom: 20px;
        left: 50%;
        transform: translateX(-50%);
        padding: 12px 25px;
        font-size: 14px;
        top: auto;
    }
}

/* Mobile (480px et moins) */
@media (max-width: 480px) {
    body {
        background-attachment: scroll;
    }
    
    .container {
        padding: 15px;
        height: auto;
        min-height: 100vh;
    }
    
    .content {
        width: 100%;
        max-width: 300px;
        height: auto;
        min-height: 380px;
        padding: 30px 25px;
        border-radius: 20px;
    }
    
    h2 {
        font-size: 18px;
        padding: 10px;
        margin-bottom: 15px;
    }
    
    .inputBox {
        margin-top: 20px;
    }
    
    .inputBox input {
        font-size: 14px;
        padding: 15px 10px 6px;
    }
    
    input[type="submit"] {
        font-size: 14px;
        padding: 10px;
        margin-top: 15px;
    }
    
    input[type="submit"]:hover {
        letter-spacing: 2px;
    }
    
    .back-link {
        bottom: 15px;
        padding: 10px 20px;
        font-size: 12px;
        border-radius: 30px;
    }
    
    .back-link:hover {
        letter-spacing: 0.5px;
    }
    
    .error {
        padding: 10px !important;
        font-size: 14px !important;
        margin-top: 10px !important;
    }
}

/* Très petits écrans (320px et moins) */
@media (max-width: 320px) {
    .container {
        padding: 10px;
    }
    
    .content {
        max-width: 280px;
        padding: 25px 20px;
        min-height: 350px;
    }
    
    h2 {
        font-size: 16px;
        padding: 8px;
    }
    
    .inputBox {
        margin-top: 15px;
    }
    
    .inputBox input {
        font-size: 13px;
        padding: 12px 8px 5px;
    }
    
    input[type="submit"] {
        font-size: 13px;
        padding: 8px;
        margin-top: 12px;
    }
    
    .back-link {
        bottom: 10px;
        padding: 8px 15px;
        font-size: 11px;
    }
}

/* Écrans plus larges (1200px et plus) */
@media (min-width: 1200px) {
    .content {
        width: 450px;
        height: 500px;
        padding: 60px 50px;
    }
    
    h2 {
        font-size: 26px;
        padding: 18px;
    }
    
    .inputBox {
        margin-top: 40px;
    }
    
    .inputBox input {
        font-size: 18px;
        padding: 22px 18px 12px;
    }
    
    input[type="submit"] {
        font-size: 20px;
        padding: 18px;
        margin-top: 30px;
    }
    
    .back-link {
        padding: 18px 35px;
        font-size: 18px;
    }
}

/* Orientation paysage sur mobile */
@media (max-height: 600px) and (orientation: landscape) {
    .container {
        height: auto;
        min-height: 100vh;
        padding: 10px;
    }
    
    form {
        margin-top: 0;
    }
    
    .content {
        height: auto;
        min-height: 300px;
        padding: 20px 30px;
    }
    
    .inputBox {
        margin-top: 15px;
    }
    
    h2 {
        margin-bottom: 5px;
    }
    
    .back-link {
        position: relative;
        bottom: auto;
        left: auto;
        transform: none;
        margin-top: 15px;
        display: inline-block;
    }
}

    </style>
</head>
<body>

<div class="container">
    <form method="post" action="">
        <div class="content">
            <h2>Connexion Admin</h2>

            <?php if ($error): ?>
                <div class="error" style="color: red; background: #fff; text-align:center; margin-top:10px; padding: 8px; border-radius: 10px;">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($show2FAForm): ?>
                <input type="hidden" name="step" value="2fa">
                <div class="inputBox">
                    <input type="text" name="2fa_code" id="2fa_code" maxlength="6" required placeholder="Code de vérification">
                </div>
                <div class="inputBox">
                    <input type="submit" value="Valider">
                </div>
                <p style="color: #fff; text-align: center; margin-top: 10px;">Un code à 6 chiffres vous a été envoyé par email.</p>

            <?php else: ?>
                <div class="inputBox">
                    <input type="text" name="username" id="username" required placeholder="Nom d'utilisateur">
                </div>
                <div class="inputBox">
                    <input type="password" name="password" id="password" required placeholder="Mot de passe">
                </div>
                <div class="inputBox">
                    <input type="submit" value="Se connecter">
                </div>
            <?php endif; ?>
        </div>
    </form>
    
    <!-- Lien de retour en bas -->
    <a href="../index.php" class="back-link">← Retour au site</a>
</div>

</body>
</html>