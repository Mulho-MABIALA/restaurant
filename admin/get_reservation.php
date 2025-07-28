<?php
session_start();
require_once __DIR__ . '/../vendor/autoload.php';
require_once '../config.php';

// Vérifier si l'utilisateur est un admin connecté
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

if (!isset($_GET['id'])) {
    header('HTTP/1.1 400 Bad Request');
    exit;
}

$id = intval($_GET['id']);
$stmt = $conn->prepare("SELECT * FROM reservations WHERE id = ?");
$stmt->execute([$id]);
$reservation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$reservation) {
    header('HTTP/1.1 404 Not Found');
    exit;
}

header('Content-Type: application/json');
echo json_encode($reservation);