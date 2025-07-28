<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

require_once '../config.php'; // contient $conn = new PDO(...);

$client_id = intval($_GET['id'] ?? 0);
if ($client_id <= 0) {
    die("ID client invalide.");
}

// 1. Récupérer les données existantes du client
$stmt = $conn->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->execute([$client_id]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$client) {
    die("Client introuvable.");
}

// 2. Traitement du formulaire de mise à jour
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom       = trim($_POST['nom']);
    $email     = trim($_POST['email']);
    $telephone = trim($_POST['telephone']);

    // Validation basique (tu peux ajouter plus de vérifs)
    if ($nom === '' || $email === '') {
        $error = "Le nom et l'email sont obligatoires.";
    } else {
        $update = $conn->prepare(
            "UPDATE clients 
             SET nom = :nom, email = :email, telephone = :telephone 
             WHERE id = :id"
        );
        $update->execute([
            ':nom'       => $nom,
            ':email'     => $email,
            ':telephone' => $telephone,
            ':id'        => $client_id
        ]);

        // Redirection vers la liste avec message de succès
        header("Location: client.php?updated=1");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Modifier le client</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">

<div class="container py-5">
    <h2 class="mb-4">✏️ Modifier le client : <?= htmlspecialchars($client['nom']) ?></h2>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" class="bg-white p-4 rounded shadow-sm">
        <div class="mb-3">
            <label for="nom" class="form-label">Nom *</label>
            <input 
                type="text" 
                id="nom" 
                name="nom" 
                class="form-control" 
                required 
                value="<?= htmlspecialchars($_POST['nom'] ?? $client['nom']) ?>"
            >
        </div>
        <div class="mb-3">
            <label for="email" class="form-label">Email *</label>
            <input 
                type="email" 
                id="email" 
                name="email" 
                class="form-control" 
                required 
                value="<?= htmlspecialchars($_POST['email'] ?? $client['email']) ?>"
            >
        </div>
        <div class="mb-3">
            <label for="telephone" class="form-label">Téléphone</label>
            <input 
                type="text" 
                id="telephone" 
                name="telephone" 
                class="form-control" 
                value="<?= htmlspecialchars($_POST['telephone'] ?? $client['telephone']) ?>"
            >
        </div>

        <button type="submit" class="btn btn-success">Enregistrer les modifications</button>
        <a href="client.php" class="btn btn-secondary">Annuler</a>
    </form>
</div>

</body>
</html>
