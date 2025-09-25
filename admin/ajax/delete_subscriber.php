<?php
session_start();
require_once '../includes/newsletter_functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (isset($input['id'])) {
    $result = deleteSubscriber($input['id']);
    echo json_encode(['success' => $result]);
} else {
    echo json_encode(['success' => false, 'message' => 'ID manquant']);
}
?>
