<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;


// Configuration PDO
try {
    $conn = new PDO('mysql:host=localhost;dbname=restaurant;charset=utf8', 'root', '');
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// Récupérer tous les plats avec le nom de la catégorie
$query = "SELECT p.*, c.nom AS categorie_nom 
          FROM plats p 
          LEFT JOIN categories c ON p.categorie_id = c.id 
          ORDER BY p.nom ASC";
$stmt = $conn->query($query);
$plats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Préparation HTML
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <style>
        body { font-family: Arial, sans-serif; }
        h1 { text-align: center; color: #333; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 12px; }
        th { background-color: #f2f2f2; text-align: left; padding: 8px; font-weight: bold; }
        td { padding: 8px; border-bottom: 1px solid #ddd; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        img { max-width: 60px; max-height: 40px; }
        .no-image { color: #999; font-style: italic; }
    </style>
</head>
<body>
    <h1>Liste des plats du restaurant</h1>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nom</th>
                <th>Description</th>
                <th class="text-right">Prix (FCFA)</th>
                <th>Catégorie</th>
                <th class="text-center">Image</th>
            </tr>
        </thead>
        <tbody>';

foreach ($plats as $plat) {
    $html .= '<tr>';
    $html .= '<td>' . htmlspecialchars($plat['id'] ?? '') . '</td>';
    $html .= '<td>' . htmlspecialchars($plat['nom'] ?? '') . '</td>';
    $html .= '<td>' . htmlspecialchars($plat['description'] ?? '') . '</td>';
    $html .= '<td class="text-right">' . number_format($plat['prix'] ?? 0, 2, ',', ' ') . ' FCFA</td>';
    $html .= '<td>' . htmlspecialchars($plat['categorie_nom'] ?? 'Non catégorisé') . '</td>';

    // Gestion de l'image
    $imagePath = __DIR__ . '/../uploads/' . ($plat['image'] ?? '');
    if (!empty($plat['image']) && file_exists($imagePath)) {
        $type = pathinfo($imagePath, PATHINFO_EXTENSION);
        $data = file_get_contents($imagePath);
        $base64 = 'data:image/' . $type . ';base64,' . base64_encode($data);
        $html .= '<td class="text-center"><img src="' . $base64 . '"></td>';
    } else {
        $html .= '<td class="text-center no-image">Pas d\'image</td>';
    }

    $html .= '</tr>';
}

$html .= '</tbody></table>';

// Footer avec date de génération
$html .= '<p style="text-align: right; margin-top: 30px; font-size: 10px; color: #666;">';
$html .= 'Généré le ' . date('d/m/Y à H:i') . '</p>';
$html .= '</body></html>';

// Configuration DomPDF
$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);
$options->set('defaultFont', 'Arial');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Envoi au navigateur
$dompdf->stream("menu_restaurant.pdf", [
    "Attachment" => false // false pour afficher dans le navigateur, true pour télécharger
]);

exit;
?>