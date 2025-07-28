<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

require_once '../config.php'; // $conn est un PDO

// Suppression d’un client (via GET ?delete=id)
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM clients WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: client.php?deleted=1");
    exit;
}

// Récupération des clients
$stmt = $conn->query("SELECT * FROM clients ORDER BY date_inscription DESC");
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion des clients</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
      <div class="flex h-screen overflow-hidden">
        <?php include 'sidebar.php'; ?>

<div class="container py-5">
    <h1 class="mb-4">👤 Gestion des clients</h1>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-success">Client supprimé avec succès.</div>
    <?php endif; ?>

    <?php if (empty($clients)): ?>
        <div class="alert alert-info">Aucun client trouvé.</div>
    <?php else: ?>
        <table class="table table-bordered table-striped bg-white shadow-sm">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Nom</th>
                    <th>Email</th>
                    <th>Téléphone</th>
                    <th>Inscrit le</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($clients as $client): ?>
                <tr>
                    <td><?= htmlspecialchars($client['id']) ?></td>
                    <td><?= htmlspecialchars($client['nom']) ?></td>
                    <td><?= htmlspecialchars($client['email']) ?></td>
                    <td><?= htmlspecialchars($client['telephone']) ?></td>
                    <td><?= date('d/m/Y H:i', strtotime($client['date_inscription'])) ?></td>
                    <td>
                        <a href="commandes_client.php?id=<?= $client['id'] ?>" class="btn btn-sm btn-primary mb-1">Commandes</a>
                        <a href="reservations_client.php?id=<?= $client['id'] ?>" class="btn btn-sm btn-info text-white mb-1">Réservations</a>
                        <a href="modifier_client.php?id=<?= $client['id'] ?>" class="btn btn-sm btn-warning mb-1">Modifier</a>
                        <a href="client.php?delete=<?= $client['id'] ?>" class="btn btn-sm btn-danger mb-1" onclick="return confirm('Supprimer ce client ?')">Supprimer</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <a href="dashboard.php" class="btn btn-secondary mt-3">⬅ Retour au tableau de bord</a>
</div>

</body>
</html>
