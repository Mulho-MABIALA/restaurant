<?php
require_once '../../config.php';
session_start();

$user_role = $_SESSION['role'] ?? 'user';

// Vérifier les permissions
if ($user_role === 'user') {
    header('HTTP/1.0 403 Forbidden');
    exit('Accès refusé');
}

$format = $_GET['format'] ?? 'csv';
$date_debut = $_GET['date_debut'] ?? date('Y-m-01');
$date_fin = $_GET['date_fin'] ?? date('Y-m-t');

// Requête pour récupérer les incidents
$query = "SELECT 
    i.id,
    i.titre,
    i.description,
    i.gravite,
    i.statut,
    i.categorie,
    i.departement,
    i.tags,
    i.created_at,
    i.updated_at,
    e.nom as createur,
    e2.nom as assigne_a
    FROM incidents i
    JOIN employes e ON i.employe_id = e.id
    LEFT JOIN employes e2 ON i.assigne_a = e2.id
    WHERE i.created_at BETWEEN ? AND ?
    ORDER BY i.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->execute([$date_debut . ' 00:00:00', $date_fin . ' 23:59:59']);
$incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="incidents_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // En-têtes CSV
    fputcsv($output, [
        'ID',
        'Titre',
        'Description',
        'Gravité',
        'Statut',
        'Catégorie',
        'Département',
        'Tags',
        'Créateur',
        'Assigné à',
        'Date création',
        'Date modification'
    ]);
    
    // Données
    foreach ($incidents as $incident) {
        fputcsv($output, [
            $incident['id'],
            $incident['titre'],
            $incident['description'],
            $incident['gravite'],
            $incident['statut'],
            $incident['categorie'],
            $incident['departement'],
            $incident['tags'],
            $incident['createur'],
            $incident['assigne_a'],
            $incident['created_at'],
            $incident['updated_at']
        ]);
    }
    
    fclose($output);
    exit;
}

if ($format === 'pdf') {
    require_once '../vendor/tcpdf/tcpdf.php';
    
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    $pdf->SetCreator('Système de gestion des incidents');
    $pdf->SetTitle('Rapport des incidents');
    $pdf->SetHeaderData('', 0, 'Rapport des incidents', 'Période: ' . $date_debut . ' - ' . $date_fin);
    
    $pdf->setHeaderFont(['helvetica', '', 10]);
    $pdf->setFooterFont(['helvetica', '', 8]);
    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
    $pdf->SetMargins(15, 27, 15);
    $pdf->SetAutoPageBreak(TRUE, 25);
    
    $pdf->AddPage();
    
    $html = '<h1>Rapport des incidents</h1>';
    $html .= '<p>Période: ' . date('d/m/Y', strtotime($date_debut)) . ' - ' . date('d/m/Y', strtotime($date_fin)) . '</p>';
    $html .= '<p>Total des incidents: ' . count($incidents) . '</p>';
    
    $html .= '<table border="1" cellpadding="5">
        <tr style="background-color:#f0f0f0;">
            <th>ID</th>
            <th>Titre</th>
            <th>Gravité</th>
            <th>Statut</th>
            <th>Créateur</th>
            <th>Date</th>
        </tr>';
    
    foreach ($incidents as $incident) {
        $html .= '<tr>
            <td>' . $incident['id'] . '</td>
            <td>' . htmlspecialchars($incident['titre']) . '</td>
            <td>' . ucfirst($incident['gravite']) . '</td>
            <td>' . ucfirst(str_replace('_', ' ', $incident['statut'])) . '</td>
            <td>' . htmlspecialchars($incident['createur']) . '</td>
            <td>' . date('d/m/Y', strtotime($incident['created_at'])) . '</td>
        </tr>';
    }
    
    $html .= '</table>';
    
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output('incidents_' . date('Y-m-d') . '.pdf', 'D');
    exit;
}
?>