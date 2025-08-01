<?php
require_once 'config.php';
require 'vendor/autoload.php'; // PHPMailer et Dompdf
session_start();
$total = 0;
$produits = [];

if (!empty($_SESSION['panier'])) {
    $ids = array_keys($_SESSION['panier']);
    $stmt = $conn->prepare("SELECT * FROM plats WHERE id = ?");
    foreach ($ids as $id) {
        $stmt->execute([$id]);
        $produit = $stmt->fetch();
        if ($produit) {
            $produit['quantite'] = $_SESSION['panier'][$produit['id']];
            $total += $produit['prix'] * $produit['quantite'];
            $produits[] = $produit;
        }
    }
}


use Dompdf\Dompdf;
use Dompdf\Options;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Vérifier que le panier n'est pas vide
if (!isset($_SESSION['panier']) || empty($_SESSION['panier'])) {
    header('Location: panier.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nom = trim($_POST['nom']);
    $email = trim($_POST['email']);
    $telephone = trim($_POST['telephone']);
    $adresse = trim($_POST['adresse']);
    $mode_retrait = $_POST['mode_retrait'] ?? '';

    if (empty($nom) || empty($email) || empty($adresse)) {
        $erreur = "Veuillez remplir tous les champs obligatoires.";
    } else {
        $total = 0;
        $ids = array_keys($_SESSION['panier']);
        $stmt = $conn->prepare("SELECT * FROM plats WHERE id = ?");
        $produits = [];

        foreach ($ids as $id) {
            $stmt->execute([$id]);
            $produit = $stmt->fetch();
            if ($produit) {
                $produit['quantite'] = $_SESSION['panier'][$produit['id']];
                $total += $produit['prix'] * $produit['quantite'];
                $produits[] = $produit;
            }
        }

        $transactionActive = false;
        try {
            $conn->beginTransaction();
            $transactionActive = true;

            // Insertion de la commande
            $stmt = $conn->prepare("INSERT INTO commandes 
                (nom_client, email, telephone, adresse, mode_retrait, total, date_commande, statut, vu_admin, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW(), 'En cours', 0, NOW())");
            $stmt->execute([$nom, $email, $telephone, $adresse, $mode_retrait, $total]);
            $commande_id = $conn->lastInsertId();

            // Insertion des détails
            foreach ($produits as $plat) {
                $stmt = $conn->prepare("INSERT INTO commande_details (commande_id, nom_plat, quantite, prix) VALUES (?, ?, ?, ?)");
                $stmt->execute([$commande_id, $plat['nom'], $plat['quantite'], $plat['prix']]);

                $stmt = $conn->prepare("UPDATE plats SET stock = stock - ? WHERE id = ?");
                $stmt->execute([$plat['quantite'], $plat['id']]);
            }

            // Notification admin
            $notif = $conn->prepare("INSERT INTO notifications (message, type, date, vue) VALUES (?, ?, NOW(), 0)");
            $notif->execute(['Un client vient de passer une commande.', 'info']);

            $conn->commit();
            $transactionActive = false;

            // Template HTML pour l'email de reçu (identique à la page de confirmation)
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
                    }
                </style>
            </head>
            <body>
                <div class='receipt-container'>
                    <!-- Header avec cercle vert et titre -->
                    <div class='header'>
                        <div class='success-circle'></div>
                        <h1>Commande confirmée !</h1>
                        <p>Merci pour votre commande !</p>
                    </div>
                    
                    <!-- Section détails de la commande -->
                    <div class='details-section'>
                        <div class='section-title'>Détails de la commande</div>
                        
                        <div class='detail-row'>
                            <span class='detail-label'>N° de commande:  </span>
                            <span class='detail-value order-number'>#" . str_pad($commande_id, 6, '0', STR_PAD_LEFT) . "</span>
                        </div>
                        
                        <div class='detail-row'>
                            <span class='detail-label'>Date:  </span>
                            <span class='detail-value'>" . date('d/m/Y') . "</span>
                        </div>
                        
                        <div class='detail-row'>
                            <span class='detail-label'>Client:  </span>
                            <span class='detail-value'>" . htmlspecialchars($nom) . "</span>
                        </div>";
                        
            if (!empty($telephone)) {
                $emailTemplate .= "
                        <div class='detail-row'>
                            <span class='detail-label'>Téléphone:  </span>
                            <span class='detail-value'>" . htmlspecialchars($telephone) . "</span>
                        </div>";
            }
            
            $emailTemplate .= "
                        <div class='detail-row'>
                            <span class='detail-label'>Email:  </span>
                            <span class='detail-value'>" . htmlspecialchars($email) . "</span>
                        </div>
                        
                        <div class='detail-row'>
                            <span class='detail-label'>Adresse:  </span>
                            <span class='detail-value'>" . htmlspecialchars($adresse) . "</span>
                        </div>
                        
                        <div class='detail-row'>
                            <span class='detail-label'>Total:  </span>
                            <span class='detail-value total-value'>" . number_format($total, 2) . " fcfa</span>
                        </div>
                    </div>
                    
                    <!-- Section produits commandés -->
                    <div class='products-section'>
                        <div class='section-title'>Produits commandés:  </div>
                        
                        <table class='products-table'>
                            <thead>
                                <tr>
                                    <th>Produit:  </th>
                                    <th>Quantité:  </th>
                                    <th>Prix<br>unitaire:  </th>
                                    <th>Sous-total:  </th>
                                </tr>
                            </thead>
                            <tbody>";
                            
            foreach ($produits as $plat) {
                $sousTotal = $plat['prix'] * $plat['quantite'];
                $emailTemplate .= "
                                <tr>
                                    <td class='product-name'>" . htmlspecialchars($plat['nom']) . "</td>
                                    <td>" . $plat['quantite'] . "</td>
                                    <td class='price-text'>" . number_format($plat['prix'], 2) . "<br>fcfa</td>
                                    <td class='price-text'>" . number_format($sousTotal, 2) . "<br>fcfa</td>
                                </tr>";
            }
            
            $emailTemplate .= "
                                <tr class='total-row'>
                                    <td colspan='3'><strong>Total</strong></td>
                                    <td class='total-amount'><strong>" . number_format($total, 2) . "<br>fcfa</strong></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </body>
            </html>";

            // Envoi de l'e-mail
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
                $mail->Subject = 'Confirmation de votre commande #' . str_pad($commande_id, 6, '0', STR_PAD_LEFT);
                $mail->Body = $emailTemplate;

                $mail->send();
            } catch (Exception $e) {
                error_log("Erreur lors de l'envoi du mail : {$mail->ErrorInfo}");
            }

            // Redirection vers la page de confirmation
            header("Location: confirmation.php?commande=$commande_id");
            exit;

        } catch (PDOException $e) {
            if ($transactionActive) {
                $conn->rollBack();
            }
            die("Erreur lors de l'enregistrement de la commande : " . $e->getMessage());
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
                        <h2 class="text-xl font-bold text-gray-800">Informations de livraison</h2>
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
                    
                    <form method="POST" class="space-y-6">
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
                                    Adresse email <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <input type="email" 
                                           id="email" 
                                           name="email" 
                                           required
                                           value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>"
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
                                Adresse de livraison <span class="text-red-500">*</span>
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
                    
                    <div class="space-y-4 mb-6">
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
                    </div>
                    
                    <div class="border-t pt-4">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-gray-600">Sous-total</span>
                            <span class="font-medium"><?= number_format($total, 0) ?> FCFA</span>
                        </div>
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-gray-600">Livraison</span>
                            <span class="font-medium text-primary">Gratuite</span>
                        </div>
                        <div class="border-t pt-2 mt-4">
                            <div class="flex justify-between items-center">
                                <span class="text-lg font-bold text-gray-800">Total</span>
                                <span class="text-2xl font-bold text-primary"><?= number_format($total, 0) ?> FCFA</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-6 p-4 bg-blue-50 rounded-lg border border-blue-200">
                        <div class="flex items-start">
                            <svg class="w-5 h-5 text-blue-600 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                            </svg>
                            <div>
                                <p class="text-sm font-medium text-blue-800">Information de livraison</p>
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
</body>
</html>