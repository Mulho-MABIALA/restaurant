<?php
/**
 * Migration rapide - Ajout des colonnes horaires pour les plats
 * Exécutez ce fichier une seule fois
 */

session_start();
require_once '../config.php';

// Vérification admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    die("Accès refusé. Veuillez vous connecter en tant qu'administrateur.");
}

$message = '';
$success = false;

try {
    // Vérifier si les colonnes existent déjà
    $stmt = $conn->query("SHOW COLUMNS FROM plats LIKE 'heure_debut'");

    if ($stmt->rowCount() > 0) {
        $message = "✅ Les colonnes horaires existent déjà dans la table plats. Aucune action nécessaire.";
        $success = true;
    } else {
        // Ajouter les colonnes
        $conn->exec("
            ALTER TABLE plats
            ADD COLUMN heure_debut TIME DEFAULT NULL COMMENT 'Heure de début de disponibilité',
            ADD COLUMN heure_fin TIME DEFAULT NULL COMMENT 'Heure de fin de disponibilité',
            ADD COLUMN disponibilite_active TINYINT(1) DEFAULT 0 COMMENT '1 = horaires actifs, 0 = toujours disponible'
        ");

        $message = "✅ Migration réussie ! Les colonnes heure_debut, heure_fin et disponibilite_active ont été ajoutées à la table plats.";
        $success = true;
    }

} catch (PDOException $e) {
    $message = "❌ Erreur lors de la migration : " . $e->getMessage();
    $success = false;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migration - Horaires Plats</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-xl p-8 max-w-2xl w-full">
            <div class="text-center mb-6">
                <div class="w-20 h-20 mx-auto mb-4 rounded-full flex items-center justify-center <?= $success ? 'bg-green-100' : 'bg-red-100' ?>">
                    <i class="fas <?= $success ? 'fa-check text-green-500' : 'fa-times text-red-500' ?> text-4xl"></i>
                </div>
                <h1 class="text-3xl font-bold text-gray-800 mb-2">Migration Base de Données</h1>
                <p class="text-gray-600">Ajout des horaires de disponibilité aux plats</p>
            </div>

            <div class="<?= $success ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200' ?> border-l-4 <?= $success ? 'border-l-green-500' : 'border-l-red-500' ?> p-6 rounded-r-lg mb-6">
                <p class="text-lg"><?= $message ?></p>
            </div>

            <?php if ($success): ?>
            <div class="space-y-4 mb-6">
                <h2 class="font-bold text-gray-800 text-xl">📋 Colonnes ajoutées à la table plats:</h2>
                <ul class="space-y-2 text-gray-700">
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                        <div>
                            <strong>heure_debut</strong> (TIME) - Heure de début de disponibilité du plat
                        </div>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                        <div>
                            <strong>heure_fin</strong> (TIME) - Heure de fin de disponibilité du plat
                        </div>
                    </li>
                    <li class="flex items-start">
                        <i class="fas fa-check-circle text-green-500 mt-1 mr-2"></i>
                        <div>
                            <strong>disponibilite_active</strong> (TINYINT) - Active ou désactive les horaires du plat
                        </div>
                    </li>
                </ul>
            </div>

            <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded-r-lg mb-6">
                <h3 class="font-bold text-blue-800 mb-2">💡 Prochaines étapes:</h3>
                <ol class="list-decimal list-inside space-y-1 text-blue-700 text-sm">
                    <li>Allez dans <strong>Ajouter un Plat</strong></li>
                    <li>Configurez les horaires pour chaque plat (ex: Poulet 10h-15h)</li>
                    <li>Les plats seront automatiquement bloqués en dehors des horaires</li>
                    <li>Vous pouvez aussi modifier les plats existants dans <strong>Gestion des Plats</strong></li>
                </ol>
            </div>
            <?php endif; ?>

            <div class="flex flex-col sm:flex-row gap-3">
                <a href="ajouter_plat.php" class="flex-1 bg-gradient-to-r from-blue-500 to-purple-500 text-white px-6 py-3 rounded-xl font-semibold text-center hover:shadow-lg transition-all">
                    <i class="fas fa-plus mr-2"></i>
                    Ajouter un Plat
                </a>
                <a href="gestion_plats.php" class="flex-1 bg-gradient-to-r from-green-500 to-teal-500 text-white px-6 py-3 rounded-xl font-semibold text-center hover:shadow-lg transition-all">
                    <i class="fas fa-list mr-2"></i>
                    Gérer les Plats
                </a>
                <a href="dashboard.php" class="flex-1 bg-gray-200 text-gray-700 px-6 py-3 rounded-xl font-semibold text-center hover:bg-gray-300 transition-all">
                    <i class="fas fa-home mr-2"></i>
                    Dashboard
                </a>
            </div>
        </div>
    </div>
</body>
</html>
