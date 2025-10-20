<?php
session_start();
require_once '../config.php';
require_once 'permissions.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

requireAccess($conn, $_SESSION['admin_id'], 'finances');

$id = $_GET['id'] ?? null;

if (!$id) {
    die('ID facture manquant');
}

// Récupérer les détails de la facture
$stmt = $conn->prepare("
    SELECT ff.*, f.*
    FROM factures_fournisseurs ff
    LEFT JOIN fournisseurs f ON ff.fournisseur_id = f.id
    WHERE ff.id = ?
");
$stmt->execute([$id]);
$facture = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$facture) {
    die('Facture non trouvée');
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facture Fournisseur #<?= htmlspecialchars($facture['numero_facture']) ?></title>
    <style>
        @media print {
            .no-print {
                display: none;
            }
        }

        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f5f5f5;
        }

        .facture-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }

        .header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
            border-bottom: 3px solid #2563eb;
            padding-bottom: 20px;
        }

        .company-info {
            flex: 1;
        }

        .company-info h1 {
            margin: 0;
            color: #1e40af;
            font-size: 28px;
        }

        .invoice-info {
            text-align: right;
        }

        .invoice-number {
            font-size: 24px;
            font-weight: bold;
            color: #1e40af;
            margin-bottom: 10px;
        }

        .addresses {
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
        }

        .address-block {
            flex: 1;
        }

        .address-block h3 {
            color: #1e40af;
            margin-bottom: 10px;
            font-size: 14px;
            text-transform: uppercase;
        }

        .details-table {
            width: 100%;
            margin-top: 30px;
            margin-bottom: 30px;
        }

        .details-table td {
            padding: 10px;
            border-bottom: 1px solid #e5e7eb;
        }

        .details-table td:first-child {
            font-weight: bold;
            color: #4b5563;
            width: 40%;
        }

        .totals {
            margin-top: 40px;
            float: right;
            width: 300px;
        }

        .totals table {
            width: 100%;
        }

        .totals td {
            padding: 8px;
        }

        .totals .label {
            text-align: left;
            font-weight: 500;
        }

        .totals .amount {
            text-align: right;
            font-weight: bold;
        }

        .total-row {
            border-top: 2px solid #2563eb;
            font-size: 18px;
            color: #1e40af;
        }

        .footer {
            clear: both;
            margin-top: 60px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            color: #6b7280;
            font-size: 12px;
        }

        .statut-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: bold;
            margin-top: 10px;
        }

        .statut-payee {
            background: #d1fae5;
            color: #065f46;
        }

        .statut-en-attente {
            background: #fed7aa;
            color: #92400e;
        }

        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #2563eb;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .print-button:hover {
            background: #1d4ed8;
        }
    </style>
</head>
<body>

    <button class="print-button no-print" onclick="window.print()">
        <i class="fas fa-print"></i> Imprimer
    </button>

    <div class="facture-container">
        <div class="header">
            <div class="company-info">
                <h1>Restaurant Jungle</h1>
                <p>Dakar, Sénégal</p>
                <p>Tél: +221 XX XXX XX XX</p>
            </div>
            <div class="invoice-info">
                <div class="invoice-number">FACTURE FOURNISSEUR</div>
                <div style="font-size: 18px; margin-bottom: 5px;">#<?= htmlspecialchars($facture['numero_facture']) ?></div>
                <div style="color: #6b7280;">Date: <?= date('d/m/Y', strtotime($facture['date_facture'])) ?></div>
                <div style="color: #6b7280;">Échéance: <?= date('d/m/Y', strtotime($facture['date_echeance'])) ?></div>
                <span class="statut-badge <?= $facture['statut'] == 'payee' ? 'statut-payee' : 'statut-en-attente' ?>">
                    <?= $facture['statut'] == 'payee' ? 'PAYÉE' : 'EN ATTENTE' ?>
                </span>
            </div>
        </div>

        <div class="addresses">
            <div class="address-block">
                <h3>Fournisseur</h3>
                <p><strong><?= htmlspecialchars($facture['nom']) ?></strong></p>
                <?php if ($facture['contact_nom']): ?>
                    <p>Contact: <?= htmlspecialchars($facture['contact_nom']) ?></p>
                <?php endif; ?>
                <?php if ($facture['adresse']): ?>
                    <p><?= nl2br(htmlspecialchars($facture['adresse'])) ?></p>
                <?php endif; ?>
                <?php if ($facture['ville']): ?>
                    <p><?= htmlspecialchars($facture['ville']) ?></p>
                <?php endif; ?>
                <?php if ($facture['telephone']): ?>
                    <p>Tél: <?= htmlspecialchars($facture['telephone']) ?></p>
                <?php endif; ?>
                <?php if ($facture['email']): ?>
                    <p>Email: <?= htmlspecialchars($facture['email']) ?></p>
                <?php endif; ?>
            </div>
            <div class="address-block">
                <h3>Informations complémentaires</h3>
                <?php if ($facture['siret']): ?>
                    <p>SIRET: <?= htmlspecialchars($facture['siret']) ?></p>
                <?php endif; ?>
                <?php if ($facture['tva_numero']): ?>
                    <p>N° TVA: <?= htmlspecialchars($facture['tva_numero']) ?></p>
                <?php endif; ?>
                <p>Conditions de paiement: <?= $facture['conditions_paiement'] ?> jours</p>
                <p>Mode de paiement: <?= ucfirst($facture['mode_paiement']) ?></p>
            </div>
        </div>

        <?php if ($facture['notes']): ?>
        <div style="margin: 30px 0; padding: 15px; background: #f9fafb; border-left: 4px solid #2563eb;">
            <h3 style="margin: 0 0 10px 0; color: #1e40af;">Description / Notes</h3>
            <p style="margin: 0;"><?= nl2br(htmlspecialchars($facture['notes'])) ?></p>
        </div>
        <?php endif; ?>

        <div class="totals">
            <table>
                <tr>
                    <td class="label">Montant HT</td>
                    <td class="amount"><?= number_format($facture['montant_ht'], 0, ',', ' ') ?> FCFA</td>
                </tr>
                <tr>
                    <td class="label">TVA</td>
                    <td class="amount"><?= number_format($facture['montant_tva'], 0, ',', ' ') ?> FCFA</td>
                </tr>
                <tr class="total-row">
                    <td class="label">TOTAL TTC</td>
                    <td class="amount"><?= number_format($facture['montant_ttc'], 0, ',', ' ') ?> FCFA</td>
                </tr>
            </table>
        </div>

        <div class="footer">
            <p>Restaurant Jungle - Facture générée le <?= date('d/m/Y à H:i') ?></p>
            <?php if ($facture['statut'] == 'payee' && $facture['date_paiement']): ?>
                <p><strong>Facture réglée le <?= date('d/m/Y', strtotime($facture['date_paiement'])) ?></strong></p>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Auto-print dialog on page load (optional)
        // window.onload = function() { window.print(); }
    </script>

</body>
</html>
