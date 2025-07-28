<?php
header('Content-Type: application/json');
require_once '../config.php';

$date = $_GET['date'] ?? date('Y-m-d');
$jour_semaine = date('l', strtotime($date));

$stmt = $conn->prepare("SELECT * FROM horaires_ouverture WHERE jour = ?");
$stmt->execute([$jour_semaine]);
$horaire = $stmt->fetch();

if ($horaire['est_ferme']) {
    echo json_encode(['is_open' => false]);
    exit;
}

echo json_encode([
    'is_open' => true,
    'open_time' => $horaire['ouverture_matin'] ?: $horaire['ouverture_soir'],
    'close_time' => $horaire['fermeture_soir'] ?: $horaire['fermeture_matin']
]);