<?php
require_once '../config.php';
session_start();

// Vérifie si l’admin est connecté
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Vérifie l'ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: gestion_stock.php');
    exit;
}

$id = (int) $_GET['id'];

// Traitement du formulaire (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $quantite = (int) $_POST['quantite'];
    $seuil = (int) $_POST['seuil_alerte'];

    $update = $conn->prepare("UPDATE stocks SET quantite = :quantite, seuil_alerte = :seuil WHERE id = :id");
    $update->execute([
        ':quantite' => $quantite,
        ':seuil' => $seuil,
        ':id' => $id
    ]);

    header("Location: gestion_stock.php?modifie=1");
    exit;
}

// Récupération de l'ingrédient
$stmt = $conn->prepare("SELECT * FROM stocks WHERE id = :id");
$stmt->execute([':id' => $id]);
$stock = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$stock) {
    echo "Ingrédient introuvable.";
    exit;
}
?>

<!-- Formulaire HTML -->
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Modifier l'ingrédient</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">

<div class="container py-5">
    <h2>Modifier l’ingrédient : <?= htmlspecialchars($stock['nom']) ?></h2>

    <form method="post" class="mt-4 bg-white p-4 rounded shadow-sm">
        <div class="mb-3">
            <label class="form-label">Quantité</label>
            <input type="number" name="quantite" class="form-control" required value="<?= htmlspecialchars($stock['quantite']) ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">Seuil d'alerte</label>
            <input type="number" name="seuil_alerte" class="form-control" required value="<?= htmlspecialchars($stock['seuil_alerte']) ?>">
        </div>
        <button type="submit" class="btn btn-success">Enregistrer</button>
        <a href="gestion_stock.php" class="btn btn-secondary">Annuler</a>
    </form>
</div>

</body>
</html>
