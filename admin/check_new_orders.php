<?php
require_once '../config.php';

$stmt = $conn->query("SELECT COUNT(*) as total FROM commandes WHERE statut = 'En cours'");
$row = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode(['new' => $row['total'] > 0 ? 1 : 0]);
