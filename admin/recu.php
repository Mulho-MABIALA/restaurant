<?php
// Activer le débogage
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../config.php';

require_once '../vendor/autoload.php'; 

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// === CHARGEMENT DE LA CONFIGURATION .ENV ===
function loadEnv($file = '.env') {
    $envFile = __DIR__ . '/' . $file;
    
    if (!file_exists($envFile)) {
        error_log(".env file not found: " . $envFile);
        return false;
    }

    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) {
            continue;
        }

        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value, " \t\n\r\0\x0B\"'"); 

        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
        putenv("$name=$value");
    }

    return true;
}

// Charger la configuration .env
$env_loaded = loadEnv();

// Configuration email depuis .env avec validation
$smtp_host = $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com';
$smtp_port = intval($_ENV['SMTP_PORT'] ?? 587);
$smtp_username = $_ENV['SMTP_USERNAME'] ?? '';
$smtp_password = $_ENV['SMTP_PASSWORD'] ?? '';
$from_email = $_ENV['FROM_EMAIL'] ?? $smtp_username;
$from_name = $_ENV['FROM_NAME'] ?? 'Mon Restaurant';

// Vérification des paramètres requis
if (empty($smtp_username) || empty($smtp_password)) {
    error_log("❌ ERREUR: Configuration SMTP incomplète dans .env");
    error_log("SMTP_USERNAME: " . ($smtp_username ? "✅" : "❌ MANQUANT"));
    error_log("SMTP_PASSWORD: " . ($smtp_password ? "✅" : "❌ MANQUANT"));
}

// Vérification de l'authentification admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

if (!isset($_GET['id'])) {
    header('Location: admin.php');
    exit;
}

$commande_id = intval($_GET['id']);

// === TRAITEMENT ACTIONS AJAX AMÉLIORÉ ===
if (isset($_POST['action']) && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    
    // Nettoyer le buffer de sortie
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, must-revalidate');
    
    try {
        if ($_POST['action'] == 'marquer_paye') {
            $stmt = $conn->prepare("UPDATE commandes SET statut_paiement = 'Payé' WHERE id = ?");
            $success = $stmt->execute([$commande_id]);

            echo json_encode([
                'success' => $success,
                'message' => $success ? 'Statut de paiement mis à jour avec succès' : 'Erreur lors de la mise à jour'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($_POST['action'] == 'envoyer_recu') {
            error_log("=== DÉBUT ENVOI REÇU ===");
            error_log("Commande ID: " . $commande_id);
            
            // Vérification de la configuration avant envoi
            if (empty($smtp_username) || empty($smtp_password)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Configuration SMTP incomplète. Vérifiez votre fichier .env'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            $success = envoyerRecuPaye($commande_id, $conn, $smtp_host, $smtp_port, $smtp_username, $smtp_password, $from_email, $from_name);
            
            echo json_encode([
                'success' => $success,
                'message' => $success ? 'Reçu envoyé par email avec succès' : 'Erreur lors de l\'envoi de l\'email. Vérifiez les logs pour plus de détails.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        echo json_encode([
            'success' => false,
            'message' => 'Action non reconnue'
        ], JSON_UNESCAPED_UNICODE);
        exit;
        
    } catch (Exception $e) {
        error_log("Erreur AJAX: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Erreur système: ' . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// === FONCTION DE TEST POUR DEBUG ===
if (isset($_GET['test_email']) && $_GET['test_email'] == '1') {
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    
    $test_email = $smtp_username;
    $test_sujet = "Test Email - " . date('Y-m-d H:i:s');
    $test_contenu = "<h1>Test Email</h1><p>Ceci est un test d'envoi d'email depuis PHP.</p>";
    
    $result = envoyerEmailAvecDebug($test_email, $test_sujet, $test_contenu, $smtp_host, $smtp_port, $smtp_username, $smtp_password, $from_email, $from_name);
    
    echo json_encode(['success' => $result, 'message' => $result ? 'Test réussi' : 'Test échoué'], JSON_UNESCAPED_UNICODE);
    exit;
}

// === RÉCUPÉRATION DE LA COMMANDE ===
$stmt = $conn->prepare("SELECT * FROM commandes WHERE id = ?");
$stmt->execute([$commande_id]);
$commande = $stmt->fetch();

if (!$commande) {
    header('Location: admin.php');
    exit;
}

$stmt = $conn->prepare("SELECT * FROM commande_details WHERE commande_id = ?");
$stmt->execute([$commande_id]);
$details = $stmt->fetchAll();

// === FONCTIONS D'ENVOI EMAIL AMÉLIORÉES ===

function envoyerRecuPaye($commande_id, $conn, $smtp_host, $smtp_port, $smtp_username, $smtp_password, $from_email, $from_name) {
    try {
        error_log("🚀 Début envoyerRecuPaye pour commande: " . $commande_id);
        
        // Récupérer les informations de la commande
        $stmt = $conn->prepare("SELECT * FROM commandes WHERE id = ?");
        $stmt->execute([$commande_id]);
        $commande = $stmt->fetch();

        if (!$commande) {
            error_log("❌ Commande introuvable: " . $commande_id);
            return false;
        }

        // Vérifier l'email
        if (empty($commande['email']) || !filter_var($commande['email'], FILTER_VALIDATE_EMAIL)) {
            error_log("❌ Email invalide: " . ($commande['email'] ?? 'vide'));
            return false;
        }

        // Récupérer les détails
        $stmt = $conn->prepare("SELECT * FROM commande_details WHERE commande_id = ?");
        $stmt->execute([$commande_id]);
        $details = $stmt->fetchAll();

        if (empty($details)) {
            error_log("❌ Aucun détail trouvé pour la commande: " . $commande_id);
            return false;
        }

        // Générer le contenu
        $sujet = "Reçu de paiement - Commande #" . str_pad($commande_id, 6, '0', STR_PAD_LEFT);
        $contenu = genererRecuHTML($commande, $details, true);

        // Envoyer l'email
        return envoyerEmailAvecDebug($commande['email'], $sujet, $contenu, $smtp_host, $smtp_port, $smtp_username, $smtp_password, $from_email, $from_name);

    } catch (Exception $e) {
        error_log("❌ Erreur dans envoyerRecuPaye: " . $e->getMessage());
        return false;
    }
}

function envoyerEmailAvecDebug($destinataire, $sujet, $contenu, $smtp_host, $smtp_port, $smtp_username, $smtp_password, $from_email, $from_name) {
    try {
        error_log("📧 Tentative d'envoi email vers: " . $destinataire);
        error_log("📧 Configuration: " . $smtp_host . ":" . $smtp_port . " | " . $smtp_username);
        
        $mail = new PHPMailer(true);
        
        // Configuration SMTP
        $mail->isSMTP();
        $mail->Host = $smtp_host;
        $mail->Port = $smtp_port;
        $mail->SMTPAuth = true;
        $mail->Username = $smtp_username;
        $mail->Password = $smtp_password;
        
        // Sécurité adaptée au port
        if ($smtp_port == 465) {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL
        } else {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // TLS
        }
        
        // Options SSL/TLS pour éviter les erreurs de certificat
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];

        // Debug SMTP - Enregistrer dans les logs
        $mail->SMTPDebug = SMTP::DEBUG_SERVER;
        $mail->Debugoutput = function($str, $level) {
            error_log("SMTP DEBUG [$level]: " . trim($str));
        };

        // Configuration du message
        $mail->setFrom($from_email, $from_name);
        $mail->addAddress($destinataire);
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = $sujet;
        $mail->Body = $contenu;
        
        // Timeout étendu
        $mail->Timeout = 60;
        
        error_log("📤 Envoi en cours...");
        $resultat = $mail->send();
        
        if ($resultat) {
            error_log("✅ Email envoyé avec succès vers: " . $destinataire);
        } else {
            error_log("❌ Échec d'envoi vers: " . $destinataire);
        }
        
        return $resultat;
        
    } catch (Exception $e) {
        error_log("❌ Erreur PHPMailer: " . $e->getMessage());
        error_log("❌ Code erreur: " . $e->getCode());
        
        // Log des erreurs spécifiques
        if (strpos($e->getMessage(), 'SMTP connect()') !== false) {
            error_log("💡 Problème de connexion SMTP - Vérifiez host/port");
        }
        if (strpos($e->getMessage(), 'SMTP Error: Could not authenticate') !== false) {
            error_log("💡 Problème d'authentification - Vérifiez username/password");
        }
        if (strpos($e->getMessage(), 'SSL operation failed') !== false) {
            error_log("💡 Problème SSL - Essayez avec verify_peer = false");
        }
        
        return false;
    }
}

function genererRecuHTML($commande, $details, $paye = false) {
    $total = 0;
    foreach ($details as $detail) {
        $total += floatval($detail['prix']) * intval($detail['quantite']);
    }
    
    $statut = $paye ? '<span style="color: #16a34a; font-weight: bold;">✓ PAYÉ</span>' : '<span style="color: #dc2626;">En attente</span>';
    
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Reçu de commande</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { text-align: center; background: #3b82f6; color: white; padding: 20px; border-radius: 10px 10px 0 0; }
            .content { background: white; border: 1px solid #e5e7eb; padding: 20px; }
            .footer { background: #f3f4f6; padding: 15px; text-align: center; border-radius: 0 0 10px 10px; }
            table { width: 100%; border-collapse: collapse; margin: 15px 0; }
            th, td { padding: 10px; text-align: left; border-bottom: 1px solid #e5e7eb; }
            th { background: #f9fafb; font-weight: bold; }
            .total { font-size: 18px; font-weight: bold; color: #3b82f6; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>🍽️ Mon Restaurant</h1>
            <h2>Reçu de Commande #' . str_pad($commande['id'], 6, '0', STR_PAD_LEFT) . '</h2>
        </div>
        
        <div class="content">
            <h3>Informations Client</h3>
            <p><strong>Nom:</strong> ' . htmlspecialchars($commande['nom_client']) . '</p>
            <p><strong>Email:</strong> ' . htmlspecialchars($commande['email']) . '</p>
            <p><strong>Adresse:</strong> ' . htmlspecialchars($commande['adresse']) . '</p>
            <p><strong>Statut de paiement:</strong> ' . $statut . '</p>
            
            <h3>Détails de la commande</h3>
            <table>
                <thead>
                    <tr>
                        <th>Article</th>
                        <th>Quantité</th>
                        <th>Prix unitaire</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>';
    
    foreach ($details as $detail) {
        $sous_total = floatval($detail['prix']) * intval($detail['quantite']);
        $html .= '
                    <tr>
                        <td>' . htmlspecialchars($detail['nom_plat']) . '</td>
                        <td>' . intval($detail['quantite']) . '</td>
                        <td>' . number_format(floatval($detail['prix']), 0, ',', ' ') . ' FCFA</td>
                        <td>' . number_format($sous_total, 0, ',', ' ') . ' FCFA</td>
                    </tr>';
    }
    
    $html .= '
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" class="total">TOTAL GÉNÉRAL</td>
                        <td class="total">' . number_format($total, 0, ',', ' ') . ' FCFA</td>
                    </tr>
                </tfoot>
            </table>
        </div>
        
        <div class="footer">
            <p>Merci pour votre commande !</p>
            <p><em>Date: ' . date('d/m/Y H:i') . '</em></p>
        </div>
    </body>
    </html>';
    
    return $html;
}

// Calculer le total pour l'affichage
$total = 0;
foreach ($details as $detail) {
    $total += floatval($detail['prix']) * intval($detail['quantite']);
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>restaurant Mulho #<?= str_pad($commande_id, 6, '0', STR_PAD_LEFT) ?></title>
    <link rel="icon" type="image/x-icon" href="../assets/img/logo.jpg">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Poppins', sans-serif; }
        .status-badge { 
            padding: 0.25rem 0.75rem; 
            border-radius: 9999px; 
            font-size: 0.875rem;
            font-weight: 500;
        }
        @media print {
            .no-print { display: none; }
            body { -webkit-print-color-adjust: exact; }
        }
        .success-message {
            animation: slideInRight 0.5s ease-out;
        }
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        .loading {
            opacity: 0.7;
            pointer-events: none;
        }
        .config-info {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 10px;
            border-radius: 8px;
            margin: 10px 0;
            font-size: 12px;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Messages de succès/erreur -->
    <?php if (isset($_GET['success'])): ?>
    <div class="fixed top-4 right-4 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg z-50 success-message">
        <?php if ($_GET['success'] == 'payment_updated'): ?>
            ✓ Statut de paiement mis à jour avec succès
        <?php elseif ($_GET['success'] == 'receipt_sent'): ?>
            ✓ Reçu envoyé par email avec succès
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['error'])): ?>
    <div class="fixed top-4 right-4 bg-red-500 text-white px-4 py-2 rounded-lg shadow-lg z-50 success-message">
        ✗ <?= htmlspecialchars($_GET['error']) ?>
    </div>
    <?php endif; ?>

    <!-- Message dynamique pour AJAX -->
    <div id="ajax-message" class="fixed top-4 right-4 px-4 py-2 rounded-lg shadow-lg z-50 success-message" style="display: none;"></div>

    <!-- Barre de navigation admin -->
    <nav class="bg-white shadow-md py-4 px-6 flex justify-between items-center no-print">
        <div class="flex items-center space-x-2">
            <i class="fas fa-utensils text-blue-600 text-xl"></i>
            <span class="font-bold text-xl text-gray-800">Admin Dashboard</span>
        </div>
        <a href="admin_logout.php" class="text-gray-600 hover:text-blue-600 transition">
            <i class="fas fa-sign-out-alt mr-1"></i> Déconnexion
        </a>
    </nav>

    <!-- Indicateur de configuration -->
    <?php if ($env_loaded): ?>
    <div class="max-w-6xl mx-auto px-4 pt-4 no-print">
        <div class="bg-green-500 text-white px-4 py-2 rounded-lg">
            <i class="fas fa-check-circle mr-2"></i>Configuration .env chargée - 
            SMTP: <?= $smtp_host ?>:<?= $smtp_port ?> | 
            User: <?= $smtp_username ?>
        </div>
    </div>
    <?php else: ?>
    <div class="max-w-6xl mx-auto px-4 pt-4 no-print">
        <div class="bg-yellow-500 text-white px-4 py-2 rounded-lg">
            <i class="fas fa-exclamation-triangle mr-2"></i>Configuration .env non trouvée - Utilisation des paramètres par défaut
        </div>
    </div>
    <?php endif; ?>

    <div class="max-w-6xl mx-auto px-4 py-8">
        <div class="flex items-center justify-between mb-6 no-print">
            <div>
                <h1 class="text-3xl font-bold text-gray-800">Commande #<?= str_pad($commande_id, 6, '0', STR_PAD_LEFT) ?></h1>
                <p class="text-gray-600 mt-1">Gestion des paiements et envoi de reçu</p>
            </div>
            <a href="admin.php" class="inline-flex items-center text-blue-600 hover:text-blue-700 transition">
                <i class="fas fa-arrow-left mr-2"></i> Retour aux commandes
            </a>
        </div>

        <!-- Section Statut de Paiement -->
        <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
            <h2 class="text-xl font-semibold text-gray-800 mb-4 pb-2 border-b">
                <i class="fas fa-credit-card mr-2 text-blue-600"></i>Statut de Paiement
            </h2>
            
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <span class="text-gray-600">Statut actuel :</span>
                    <?php 
                        $statut_paiement = isset($commande['statut_paiement']) ? $commande['statut_paiement'] : 'Impayé';
                        $paiement_classe = ($statut_paiement == 'Payé') ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
                        $paiement_icone = ($statut_paiement == 'Payé') ? '✓' : '△';
                    ?>
                    <span id="payment-status" class="status-badge <?= $paiement_classe ?> flex items-center">
                        <span class="mr-1"><?= $paiement_icone ?></span>
                        <?= $statut_paiement ?>
                    </span>
                </div>
                
                <div id="payment-actions">
                    <?php if ($statut_paiement != 'Payé'): ?>
                    <button id="mark-paid-btn" type="button" onclick="marquerCommePaye()" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition flex items-center">
                        <i class="fas fa-check mr-2"></i> Marquer comme Payé
                    </button>
                    <?php else: ?>
                    <div class="flex items-center text-green-600">
                        <i class="fas fa-check-circle mr-2"></i>
                        <span class="font-medium">Paiement confirmé</span>
                    </div>
                    <button id="send-receipt-btn" onclick="envoyerRecu()" class="ml-3 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition flex items-center">
                        <i class="fas fa-envelope mr-2"></i> Envoyer Reçu
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Section Informations client -->
        <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
            <h2 class="text-xl font-semibold text-gray-800 mb-4 pb-2 border-b">
                <i class="fas fa-user-circle mr-2 text-blue-600"></i>Informations client
            </h2>
            <div class="space-y-4">
                <div class="flex">
                    <div class="w-1/3 text-gray-500">Nom</div>
                    <div class="w-2/3 font-medium"><?= htmlspecialchars($commande['nom_client']) ?></div>
                </div>
                <div class="flex">
                    <div class="w-1/3 text-gray-500">Email</div>
                    <div class="w-2/3 font-medium"><?= htmlspecialchars($commande['email']) ?></div>
                </div>
                <?php if (!empty($commande['telephone'])): ?>
                <div class="flex">
                    <div class="w-1/3 text-gray-500">Téléphone</div>
                    <div class="w-2/3 font-medium"><?= htmlspecialchars($commande['telephone']) ?></div>
                </div>
                <?php endif; ?>
                <div class="flex">
                    <div class="w-1/3 text-gray-500">Adresse</div>
                    <div class="w-2/3 font-medium"><?= htmlspecialchars($commande['adresse']) ?></div>
                </div>
                <div class="flex">
                    <div class="w-1/3 text-gray-500">N° de table</div>
                    <div class="w-2/3 font-medium">
                        <?php 
                            $numero_table = 'Non attribuée';
                            if (isset($commande['numero_table']) && !empty($commande['numero_table'])) {
                                $numero_table = htmlspecialchars($commande['numero_table']);
                            } elseif (isset($commande['num_table']) && !empty($commande['num_table'])) {
                                $numero_table = htmlspecialchars($commande['num_table']);
                            }
                            echo $numero_table;
                        ?>
                    </div>
                </div>
                <div class="flex">
                    <div class="w-1/3 text-gray-500">Statut</div>
                    <div class="w-2/3 font-medium">
                        <?php 
                            $statusColor = 'bg-gray-100 text-gray-800';
                            if ($commande['statut'] == 'En cours') $statusColor = 'bg-yellow-100 text-yellow-800';
                            if ($commande['statut'] == 'Prête') $statusColor = 'bg-blue-100 text-blue-800';
                            if ($commande['statut'] == 'Livrée') $statusColor = 'bg-green-100 text-green-800';
                            if ($commande['statut'] == 'Annulée') $statusColor = 'bg-red-100 text-red-800';
                        ?>
                        <span class="status-badge <?= $statusColor ?>">
                            <?= $commande['statut'] ?>
                        </span>
                    </div>
                </div>
                <div class="flex">
                    <div class="w-1/3 text-gray-500">Total</div>
                    <div class="w-2/3 font-bold text-lg text-blue-600"><?= number_format($total, 0, ',', ' ') ?> FCFA</div>
                </div>
            </div>
        </div>

        <!-- Section Articles commandés -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-6">
            <div class="px-6 py-4 border-b">
                <h2 class="text-xl font-semibold text-gray-800">
                    <i class="fas fa-list-alt mr-2 text-blue-600"></i>Articles commandés
                </h2>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Produit</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Quantité</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Prix unitaire</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($details as $detail): ?>
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="font-medium text-gray-900"><?= htmlspecialchars($detail['nom_plat']) ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-gray-500">
                                <?= intval($detail['quantite']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-gray-500">
                                <?= number_format(floatval($detail['prix']), 0, ',', ' ') ?> FCFA
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right font-medium">
                                <?= number_format(floatval($detail['prix']) * intval($detail['quantite']), 0, ',', ' ') ?> FCFA
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="bg-gray-50 font-bold">
                        <tr>
                            <td colspan="3" class="px-6 py-4 text-right text-gray-900">Total général</td>
                            <td class="px-6 py-4 text-right text-blue-600 text-lg"><?= number_format($total, 0, ',', ' ') ?> FCFA</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex flex-wrap justify-between items-center gap-4 no-print">
            <div class="flex gap-3">
                <a href="admin.php" class="px-5 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition shadow-sm">
                    <i class="fas fa-chevron-left mr-2"></i> Retour
                </a>
            </div>
            
            <div class="flex gap-3">
                <button onclick="window.print()" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 rounded-lg text-white transition shadow-sm">
                    <i class="fas fa-print mr-2"></i> Imprimer
                </button>
               
            </div>
        </div>
    </div>

    <footer class="mt-12 py-6 text-center text-gray-500 text-sm border-t no-print">
        © <?= date('Y') ?> Mon Restaurant. Tous droits réservés. | Configuration SMTP: <?= $smtp_host ?> 
    </footer>

    <script>
        // Auto-hide success/error messages
        setTimeout(function() {
            const alerts = document.querySelectorAll('.fixed.top-4');
            alerts.forEach(alert => {
                if (alert.id !== 'ajax-message') {
                    alert.style.transition = 'all 0.5s ease-out';
                    alert.style.transform = 'translateX(100%)';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.style.display = 'none', 500);
                }
            });
        }, 4000);

        // Fonction pour afficher les messages AJAX
        function showMessage(message, type = 'success') {
            const messageDiv = document.getElementById('ajax-message');
            messageDiv.className = `fixed top-4 right-4 px-4 py-2 rounded-lg shadow-lg z-50 success-message ${type === 'success' ? 'bg-green-500' : 'bg-red-500'} text-white`;
            messageDiv.textContent = (type === 'success' ? '✓ ' : '✗ ') + message;
            messageDiv.style.display = 'block';
            
            // Auto-hide after 4 seconds
            setTimeout(() => {
                messageDiv.style.transition = 'all 0.5s ease-out';
                messageDiv.style.transform = 'translateX(100%)';
                messageDiv.style.opacity = '0';
                setTimeout(() => messageDiv.style.display = 'none', 500);
            }, 4000);
        }

        // Fonction pour envoyer le reçu
        function envoyerRecu() {
            if (!confirm('Voulez-vous envoyer le reçu par email au client ?')) {
                return;
            }

            const btn = document.getElementById('send-receipt-btn');
            if (!btn) {
                console.error('Bouton send-receipt-btn introuvable');
                return;
            }

            const originalText = btn.innerHTML;
            
            // Afficher le loading
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Envoi...';
            btn.classList.add('loading');
            btn.disabled = true;

            // Préparer les données
            const formData = new FormData();
            formData.append('action', 'envoyer_recu');

            console.log('🚀 Envoi de la requête AJAX...');

            // Envoyer la requête AJAX avec timeout amélioré
            const controller = new AbortController();
            const timeoutId = setTimeout(() => {
                console.log('⏰ Timeout de 60 secondes atteint');
                controller.abort();
            }, 60000); // 60 secondes timeout

            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Cache-Control': 'no-cache'
                },
                body: formData,
                signal: controller.signal
            })
            .then(response => {
                clearTimeout(timeoutId);
                console.log('📡 Réponse reçue, status:', response.status);
                console.log('📋 Content-Type:', response.headers.get('content-type'));
                
                if (!response.ok) {
                    throw new Error(`Erreur HTTP: ${response.status} ${response.statusText}`);
                }
                
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    console.warn('⚠️ Content-Type inattendu:', contentType);
                    return response.text().then(text => {
                        console.log('📄 Réponse non-JSON:', text.substring(0, 1000));
                        // Essayer de trouver du JSON dans la réponse
                        const jsonMatch = text.match(/\{.*\}/);
                        if (jsonMatch) {
                            console.log('🔍 JSON trouvé dans la réponse:', jsonMatch[0]);
                            return JSON.parse(jsonMatch[0]);
                        } else {
                            throw new Error('Réponse invalide du serveur (pas de JSON trouvé)');
                        }
                    });
                }
                
                return response.json();
            })
            .then(data => {
                console.log('✅ Réponse JSON:', data);
                
                // Restaurer le bouton
                btn.innerHTML = originalText;
                btn.classList.remove('loading');
                btn.disabled = false;
                
                if (data.success) {
                    console.log('🎉 Email envoyé avec succès !');
                    showMessage(data.message || 'Reçu envoyé avec succès');
                } else {
                    console.error('❌ Erreur côté serveur:', data.message);
                    showMessage(data.message || 'Erreur lors de l\'envoi', 'error');
                }
            })
            .catch(error => {
                clearTimeout(timeoutId);
                console.error('💥 Erreur complète:', error);
                
                // Restaurer le bouton
                btn.innerHTML = originalText;
                btn.classList.remove('loading');
                btn.disabled = false;
                
                let errorMessage = 'Une erreur est survenue';
                if (error.name === 'AbortError') {
                    errorMessage = 'Timeout: L\'envoi a pris trop de temps (60s)';
                } else if (error.message) {
                    errorMessage = error.message;
                }
                
                showMessage(errorMessage, 'error');
            });
        }

        // Fonction pour marquer comme payé
        function marquerCommePaye() {
            if (!confirm('Êtes-vous sûr de vouloir marquer cette commande comme payée ?')) {
                return;
            }

            const btn = document.getElementById('mark-paid-btn');
            if (!btn) {
                console.error('Bouton mark-paid-btn introuvable');
                return;
            }

            const originalText = btn.innerHTML;
            
            // Afficher le loading
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Traitement...';
            btn.classList.add('loading');
            btn.disabled = true;

            // Préparer les données
            const formData = new FormData();
            formData.append('action', 'marquer_paye');

            // Envoyer la requête AJAX
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Cache-Control': 'no-cache'
                },
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Erreur HTTP: ${response.status}`);
                }
                
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    return response.text().then(text => {
                        console.error('Réponse non-JSON:', text);
                        // Essayer de trouver du JSON dans la réponse
                        const jsonMatch = text.match(/\{.*\}/);
                        if (jsonMatch) {
                            return JSON.parse(jsonMatch[0]);
                        } else {
                            throw new Error('Réponse invalide du serveur');
                        }
                    });
                }
                
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Mettre à jour l'interface
                    const statusBadge = document.getElementById('payment-status');
                    statusBadge.className = 'status-badge bg-green-100 text-green-800 flex items-center';
                    statusBadge.innerHTML = '<span class="mr-1">✓</span>Payé';
                    
                    // Remplacer le bouton par le message de confirmation + bouton envoyer reçu
                    const actionsDiv = document.getElementById('payment-actions');
                    actionsDiv.innerHTML = `
                        <div class="flex items-center text-green-600">
                            <i class="fas fa-check-circle mr-2"></i>
                            <span class="font-medium">Paiement confirmé</span>
                        </div>
                        <button id="send-receipt-btn" onclick="envoyerRecu()" class="ml-3 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition flex items-center">
                            <i class="fas fa-envelope mr-2"></i> Envoyer Reçu
                        </button>
                    `;
                    
                    showMessage(data.message);
                } else {
                    // Restaurer le bouton en cas d'erreur
                    btn.innerHTML = originalText;
                    btn.classList.remove('loading');
                    btn.disabled = false;
                    showMessage(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                btn.innerHTML = originalText;
                btn.classList.remove('loading');
                btn.disabled = false;
                showMessage('Une erreur est survenue: ' + error.message, 'error');
            });
        }
    </script>
</body>
</html>