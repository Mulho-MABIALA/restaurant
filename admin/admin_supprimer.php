<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}
require_once '../config.php';

$id = $_GET['id'] ?? 0;

// Récupérer l'admin à supprimer
$stmt = $conn->prepare("SELECT * FROM admin WHERE id = ?");
$stmt->execute([$id]);
$admin = $stmt->fetch();

// Ne pas supprimer soi-même
if ($admin && $admin['username'] !== $_SESSION['admin_username']) {
    $stmt = $conn->prepare("DELETE FROM admin WHERE id = ?");
    $stmt->execute([$id]);
}

header('Location: admin_gestion.php');
exit;
