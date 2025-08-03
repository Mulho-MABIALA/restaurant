<?php
require_once '../config.php';
session_start();

// Vérifier si l'utilisateur est connecté

$employe_id = (int) $_SESSION['admin_id'];
$nom_employe = $_SESSION['admin_name'] ?? 'Utilisateur';  // Fallback si jamais absent

// Traitement du pointage
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $type = $_POST['action']; // 'entree' ou 'sortie'
    $geoloc = $_POST['geoloc'] ?? null;
    $now = date('Y-m-d H:i:s');
    $today = date('Y-m-d');

    // Empêcher de pointer 2 fois le même type dans la journée
    $stmt = $conn->prepare("SELECT COUNT(*) FROM pointages WHERE employe_id = ? AND type = ? AND DATE(created_at) = ?");
    $stmt->execute([$employe_id, $type, $today]);

    if ($stmt->fetchColumn() > 0) {
        $message = "⚠️ Vous avez déjà pointé une $type aujourd’hui.";
    } else {
        // Insertion du pointage
        $stmt = $conn->prepare("INSERT INTO pointages (employe_id, type, created_at, geoloc) VALUES (?, ?, ?, ?)");
        $stmt->execute([$employe_id, $type, $now, $geoloc]);

        if ($type === 'sortie') {
            // Récupérer l'heure d'entrée
            $stmt = $conn->prepare("SELECT created_at FROM pointages WHERE employe_id = ? AND type = 'entree' AND DATE(created_at) = ? ORDER BY created_at ASC LIMIT 1");
            $stmt->execute([$employe_id, $today]);
            $entree = $stmt->fetchColumn();

            if ($entree) {
                $duree = strtotime($now) - strtotime($entree);
                $heures = floor($duree / 3600);
                $minutes = floor(($duree % 3600) / 60);
                $message = "✅ Sortie enregistrée. Durée travaillée : $heures h $minutes min.";

                // Détection du retard
                if (strtotime($entree) > strtotime("$today 09:00:00")) {
                    $message .= " 🚨 Retard détecté à l'entrée.";
                }

                // Alerte manager si dépassement 10h
                if ($duree >= 10 * 3600) {
                    @mail("manager@example.com", "Dépassement horaire", "$nom_employe a travaillé plus de 10h aujourd’hui.");
                    $message .= " 📧 Alerte envoyée au manager.";
                }
            } else {
                $message = "⚠️ Impossible de calculer la durée : aucune entrée trouvée.";
            }
        } else {
            $message = "✅ Entrée enregistrée.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Badgeuse</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script>
    window.onload = () => {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(position => {
                const lat = position.coords.latitude.toFixed(6);
                const lon = position.coords.longitude.toFixed(6);
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'geoloc';
                input.value = `${lat},${lon}`;
                document.getElementById('pointageForm').appendChild(input);
            });
        }
    };
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen p-4">
    <div class="bg-white shadow-lg rounded-xl p-6 w-full max-w-md">
        <h1 class="text-2xl font-bold mb-6 text-center text-indigo-600">📍 Système de Pointage</h1>

        <?php if (!empty($message)): ?>
            <div class="mb-4 p-4 bg-yellow-100 text-yellow-800 rounded-lg">
                <?= htmlspecialchars((string)$message, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="pointageForm" class="space-y-4">
            <button name="action" value="entree" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 rounded-lg text-lg">
                📥 Pointer Entrée
            </button>
            <button name="action" value="sortie" class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-3 rounded-lg text-lg">
                📤 Pointer Sortie
            </button>
        </form>

        <p class="mt-6 text-sm text-center text-gray-400">
            Connecté en tant que <strong><?= htmlspecialchars((string)$nom_employe, ENT_QUOTES, 'UTF-8') ?></strong>
        </p>
    </div>
</body>
</html>
