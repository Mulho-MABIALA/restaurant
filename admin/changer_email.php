<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

require_once '../config.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if ($username === '' || $email === '') {
        $message = 'Veuillez remplir tous les champs.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Adresse email invalide.';
    } else {
        try {
            $stmt = $conn->prepare("UPDATE admin SET email = ? WHERE username = ?");
            $stmt->execute([$email, $username]);

            if ($stmt->rowCount() > 0) {
                $message = "✅ Email mis à jour avec succès pour l'utilisateur '$username'.";
            } else {
                $message = "⚠️ Aucun changement effectué. L’utilisateur n’existe pas ou l’email est identique.";
            }
        } catch (PDOException $e) {
            $message = '❌ Erreur SQL : ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Modifier l’email d’un administrateur</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-lg mx-auto bg-white p-6 rounded shadow">
        <h2 class="text-2xl font-bold mb-4 text-gray-800">Modifier l’email d’un admin</h2>

        <?php if ($message): ?>
            <div class="mb-4 p-3 bg-blue-100 border border-blue-300 text-blue-800 rounded">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <form method="post" class="space-y-4">
            <div>
                <label class="block font-medium" for="username">Nom d'utilisateur</label>
                <input class="w-full border border-gray-300 rounded p-2" type="text" name="username" id="username" required>
            </div>

            <div>
                <label class="block font-medium" for="email">Nouvelle adresse email</label>
                <input class="w-full border border-gray-300 rounded p-2" type="email" name="email" id="email" required>
            </div>

            <div>
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Mettre à jour</button>
                <a href="dashboard.php" class="ml-4 text-sm text-gray-600 underline">⬅ Retour au tableau de bord</a>
            </div>
        </form>
    </div>
</body>
</html>
