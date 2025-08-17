<?php
header('Content-Type: application/json');
require_once '../../config.php';
require_once 'fonctions_annonces.php';

$type = $_GET['type'] ?? 'site';
$annonces = getAnnoncesActives($type);

echo json_encode([
    'success' => true,
    'count' => count($annonces),
    'annonces' => $annonces
]);
?>