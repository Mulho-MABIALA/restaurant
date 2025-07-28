<?php
require_once '../config.php';

$id = $_POST['id'] ?? null;
$action = $_POST['action'] ?? '';

if ($id && in_array($action, ['vu', 'terminee'])) {
    if ($action === 'vu') {
        $conn->prepare("UPDATE commandes SET vu_admin = 1 WHERE id = ?")->execute([$id]);
    } elseif ($action === 'terminee') {
        $conn->prepare("UPDATE commandes SET statut = 'Terminée' WHERE id = ?")->execute([$id]);
    }
}

header('Location: commandes.php');
exit;
