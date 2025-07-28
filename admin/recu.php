<?php
require_once '../config.php';
session_start();

// Vérifie que l’admin est connecté
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Récupération de l'ID de commande via l'URL
$commande_id = $_GET['id'] ?? null;

if (!$commande_id) {
    die("ID de commande non fourni.");
}

try {
    // Requête préparée avec paramètre lié
    $stmt = $conn->prepare("SELECT recu_html FROM commandes WHERE id = :id");
    $stmt->execute(['id' => $commande_id]);

    $commande = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$commande) {
        die("Commande introuvable.");
    }

    // Affiche le HTML du reçu
    echo $commande['recu_html'];

} catch (PDOException $e) {
    die("Erreur SQL : " . $e->getMessage());
}
?>
