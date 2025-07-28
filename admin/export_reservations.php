<?php
session_start();
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Vérification d'authentification admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$format = $_GET['format'] ?? 'pdf';

// Récupération des réservations
try {
    $stmt = $conn->query("SELECT id, nom, email, telephone, date_reservation, personnes FROM reservations ORDER BY date_reservation DESC");
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erreur lors de la récupération des réservations : " . $e->getMessage());
}

if ($format === 'pdf') {
    // ✅ Génération PDF avec Dompdf (sans AddPage ni SetFont)
    $options = new Options();
    $options->set('defaultFont', 'Helvetica');
    $dompdf = new Dompdf($options);

    $html = '<h1>Liste des réservations</h1>';
    $html .= '<table border="1" cellpadding="5" cellspacing="0" width="100%">';
    $html .= '<thead><tr><th>ID</th><th>Nom</th><th>Email</th><th>Téléphone</th><th>Date</th><th>Personnes</th></tr></thead><tbody>';

    foreach ($reservations as $res) {
        $html .= "<tr>
                    <td>{$res['id']}</td>
                    <td>{$res['nom']}</td>
                    <td>{$res['email']}</td>
                    <td>{$res['telephone']}</td>
                    <td>{$res['date_reservation']}</td>
                    <td>{$res['personnes']}</td>
                  </tr>";
    }

    $html .= '</tbody></table>';

    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $dompdf->stream('reservations.pdf', ['Attachment' => true]);
    exit;

} elseif ($format === 'csv') {
    // ✅ Génération CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="reservations.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Nom', 'Email', 'Téléphone', 'Date', 'Personnes']);

    foreach ($reservations as $res) {
        fputcsv($output, [
            $res['id'],
            $res['nom'],
            $res['email'],
            $res['telephone'],
            $res['date_reservation'],
            $res['personnes']
        ]);
    }

    fclose($output);
    exit;
} else {
    echo "Format non supporté.";
    exit;
}
