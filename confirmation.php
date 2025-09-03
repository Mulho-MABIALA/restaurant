<?php
require_once 'config.php';
session_start();

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Message par défaut
$message = $_GET['message'] ?? 'Merci pour votre commande !';

// ID de commande
$commande_id = $_GET['commande'] ?? $_SESSION['commande_id'] ?? null;

if (!$commande_id) {
    die("ID de commande manquant.");
}

// Récupération de la commande et des détails
try {
    $stmt = $conn->prepare("SELECT * FROM commandes WHERE id = ?");
    $stmt->execute([$commande_id]);
    $commande = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$commande) {
        die("Commande non trouvée.");
    }

    $stmt = $conn->prepare("SELECT nom_plat, quantite, prix FROM commande_details WHERE commande_id = ?");
    $stmt->execute([$commande_id]);
    $details = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Debug : afficher les données récupérées
    error_log("Commande ID: " . $commande_id);
    error_log("Total en base: " . $commande['total']);
    error_log("Nombre de détails: " . count($details));
    error_log("Détails: " . print_r($details, true));

} catch (PDOException $e) {
    die("Erreur : " . htmlspecialchars($e->getMessage()));
}

// Recalculer le total à partir des détails (solution de secours)
$totalCalcule = 0;
if (!empty($details)) {
    foreach ($details as $item) {
        $sousTotal = $item['quantite'] * $item['prix'];
        $totalCalcule += $sousTotal;
    }
}

// Utiliser le total calculé si celui en base est 0 ou null
$totalCommande = ($commande['total'] > 0) ? $commande['total'] : $totalCalcule;

// Si toujours 0, essayer de récupérer depuis la session
if ($totalCommande == 0 && isset($_SESSION['panier']) && !empty($_SESSION['panier'])) {
    try {
        $ids = array_keys($_SESSION['panier']);
        $stmt = $conn->prepare("SELECT id, prix FROM plats WHERE id = ?");
        $sessionTotal = 0;
        foreach ($ids as $id) {
            if (is_numeric($id)) {
                $stmt->execute([$id]);
                $produit = $stmt->fetch();
                if ($produit) {
                    $sessionTotal += $produit['prix'] * $_SESSION['panier'][$id];
                }
            }
        }
        if ($sessionTotal > 0) {
            $totalCommande = $sessionTotal;
        }
    } catch (Exception $e) {
        error_log("Erreur calcul session: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>restaurant Mulho</title>
    <link rel="icon" type="image/x-icon" href="assets/img/logo.jpg">
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @media print {
            body { 
                background: white !important; 
            }
            .no-print { 
                display: none !important; 
            }
            .print-container {
                max-width: none !important;
                margin: 0 !important;
                box-shadow: none !important;
            }
        }
        
        /* Styles pour le reçu (identique à l'email) */
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
            .payment-status {
                margin: 15px;
                padding: 10px 12px;
            }
        }
    </style>
    <script>
        // Nettoyer le localStorage après confirmation
        localStorage.removeItem('cartItems');
        localStorage.removeItem('cart');
    </script>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen py-8 px-4">
        <div class="print-container">
            <!-- Format reçu pour l'affichage et l'impression -->
            <div class="receipt-container">
                <!-- Header avec cercle vert et titre -->
                <div class="header">
                    <div class="success-circle">
                        <i class="fas fa-check"></i>
                    </div>
                    <h1 style="font-size: 20px; font-weight: 600; color: #333; margin-bottom: 8px;">
                        Commande confirmée !
                    </h1>
                    <p style="color: #666; font-size: 14px;">
                        <?= htmlspecialchars($message) ?>
                    </p>
                </div>
                
                <!-- Nouveau: Statut de paiement impayé -->
                <div class="payment-status">
                    <div class="status-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h3>REÇU IMPAYÉ</h3>
                    <p>Cette commande est en attente de paiement</p>
                </div>
                
                <!-- Section détails de la commande -->
                <div class="details-section">
                    <div class="section-title">Détails de la commande</div>
                    
                    <div class="detail-row">
                        <span class="detail-label">N° de commande :</span>
                        <span class="detail-value order-number">#<?= str_pad($commande_id, 6, '0', STR_PAD_LEFT) ?></span>
                    </div>
                    
                    <div class="detail-row">
                        <span class="detail-label">Date :</span>
                        <span class="detail-value"><?= date('d/m/Y à H:i', strtotime($commande['date_commande'])) ?></span>
                    </div>
                    
                    <div class="detail-row">
                        <span class="detail-label">Statut :</span>
                        <span class="detail-value" style="color: #dc2626; font-weight: 600;">IMPAYÉ</span>
                    </div>
                    
                    <div class="detail-row">
                        <span class="detail-label">Client :</span>
                        <span class="detail-value"><?= htmlspecialchars($commande['nom_client']) ?></span>
                    </div>
                    
                    <?php if (!empty($commande['telephone'])): ?>
                    <div class="detail-row">
                        <span class="detail-label">Téléphone :</span>
                        <span class="detail-value"><?= htmlspecialchars($commande['telephone']) ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="detail-row">
                        <span class="detail-label">Email :</span>
                        <span class="detail-value"><?= htmlspecialchars($commande['email']) ?></span>
                    </div>
                    
                    <div class="detail-row">
                        <span class="detail-label">Adresse :</span>
                        <span class="detail-value"><?= htmlspecialchars($commande['adresse']) ?></span>
                    </div>
                    
                    <?php if (!empty($commande['num_table'])): ?>
                    <div class="detail-row">
                        <span class="detail-label">Numéro de table :</span>
                        <span class="detail-value"><?= htmlspecialchars($commande['num_table']) ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="detail-row">
                        <span class="detail-label">Total à payer :</span>
                        <span class="detail-value total-value"><?= number_format($totalCommande, 2) ?> FCFA</span>
                    </div>
                </div>
                
                <!-- Section produits commandés -->
                <div class="products-section">
                    <div class="section-title">Produits commandés</div>
                    
                    <?php if (!empty($details)): ?>
                    <table class="products-table">
                        <thead>
                            <tr>
                                <th>Produit</th>
                                <th>Quantité</th>
                                <th>Prix<br>unitaire</th>
                                <th>Sous-total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($details as $item): ?>
                            <?php $sousTotal = $item['quantite'] * $item['prix']; ?>
                            <tr>
                                <td class="product-name"><?= htmlspecialchars($item['nom_plat']) ?></td>
                                <td><?= (int)$item['quantite'] ?></td>
                                <td class="price-text"><?= number_format($item['prix'], 2) ?><br>FCFA</td>
                                <td class="price-text"><?= number_format($sousTotal, 2) ?><br>FCFA</td>
                            </tr>
                            <?php endforeach; ?>
                            <tr class="total-row">
                                <td colspan="3"><strong>Total à payer</strong></td>
                                <td class="total-amount"><strong><?= number_format($totalCommande, 2) ?><br>FCFA</strong></td>
                            </tr>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <!-- Debug : affichage si aucun détail trouvé -->
                    <div style="background: #fee; padding: 15px; border: 1px solid #fcc; border-radius: 5px; margin: 10px 0;">
                        <p style="color: #c33; font-weight: bold;">⚠️ Aucun produit trouvé pour cette commande</p>
                        <p style="font-size: 12px; color: #666;">
                            ID commande: <?= $commande_id ?><br>
                            Total en base: <?= $commande['total'] ?> fcfa<br>
                            Nombre détails: <?= count($details) ?>
                        </p>
                        <?php if (isset($_SESSION['panier'])): ?>
                        <p style="font-size: 12px; color: #666;">
                            Panier session: <?= !empty($_SESSION['panier']) ? 'Présent (' . count($_SESSION['panier']) . ' items)' : 'Vide' ?>
                        </p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Footer avec informations de paiement -->
                <div class="payment-footer">
                    <h4><i class="fas fa-exclamation-triangle"></i> Paiement requis</h4>
                    <p>Cette commande sera traitée après réception du paiement.<br>
                    Veuillez contacter notre équipe pour finaliser votre paiement.<br>
                    <strong>Merci de conserver ce reçu jusqu'au paiement complet.</strong></p>
                </div>
            </div>
        </div>
        
        <!-- Boutons d'action (masqués à l'impression) -->
        <div class="max-w-md mx-auto mt-6 space-y-3 no-print">
            <!-- Prochaines étapes avec avertissement paiement -->
            <div class="bg-red-50 rounded-lg p-4 text-left border border-red-200">
                <h4 class="font-semibold text-red-800 mb-2">
                    <i class="fas fa-credit-card mr-2"></i> Paiement requis
                </h4>
                <ul class="text-sm text-red-700 space-y-1">
                    <li>• Cette commande nécessite un paiement</li>
                    <li>• Un e-mail de confirmation vous a été envoyé</li>
                    <li>• Contactez-nous pour finaliser le paiement</li>
                    <li>• La préparation débutera après paiement</li>
                </ul>
            </div>

            <!-- Contact pour paiement -->
            
            <button onclick="window.print()" 
                    class="w-full bg-gray-200 hover:bg-gray-300 text-gray-700 py-3 rounded-lg font-semibold transition-colors">
                <i class="fas fa-print mr-2"></i> Imprimer le reçu
            </button>

            <a href="index.php" 
               class="w-full bg-primary hover:bg-primary-dark text-white py-3 rounded-lg font-semibold text-center block transition-colors">
                <i class="fas fa-home mr-2"></i> Retour à l'accueil
            </a>
            
            <a href="menu.php" 
               class="w-full bg-secondary hover:bg-orange-600 text-white py-3 rounded-lg font-semibold text-center block transition-colors">
                <i class="fas fa-utensils mr-2"></i> Commander à nouveau
            </a>
        </div>
    </div>
</body>
</html>