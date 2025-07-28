<?php
session_start();
require_once __DIR__ . '/../vendor/autoload.php';
require_once '../config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

$lastCheck = $_SESSION['last_reservation_check'] ?? date('Y-m-d H:i:s');
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM reservations WHERE date_envoi > ? AND statut = 'non_lu'");
$stmt->execute([$lastCheck]);
$result = $stmt->fetch();

// Mettre à jour le dernier check
$_SESSION['last_reservation_check'] = date('Y-m-d H:i:s');

header('Content-Type: application/json');
echo json_encode(['count' => $result['count']]);