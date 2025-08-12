<?php
session_start();
require_once '../config.php';


if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID de commande invalide.");
}

$id = (int) $_GET['id'];

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $statut = $_POST['statut'] ?? '';
    $vu_admin = isset($_POST['vu_admin']) ? 1 : 0;

    $stmt = $conn->prepare("UPDATE commandes SET statut = ?, vu_admin = ? WHERE id = ?");
    $stmt->execute([$statut, $vu_admin, $id]);

    header('Location: commandes.php');
    exit;
}

// Récupérer la commande
$stmt = $conn->prepare("SELECT * FROM commandes WHERE id = ?");
$stmt->execute([$id]);
$commande = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$commande) {
    die("Commande non trouvée.");
}

// Liste des statuts possibles (exemple)
$statuts = ['En cours', 'Préparation en cours', 'Terminée', 'Annulée'];
?>

<!DOCTYPE html>
<html>
<head>
    <title>Modifier commande #<?= htmlspecialchars($commande['id']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
    <h2>Modifier la commande #<?= htmlspecialchars($commande['id']) ?></h2>

    <form method="post" class="w-50">
        <div class="mb-3">
            <label class="form-label">Statut</label>
            <select name="statut" class="form-select" required>
                <?php foreach ($statuts as $statutOption): ?>
                    <option value="<?= $statutOption ?>" <?= ($commande['statut'] === $statutOption) ? 'selected' : '' ?>>
                        <?= $statutOption ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-check mb-3">
            <input type="checkbox" class="form-check-input" id="vu_admin" name="vu_admin" <?= $commande['vu_admin'] ? 'checked' : '' ?>>
            <label for="vu_admin" class="form-check-label">Vu par l'admin</label>
        </div>

        <button type="submit" class="btn btn-primary">Enregistrer</button>
        <a href="commandes.php" class="btn btn-secondary">Annuler</a>
    </form>
</body>
</html>
