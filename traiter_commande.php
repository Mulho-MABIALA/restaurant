<?php
session_start();
require_once 'config.php';

$paiement = $_POST['paiement'] ?? null;
$nom = $_POST['nom'] ?? null;
$telephone = $_POST['telephone'] ?? null;
$adresse = $_POST['adresse'] ?? null;
$total = $_POST['total'] ?? null;
$panier = $_POST['panier'] ?? null;

if (!$paiement || !$nom || !$telephone || !$adresse || !$total || !$panier) {
    die("Informations manquantes.");
}

// Enregistrement de la commande
try {
    $stmt = $conn->prepare("INSERT INTO commandes (nom, telephone, adresse, total, panier, paiement, statut) VALUES (?, ?, ?, ?, ?, ?, 'en attente')");
    $stmt->execute([
        $nom,
        $telephone,
        $adresse,
        $total,
        $panier, // JSON ou texte selon ton format
        $paiement
    ]);

    $commande_id = $conn->lastInsertId();
    $_SESSION['commande_id'] = $commande_id;

    // Redirection
    if ($paiement === 'livraison') {
        header("Location: confirmation.php?via=livraison");
    } elseif ($paiement === 'en ligne') {
        header("Location: payer.php?commande_id=$commande_id");
    } else {
        die("Mode de paiement invalide.");
    }
    exit;

} catch (Exception $e) {
    die("Erreur lors de l'enregistrement : " . $e->getMessage());
}
