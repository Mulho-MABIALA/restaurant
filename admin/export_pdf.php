<?php
ob_start();

require_once '../config.php';
require __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$options = new Options();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);

// ✅ Requête : total par jour
try {
    $stmt = $conn->prepare("
        SELECT 
            DATE(c.date_commande) AS jour,
            SUM(ci.quantite * ci.prix_unitaire) AS montant_journalier
        FROM commandes c
        LEFT JOIN commande_items ci ON ci.commande_id = c.id
        WHERE c.date_commande >= DATE_FORMAT(CURRENT_DATE ,'%Y-%m-01')
        GROUP BY jour
        ORDER BY jour DESC
    ");
    $stmt->execute();
    $totauxParJour = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Erreur SQL : ' . $e->getMessage());
}

// ✅ HTML + style
$html = '
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
    h1 { text-align: center; color: #2c3e50; }
    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
    th, td { border: 1px solid #ccc; padding: 8px; }
    thead { background-color: #f7f7f7; color: #34495e; }
    tr.total { background-color: #e8f0fe; font-weight: bold; }
    .logo { text-align: center; margin-bottom: 10px; }
</style>

<div class="logo">
    <img src="../assets/img/logo.png" alt="Logo" width="120">
</div>

<h1>Rapport des ventes par jour</h1>

<table>
    <thead>
        <tr>
            <th>Date</th>
            <th>Total du jour (FCFA)</th>
        </tr>
    </thead>
    <tbody>
';

$totalGeneral = 0;

if (count($totauxParJour) > 0) {
    foreach ($totauxParJour as $row) {
        $montant = $row['montant_journalier'] ?? 0;
        $totalGeneral += (float)$montant;

        $html .= '<tr>
            <td>' . htmlspecialchars($row['jour']) . '</td>
            <td style="text-align: right;">' . number_format((float)$montant, 0, ',', ' ') . ' FCFA</td>
        </tr>';
    }

    $html .= '<tr class="total">
        <td style="text-align: right;">Total général</td>
        <td style="text-align: right;">' . number_format($totalGeneral, 0, ',', ' ') . ' FCFA</td>
    </tr>';
} else {
    $html .= '<tr><td colspan="2" style="text-align: center;">Aucune commande ce mois-ci.</td></tr>';
}

$html .= '
    </tbody>
</table>
';

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
ob_end_clean();
$dompdf->stream('rapport_ventes_par_jour.pdf', ['Attachment' => false]);
exit;
