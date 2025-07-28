<?php
require_once('../config.php');

$id = $_GET['id'] ?? null;

if ($id) {
    $stmt = $conn->prepare("DELETE FROM plats WHERE id = ?");
    $stmt->execute([$id]);
}

header("Location: gestion_plats.php");
exit;
