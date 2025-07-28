<?php
require_once '../config.php';
session_start();

// Protection admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Fonction de sécurité pour l'affichage
function e($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// Vérification ID de commande
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID de commande invalide.");
}

$id = (int) $_GET['id'];

// Récupération de la commande
$stmt = $conn->prepare("SELECT * FROM commandes WHERE id = ?");
$stmt->execute([$id]);
$commande = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$commande) {
    die("Commande non trouvée.");
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Détails commande #<?= e($commande['id']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
    <div class="container">
        <h2 class="mb-4">Détails de la commande #<?= e($commande['id']) ?></h2>

        <table class="table table-bordered w-50">
            <tr><th>Client</th><td><?= e($commande['nom_client']) ?></td></tr>
            <tr><th>Email</th><td><?= e($commande['email']) ?></td></tr>
            <tr><th>Téléphone</th><td><?= e($commande['telephone']) ?></td></tr>
            <tr><th>Mode de retrait</th><td><?= e($commande['mode_retrait']) ?></td></tr>
            <tr><th>Adresse</th><td><?= nl2br(e($commande['adresse'])) ?></td></tr>
            <tr><th>Total</th><td><?= number_format((float)$commande['total'], 2) ?> €</td></tr>
            <tr><th>Date commande</th><td><?= e($commande['date_commande']) ?: 'Non disponible' ?></td></tr>
            <tr><th>Statut</th><td><?= e($commande['statut']) ?: 'Non défini' ?></td></tr>
            <tr><th>Vu admin</th><td><?= $commande['vu_admin'] ? '✅' : '❌' ?></td></tr>
        </table>

        <a href="commandes.php" class="btn btn-secondary">Retour à la liste</a>
    </div>
</body>
</html>
