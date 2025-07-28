<?php
require_once '../config.php';

// On suppose que tu as une colonne "is_read" dans la table "commandes"
$stmt = $conn->query("SELECT COUNT(*) FROM commandes WHERE is_read = 0");
$count = $stmt->fetchColumn();

echo json_encode(['new_orders' => $count]);
