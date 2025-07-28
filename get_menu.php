<?php
require_once '../config.php'; // Contient $conn = new PDO(...);
session_start();


header('Content-Type: application/json');

$categorie = $_GET['categorie'] ?? '';

if (!$categorie) {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM plats WHERE categorie = ?");
$stmt->execute([$categorie]);

$plats = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($plats);
?>
