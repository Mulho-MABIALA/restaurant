<?php
require_once '../config.php';
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $commande_id = (int) $_POST['commande_id'];
    $statut = $_POST['statut'];

    $stmt = $conn->prepare("UPDATE commandes SET statut = :statut WHERE id = :id");
    $stmt->execute([':statut' => $statut, ':id' => $commande_id]);

    header('Location: commandes.php');
    exit;
}
?>
