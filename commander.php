<?php
require_once 'config.php';
require 'vendor/autoload.php';
session_start();

// Ajouter ce code après session_start() dans commander.php

// Gestionnaire pour la synchronisation du panier localStorage
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'sync_cart') {
    header('Content-Type: application/json');
    
    try {
        $cartData = json_decode($_POST['cart_data'], true);
        
        if ($cartData && is_array($cartData)) {
            $_SESSION['panier'] = [];
            
            // Récupérer les produits de la base pour validation
            $stmt = $conn->prepare("SELECT id, nom, prix FROM plats WHERE nom = ?");
            
            foreach ($cartData as $item) {
                $stmt->execute([$item['item']]);
                $produit = $stmt->fetch();
                
                if ($produit) {
                    $_SESSION['panier'][$produit['id']] = intval($item['quantity']);
                }
            }
            
            echo json_encode([
                'success' => true, 
                'message' => 'Panier synchronisé',
                'session_count' => count($_SESSION['panier'])
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Données invalides']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Gestion de la newsletter après soumission du modal
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['newsletter_choice'])) {
    header('Content-Type: application/json');
    
    try {
        $email = $_POST['email'];
        $choice = $_POST['newsletter_choice'];
        
        if ($choice == 'oui') {
            // Vérifier si l'email existe déjà
            $stmt = $conn->prepare("SELECT * FROM newsletter WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->rowCount() == 0) {
                // Insérer dans la newsletter
                $stmt = $conn->prepare("INSERT INTO newsletter (email, date_inscription) VALUES (?, NOW())");
                $stmt->execute([$email]);
            }
        }
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Initialisation sécurisée des variables
$total = 0;
$produits = [];


// Vérification et initialisation du panier
if (!isset($_SESSION['panier']) || !is_array($_SESSION['panier'])) {
    $_SESSION['panier'] = [];
}

// Chargement des produits du panier depuis la session
if (!empty($_SESSION['panier']) && is_array($_SESSION['panier'])) {
    $ids = array_keys($_SESSION['panier']);
    if (!empty($ids)) {
        $stmt = $conn->prepare("SELECT * FROM plats WHERE id = ?");
        foreach ($ids as $id) {
            if (is_numeric($id)) {
                $stmt->execute([$id]);
                $produit = $stmt->fetch();
                if ($produit) {
                    $produit['quantite'] = $_SESSION['panier'][$produit['id']];
                    $total += $produit['prix'] * $produit['quantite'];
                    $produits[] = $produit;
                }
            }
        }
    }
}

// Si le panier de session est empty, essayer de récupérer depuis localStorage via JavaScript
if (empty($produits)) {
    // On laissera JavaScript récupérer le panier du localStorage
    $useLocalStorage = true;
} else {
    $useLocalStorage = false;
}

if (isset($_SESSION['panier'])) {
    unset($_SESSION['panier']);
    // ou
    $_SESSION['panier'] = [];
}

// Importation des classes nécessaires pour PDF et email
use Dompdf\Dompdf;
use Dompdf\Options;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['newsletter_choice'])) {
    $nom = trim($_POST['nom']);
    $email = trim($_POST['email']);
    $telephone = trim($_POST['telephone']);
    $adresse = trim($_POST['adresse']);
    $mode_retrait = $_POST['mode_retrait'] ?? '';
    $num_table = trim($_POST['num_table'] ?? '');
    
    if (empty($nom) || empty($adresse)) {
        $erreur = "Veuillez remplir tous les champs obligatoires.";
    } else {
        // CORRECTION 1: Récupérer les produits AVANT de commencer la transaction
        $produits = [];
        $total = 0;

        // Vérifier d'abord la session
        if (!empty($_SESSION['panier']) && is_array($_SESSION['panier'])) {
            $ids = array_keys($_SESSION['panier']);
            if (!empty($ids)) {
                $stmt = $conn->prepare("SELECT * FROM plats WHERE id = ?");
                foreach ($ids as $id) {
                    if (is_numeric($id)) {
                        $stmt->execute([$id]);
                        $produit = $stmt->fetch();
                        if ($produit) {
                            $produit['quantite'] = $_SESSION['panier'][$produit['id']];
                            $total += $produit['prix'] * $produit['quantite'];
                            $produits[] = $produit;
                        }
                    }
                }
            }
        }

        // CORRECTION 2: Si panier session vide, essayer localStorage via AJAX
        if (empty($produits) && isset($_POST['cart_data'])) {
            $cartData = json_decode($_POST['cart_data'], true);
            if ($cartData && is_array($cartData)) {
                $stmt = $conn->prepare("SELECT * FROM plats WHERE nom = ?");
                foreach ($cartData as $item) {
                    $stmt->execute([$item['item']]);
                    $produit = $stmt->fetch();
                    if ($produit) {
                        $produit['quantite'] = $item['quantity'];
                        $total += $produit['prix'] * $produit['quantite'];
                        $produits[] = $produit;
                    }
                }
            }
        }

        // CORRECTION 3: Vérifier qu'on a des produits avant de continuer
        if (empty($produits) || $total <= 0) {
            $erreur = "Erreur : Aucun produit trouvé dans votre panier. Veuillez retourner au menu.";
        } else {
            // Debug - à supprimer après résolution
            error_log("Produits à sauvegarder: " . print_r($produits, true));
            error_log("Total calculé: " . $total);

            $transactionActive = false;
            try {
                $conn->beginTransaction();
                $transactionActive = true;

                // CORRECTION 4: Insertion de la commande avec le bon total
                $stmt = $conn->prepare("INSERT INTO commandes 
    (nom_client, email, telephone, adresse, mode_retrait, num_table, total, statut_paiement, date_commande, statut, vu_admin, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, 'impaye', NOW(), 'En cours', 0, NOW())");
                $stmt->execute([$nom, $email, $telephone, $adresse, $mode_retrait, $num_table, $total]);
                $commande_id = $conn->lastInsertId();
                // Debug
                error_log("Commande ID créé: " . $commande_id);

                // CORRECTION 5: Insertion des détails avec vérification
                foreach ($produits as $plat) {
                    $stmt = $conn->prepare("INSERT INTO commande_details (commande_id, nom_plat, quantite, prix) VALUES (?, ?, ?, ?)");
                    $result = $stmt->execute([$commande_id, $plat['nom'], $plat['quantite'], $plat['prix']]);
                    
                    if (!$result) {
                        throw new Exception("Erreur insertion détail: " . implode(", ", $stmt->errorInfo()));
                    }

                    // Mise à jour du stock
                    $stmt = $conn->prepare("UPDATE plats SET stock = stock - ? WHERE id = ?");
                    $stmt->execute([$plat['quantite'], $plat['id']]);
                    
                    error_log("Détail inséré: " . $plat['nom'] . " x" . $plat['quantite'] . " = " . ($plat['prix'] * $plat['quantite']));
                }

                // Notification admin
                $notif = $conn->prepare("INSERT INTO notifications (message, type, date, vue) VALUES (?, ?, NOW(), 0)");
                $notif->execute(['Un client vient de passer une commande.', 'info']);

                $conn->commit();
                $transactionActive = false;

                // CORRECTION 6: Nettoyer le panier après succès
                $_SESSION['panier'] = [];
                unset($_SESSION['panier']);

                // Template HTML pour l'email
                $emailTemplate = "
<!DOCTYPE html>
<html lang='fr'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Reçu de commande</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.5;
            background-color: #f9f9f9;
            padding: 20px;
            color: #333;
        }
        .receipt-container {
            max-width: 400px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            text-align: center;
            padding: 30px 20px 20px;
            border-bottom: 1px solid #f0f0f0;
        }
        .success-circle {
            width: 60px;
            height: 60px;
            background-color: #c8f7c5;
            border-radius: 50%;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: #22c55e;
        }
        .header h1 {
            font-size: 20px;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        .header p {
            color: #666;
            font-size: 14px;
        }
        /* Nouveau style pour le statut impayé */
        .payment-status {
            background-color: #fef2f2;
            border: 2px solid #fecaca;
            border-radius: 8px;
            padding: 12px 16px;
            margin: 20px;
            text-align: center;
        }
        .payment-status .status-icon {
            width: 40px;
            height: 40px;
            background-color: #fee2e2;
            border-radius: 50%;
            margin: 0 auto 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: #dc2626;
        }
        .payment-status h3 {
            color: #dc2626;
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 5px;
        }
        .payment-status p {
            color: #991b1b;
            font-size: 12px;
        }
        .details-section {
            padding: 20px;
            border-bottom: 1px solid #f0f0f0;
        }
        .section-title {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 15px;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 8px 0;
            border-bottom: 1px solid #f5f5f5;
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .detail-label {
            color: #666;
            font-size: 14px;
            flex: 1;
        }
        .detail-value {
            color: #333;
            font-size: 14px;
            font-weight: 500;
            text-align: right;
            flex: 1;
        }
        .order-number {
            color: #3b82f6 !important;
            font-weight: 600;
        }
        .total-value {
            color: #dc2626 !important;
            font-weight: 600;
            font-size: 16px;
        }
        .products-section {
            padding: 20px;
        }
        .products-table {
            width: 100%;
            border-collapse: collapse;
            background: #f8f9fa;
            border-radius: 6px;
            overflow: hidden;
            font-size: 13px;
        }
        .products-table th {
            background-color: #e9ecef;
            color: #495057;
            font-weight: 600;
            padding: 12px 8px;
            text-align: left;
            font-size: 12px;
            border-bottom: 1px solid #dee2e6;
        }
        .products-table th:nth-child(2),
        .products-table th:nth-child(3),
        .products-table th:nth-child(4) {
            text-align: center;
        }
        .products-table td {
            padding: 12px 8px;
            border-bottom: 1px solid #f1f3f4;
            background: white;
        }
        .products-table td:nth-child(2),
        .products-table td:nth-child(3),
        .products-table td:nth-child(4) {
            text-align: center;
        }
        .product-name {
            color: #333;
            font-weight: 500;
        }
        .price-text {
            color: #333;
            font-size: 12px;
        }
        .total-row {
            background-color: #f8f9fa !important;
            font-weight: 600;
        }
        .total-row td {
            border-bottom: none !important;
            padding: 15px 8px !important;
        }
        .total-amount {
            color: #dc2626 !important;
            font-weight: 600;
            font-size: 14px;
        }
        /* Footer avec informations de paiement */
        .payment-footer {
            background-color: #f8f9fa;
            padding: 20px;
            border-top: 1px solid #e9ecef;
            text-align: center;
        }
        .payment-footer h4 {
            color: #495057;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .payment-footer p {
            color: #6c757d;
            font-size: 12px;
            line-height: 1.4;
        }
        @media (max-width: 500px) {
            .receipt-container {
                margin: 0;
                border-radius: 0;
            }
            body {
                padding: 0;
            }
            .products-table {
                font-size: 11px;
            }
            .products-table th,
            .products-table td {
                padding: 8px 4px;
            }
            .payment-status {
                margin: 15px;
                padding: 10px 12px;
            }
        }
    </style>
</head>
<body>
<div class='receipt-container'>
    <!-- Header avec cercle vert et titre -->
    <div class='header'>
        <div class='success-circle'>✓</div>
        <h1>Commande confirmée !</h1>
        <p>Merci pour votre commande !</p>
    </div>
    
    <!-- Nouveau: Statut de paiement impayé -->
    <div class='payment-status'>
        <div class='status-icon'>⚠️</div>
        <h3>REÇU IMPAYÉ</h3>
        <p>Cette commande est en attente de paiement</p>
    </div>
    
    <!-- Section détails de la commande -->
    <div class='details-section'>
        <div class='section-title'>Détails de la commande</div>
        
        <div class='detail-row'>
            <span class='detail-label'>N° de commande:</span>
            <span class='detail-value order-number'>#" . str_pad($commande_id, 6, '0', STR_PAD_LEFT) . "</span>
        </div>
        
        <div class='detail-row'>
            <span class='detail-label'>Date:</span>
            <span class='detail-value'>" . date('d/m/Y à H:i') . "</span>
        </div>
        
        <div class='detail-row'>
            <span class='detail-label'>Statut:</span>
            <span class='detail-value' style='color: #dc2626; font-weight: 600;'>IMPAYÉ</span>
        </div>
        
        <div class='detail-row'>
            <span class='detail-label'>Client:</span>
            <span class='detail-value'>" . htmlspecialchars($nom) . "</span>
        </div>";

if (!empty($telephone)) {
    $emailTemplate .= "
        <div class='detail-row'>
            <span class='detail-label'>Téléphone:</span>
            <span class='detail-value'>" . htmlspecialchars($telephone) . "</span>
        </div>";
}

$emailTemplate .= "
        <div class='detail-row'>
            <span class='detail-label'>Email:</span>
            <span class='detail-value'>" . htmlspecialchars($email) . "</span>
        </div>
        
        <div class='detail-row'>
            <span class='detail-label'>Adresse:</span>
            <span class='detail-value'>" . htmlspecialchars($adresse) . "</span>
        </div>";

if (!empty($num_table)) {
    $emailTemplate .= "
        <div class='detail-row'>
            <span class='detail-label'>Numéro de table:</span>
            <span class='detail-value'>" . htmlspecialchars($num_table) . "</span>
        </div>";
}

$emailTemplate .= "
        <div class='detail-row'>
            <span class='detail-label'>Total à payer:</span>
            <span class='detail-value total-value'>" . number_format($total, 2) . " FCFA</span>
        </div>
    </div>
    
    <!-- Section produits commandés -->
    <div class='products-section'>
        <div class='section-title'>Produits commandés</div>
        
        <table class='products-table'>
            <thead>
                <tr>
                    <th>Produit</th>
                    <th>Quantité</th>
                    <th>Prix<br>unitaire</th>
                    <th>Sous-total</th>
                </tr>
            </thead>
            <tbody>";

// Boucle pour générer les produits
foreach ($produits as $plat) {
    $sousTotal = $plat['prix'] * $plat['quantite'];
    $emailTemplate .= "
                <tr>
                    <td class='product-name'>" . htmlspecialchars($plat['nom']) . "</td>
                    <td>" . $plat['quantite'] . "</td>
                    <td class='price-text'>" . number_format($plat['prix'], 2) . "<br>FCFA</td>
                    <td class='price-text'>" . number_format($sousTotal, 2) . "<br>FCFA</td>
                </tr>";
}

$emailTemplate .= "
                <tr class='total-row'>
                    <td colspan='3'><strong>Total à payer</strong></td>
                    <td class='total-amount'><strong>" . number_format($total, 2) . "<br>FCFA</strong></td>
                </tr>
            </tbody>
        </table>
    </div>
            </html>";

                // Envoi de l'e-mail même si on affiche le modal newsletter
                if (!empty($email)) {
                    $mail = new PHPMailer(true);

                    try {
                        $mail->isSMTP();
                        $mail->Host = 'smtp.gmail.com';
                        $mail->SMTPAuth = true;
                        $mail->Username = 'mulhomabiala29@gmail.com'; // Ton adresse
                        $mail->Password = 'khli pyzj ihte qdgu'; // Mot de passe application Gmail
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port = 587;

                        $mail->setFrom('mulhomabiala29@gmail.com', 'Nom du Restaurant');
                        $mail->addAddress($email, $nom);

                        $mail->isHTML(true);
                        $mail->Subject = 'Reçu impayé - Commande #' . str_pad($commande_id, 6, '0', STR_PAD_LEFT) . ' en attente de paiement';
                        $mail->Body = $emailTemplate;

                        $mail->send();
                        error_log("Email envoyé avec succès à: $email");
                    } catch (Exception $e) {
                        error_log("Erreur lors de l'envoi du mail : {$mail->ErrorInfo}");
                    }
                }

                // Vérifier si un email a été fourni pour afficher le modal newsletter
                if (!empty($email)) {
                    // Stocker les informations de la commande pour le modal
                    $_SESSION['commande_id'] = $commande_id;
                    $_SESSION['commande_email'] = $email;
                    $_SESSION['commande_nom'] = $nom;
                    
                    // Afficher le modal au lieu de rediriger immédiatement
                    $showNewsletterModal = true;
                } else {
                    // Pas d'email, rediriger directement
                    header("Location: confirmation.php?commande=$commande_id");
                    exit;
                }

            } catch (PDOException $e) {
                if ($transactionActive) {
                    $conn->rollBack();
                }
                die("Erreur lors de l'enregistrement de la commande : " . $e->getMessage());
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finaliser votre commande</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#10b981',
                        'primary-dark': '#059669',
                        secondary: '#f59e0b',
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Header -->
    <header class="bg-gradient-to-r from-primary to-primary-dark shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl md:text-3xl font-bold text-white">
                    🛒 Finaliser votre commande
                </h1>
                <div class="hidden sm:flex items-center space-x-2 text-white/90">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                    </svg>
                    <span class="text-sm font-medium">Paiement sécurisé</span>
                </div>
            </div>
        </div>
    </header>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <!-- Formulaire de commande -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-2xl shadow-lg p-6 md:p-8">
                    <div class="flex items-center mb-6">
                        <div class="w-8 h-8 bg-primary rounded-full flex items-center justify-center mr-3">
                            <span class="text-white font-bold text-sm">1</span>
                        </div>
                        <h2 class="text-xl font-bold text-gray-800">Informations pour finaliser la commande</h2>
                    </div>

                    <?php if(isset($erreur)): ?>
                        <div class="mb-6 p-4 bg-red-50 border-l-4 border-red-400 rounded-lg">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 text-red-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                                </svg>
                                <p class="text-red-700 font-medium"><?php echo $erreur; ?></p>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" class="space-y-6" id="commandeForm">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="md:col-span-2">
                                <label for="nom" class="block text-sm font-semibold text-gray-700 mb-2">
                                    Nom complet <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <input type="text" 
                                           id="nom" 
                                           name="nom" 
                                           required
                                           value="<?= isset($_POST['nom']) ? htmlspecialchars($_POST['nom']) : '' ?>"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent transition-colors pl-10 bg-gray-50 focus:bg-white">
                                    <svg class="w-5 h-5 text-gray-400 absolute left-3 top-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                    </svg>
                                </div>
                            </div>
                            
                            <div>
                                <label for="email" class="block text-sm font-semibold text-gray-700 mb-2">
                                    Adresse email
                                </label>
                                <div class="relative">
                                    <input type="email" 
                                           id="email" 
                                           name="email" 
                                           value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>"
                                           placeholder="Votre adresse email vous permettra uniquement de recevoir le reçu de votre commande dans votre boîte mail."
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent transition-colors pl-10 bg-gray-50 focus:bg-white">
                                    <svg class="w-5 h-5 text-gray-400 absolute left-3 top-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"></path>
                                    </svg>
                                </div>
                            </div>
                            
                            <div>
                                <label for="telephone" class="block text-sm font-semibold text-gray-700 mb-2">
                                    Numéro de téléphone
                                </label>
                                <div class="relative">
                                    <input type="tel" 
                                           id="telephone" 
                                           name="telephone"
                                           value="<?= isset($_POST['telephone']) ? htmlspecialchars($_POST['telephone']) : '' ?>"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent transition-colors pl-10 bg-gray-50 focus:bg-white">
                                    <svg class="w-5 h-5 text-gray-400 absolute left-3 top-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <label for="adresse" class="block text-sm font-semibold text-gray-700 mb-2">
                                Adresse <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <textarea id="adresse" 
                                          name="adresse" 
                                          rows="4" 
                                          required
                                          placeholder="Veuillez indiquer votre adresse complète..."
                                          class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent transition-colors pl-10 bg-gray-50 focus:bg-white resize-none"><?= isset($_POST['adresse']) ? htmlspecialchars($_POST['adresse']) : '' ?></textarea>
                                <svg class="w-5 h-5 text-gray-400 absolute left-3 top-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                            </div>
                        </div>
                        <div>
                            <label for="num_table" class="block text-sm font-semibold text-gray-700 mb-2">
                                Numéro de table
                            </label>
                            <div class="relative">
                                <input type="number" 
                                       id="num_table" 
                                       name="num_table" 
                                       min="1"
                                       value="<?= isset($_POST['num_table']) ? htmlspecialchars($_POST['num_table']) : '' ?>"
                                       placeholder="Entrez votre numéro de table"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent transition-colors pl-10 bg-gray-50 focus:bg-white">
                                <svg class="w-5 h-5 text-gray-400 absolute left-3 top-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path>
                                </svg>
                            </div>
                        </div>
                        <button type="submit" 
                                class="w-full bg-gradient-to-r from-primary to-primary-dark text-white font-bold py-4 px-6 rounded-lg hover:from-primary-dark hover:to-primary transform hover:scale-[1.02] transition-all duration-200 shadow-lg hover:shadow-xl flex items-center justify-center space-x-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Confirmer la commande</span>
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Résumé de la commande -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-2xl shadow-lg p-6 sticky top-8">
                    <div class="flex items-center mb-6">
                        <div class="w-8 h-8 bg-secondary rounded-full flex items-center justify-center mr-3">
                            <span class="text-white font-bold text-sm">2</span>
                        </div>
                        <h2 class="text-xl font-bold text-gray-800">Résumé de la commande</h2>
                    </div>
                    
                    <!-- Container pour les produits -->
                    <div id="orderSummary" class="space-y-4 mb-6">
                        <?php if (!empty($produits)): ?>
                            <!-- Affichage depuis PHP (session) -->
                            <?php foreach ($produits as $produit): ?>
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <div class="flex-1">
                                        <h3 class="font-medium text-gray-800 text-sm">
                                            <?= htmlspecialchars($produit['nom']) ?>
                                        </h3>
                                        <p class="text-xs text-gray-500 mt-1">
                                            <?= number_format($produit['prix'], 0) ?> FCFA × <?= $produit['quantite'] ?>
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-semibold text-gray-800">
                                            <?= number_format($produit['prix'] * $produit['quantite'], 0) ?> FCFA
                                        </p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <!-- Le panier sera chargé via JavaScript si vide en PHP -->
                            <div class="text-center py-4" id="emptyCartMessage">
                                <p class="text-gray-500">Chargement du panier...</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="border-t pt-4">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-gray-600">Sous-total</span>
                            <span class="font-medium" id="subtotalAmount"><?= number_format($total, 0) ?> FCFA</span>
                        </div>
                        
                        <div class="border-t pt-2 mt-4">
                            <div class="flex justify-between items-center">
                                <span class="text-lg font-bold text-gray-800">Total</span>
                                <span class="text-2xl font-bold text-primary" id="totalAmount"><?= number_format($total, 0) ?> FCFA</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-6 p-4 bg-blue-50 rounded-lg border border-blue-200">
                        <div class="flex items-start">
                            <svg class="w-5 h-5 text-blue-600 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                            </svg>
                            <div>
                                <p class="text-sm font-medium text-blue-800">Information sur la commande</p>
                                <p class="text-xs text-blue-600 mt-1">
                                    Vous recevrez un email de confirmation avec les détails de votre commande.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Newsletter -->
    <?php if (isset($showNewsletterModal) && $showNewsletterModal): ?>
    <div id="newsletterModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
            <h3 class="text-xl font-bold mb-4">Souhaitez-vous recevoir également des informations du restaurant ?</h3>
            <p class="text-gray-600 mb-6">Réductions, événements, nouveautés...</p>
            <div class="flex justify-between">
                <button type="button" id="newsletterNon" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-6 rounded">
                    Non
                </button>
                <button type="button" id="newsletterOui" class="bg-primary hover:bg-primary-dark text-white font-bold py-2 px-6 rounded">
                    Oui
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>

<script>
// Variables globales pour gérer le panier
let cartFromLocalStorage = [];

// Fonction pour charger le panier depuis localStorage ou sessionStorage
function loadCartFromStorage() {
    // Essayer sessionStorage d'abord (sauvegarde de menu.php)
    let cartItems = [];
    
    try {
        const sessionCart = sessionStorage.getItem('mulho_cart');
        if (sessionCart) {
            cartItems = JSON.parse(sessionCart);
            console.log('Panier chargé depuis sessionStorage:', cartItems);
        }
    } catch (e) {
        console.log('Pas de sessionStorage disponible');
    }
    
    // Si pas trouvé, essayer localStorage (fallback)
    if (cartItems.length === 0) {
        try {
            const localCart = localStorage.getItem('cartItems');
            if (localCart) {
                cartItems = JSON.parse(localCart);
                console.log('Panier chargé depuis localStorage:', cartItems);
            }
        } catch (e) {
            console.log('Pas de localStorage disponible');
        }
    }
    
    if (cartItems.length > 0) {
        cartFromLocalStorage = cartItems;
        displayCartItems(cartItems);
        
        // Envoyer immédiatement au serveur pour synchronisation
        syncCartWithServer(cartItems);
    } else {
        // Aucun item dans le panier
        document.getElementById('emptyCartMessage').innerHTML = 
            '<p class="text-gray-500">Votre panier est vide</p><a href="menu.php" class="text-primary hover:underline">← Retour au menu</a>';
    }
}

// Fonction pour afficher les items du panier
function displayCartItems(cartItems) {
    const orderSummary = document.getElementById('orderSummary');
    let total = 0;
    let itemsHTML = '';
    
    cartItems.forEach(item => {
        const itemTotal = item.price * item.quantity;
        total += itemTotal;
        
        itemsHTML += `
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                <div class="flex-1">
                    <h3 class="font-medium text-gray-800 text-sm">
                        ${item.item}
                    </h3>
                    <p class="text-xs text-gray-500 mt-1">
                        ${item.price.toLocaleString()} FCFA × ${item.quantity}
                    </p>
                    ${item.specialInstructions ? 
                        `<p class="text-xs text-blue-600 mt-1 italic">${item.specialInstructions}</p>` : 
                        ''
                    }
                </div>
                <div class="text-right">
                    <p class="font-semibold text-gray-800">
                        ${itemTotal.toLocaleString()} FCFA
                    </p>
                </div>
            </div>
        `;
    });
    
    orderSummary.innerHTML = itemsHTML;
    
    // Mettre à jour les totaux
    document.getElementById('subtotalAmount').textContent = total.toLocaleString() + ' FCFA';
    document.getElementById('totalAmount').textContent = total.toLocaleString() + ' FCFA';
    
    // Masquer le message "panier vide"
    const emptyMessage = document.getElementById('emptyCartMessage');
    if (emptyMessage) {
        emptyMessage.style.display = 'none';
    }
}

// Fonction pour synchroniser avec le serveur
function syncCartWithServer(cartItems) {
    console.log('Synchronisation avec le serveur...', cartItems);
    
    fetch('commander.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=sync_cart&cart_data=' + encodeURIComponent(JSON.stringify(cartItems))
    })
    .then(response => response.json())
    .then(data => {
        console.log('Panier synchronisé:', data);
        if (!data.success) {
            console.error('Erreur de synchronisation:', data.message);
        }
    })
    .catch(error => {
        console.error('Erreur lors de la synchronisation:', error);
    });
}

// Gestionnaire de soumission du formulaire
function setupFormHandler() {
    const form = document.querySelector('form[method="POST"]');
    if (!form) return;
    
    form.addEventListener('submit', function(e) {
        console.log('Soumission du formulaire...');
        
        // Récupérer les données du panier
        let cartItems = cartFromLocalStorage;
        
        // Si pas de données, essayer de relire depuis le storage
        if (cartItems.length === 0) {
            try {
                const sessionCart = sessionStorage.getItem('mulho_cart');
                const localCart = localStorage.getItem('cartItems');
                cartItems = JSON.parse(sessionCart || localCart || '[]');
            } catch (e) {
                cartItems = [];
            }
        }
        
        if (cartItems.length > 0) {
            // Vérifier s'il y a déjà un champ cart_data
            let cartInput = form.querySelector('input[name="cart_data"]');
            if (!cartInput) {
                cartInput = document.createElement('input');
                cartInput.type = 'hidden';
                cartInput.name = 'cart_data';
                form.appendChild(cartInput);
            }
            cartInput.value = JSON.stringify(cartItems);
            
            console.log('Données panier envoyées avec le formulaire:', cartItems);
        } else {
            console.warn('Aucune donnée de panier à envoyer');
            
            // Optionnel : empêcher la soumission si le panier est empty
            e.preventDefault();
            alert('Votre panier est vide. Veuillez ajouter des articles avant de commander.');
            return false;
        }
    });
}

// Fonction pour vider le storage après commande réussie
function clearCartStorage() {
    try {
        sessionStorage.removeItem('mulho_cart');
        localStorage.removeItem('cartItems');
        console.log('Panier vidé du stockage local');
    } catch (e) {
        console.log('Erreur lors du vidage du panier');
    }
}

// Gestion du modal newsletter
function setupNewsletterModal() {
    const modal = document.getElementById('newsletterModal');
    if (!modal) return;
    
    const btnOui = document.getElementById('newsletterOui');
    const btnNon = document.getElementById('newsletterNon');
    
    const email = '<?php echo $_SESSION['commande_email'] ?? ''; ?>';
    const commandeId = '<?php echo $_SESSION['commande_id'] ?? ''; ?>';
    
    function handleNewsletterChoice(choice) {
        // Envoyer le choix au serveur
        fetch('commander.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'newsletter_choice=' + choice + '&email=' + encodeURIComponent(email)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Rediriger vers la page de confirmation
                window.location.href = 'confirmation.php?commande=' + commandeId;
            } else {
                alert('Erreur lors du traitement de votre choix. Veuillez réessayer.');
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            alert('Erreur de connexion. Veuillez réessayer.');
        });
    }
    
    btnOui.addEventListener('click', function() {
        handleNewsletterChoice('oui');
    });
    
    btnNon.addEventListener('click', function() {
        handleNewsletterChoice('non');
    });
}

// Initialisation au chargement de la page
document.addEventListener('DOMContentLoaded', function() {
    console.log('Commander.php - Initialisation...');
    
    <?php if ($useLocalStorage): ?>
        console.log('Chargement du panier depuis localStorage...');
        loadCartFromStorage();
    <?php else: ?>
        console.log('Panier déjà chargé depuis la session PHP');
    <?php endif; ?>
    
    setupFormHandler();
    setupNewsletterModal();
});

// Debug : afficher les données disponibles
console.log('Session Storage:', sessionStorage.getItem('mulho_cart'));
console.log('Local Storage:', localStorage.getItem('cartItems'));
</script>
    
    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-8 mt-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <div class="flex items-center space-x-4 mb-4 md:mb-0">
                    <svg class="w-6 h-6 text-primary" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"></path>
                    </svg>
                    <span class="text-sm">Paiement 100% sécurisé</span>
                </div>
                <div class="text-sm text-gray-400">
                    © 2024 - Tous droits réservés
                </div>
            </div>
        </div>
    </footer>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    
    form.addEventListener('submit', function(e) {
        // Récupérer le panier du localStorage si nécessaire
        const cartItems = JSON.parse(localStorage.getItem('cartItems') || '[]');
        
        if (cartItems.length > 0) {
            // Créer un champ caché pour envoyer les données du panier
            const cartInput = document.createElement('input');
            cartInput.type = 'hidden';
            cartInput.name = 'cart_data';
            cartInput.value = JSON.stringify(cartItems);
            form.appendChild(cartInput);
            
            console.log('Données panier envoyées:', cartItems);
        }
    });
    
    // Fonction pour synchroniser avec le serveur (comme dans votre code existant)
    function updateServerCart(cartItems) {
        fetch('commander.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=sync_cart&cart_data=' + encodeURIComponent(JSON.stringify(cartItems))
        })
        .then(response => response.json())
        .then(data => {
            console.log('Panier synchronisé:', data);
        })
        .catch(error => {
            console.error('Erreur sync:', error);
        });
    }
    
    // Synchroniser au chargement de la page
    const cartItems = JSON.parse(localStorage.getItem('cartItems') || '[]');
    if (cartItems.length > 0) {
        updateServerCart(cartItems);
    }
});
</script>
<script>
// Vider le panier du sessionStorage après commande réussie
function clearCartAfterOrder() {
    try {
        sessionStorage.removeItem('mulho_cart');
        console.log('Panier vidé après commande');
    } catch (error) {
        console.error('Erreur lors du vidage du panier:', error);
    }
}

// Appeler cette fonction après confirmation de la commande
document.addEventListener('DOMContentLoaded', function() {
    clearCartAfterOrder();
});
</script>
</body>
</html>