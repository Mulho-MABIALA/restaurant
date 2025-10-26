<?php
session_start();
require_once '../config.php';
require_once 'includes/permissions.php';

// Rediriger si l'admin n'est pas connecté
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
requireAccess($conn, $_SESSION['admin_id'], 'avis_admin');

// Traitement des actions
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    if ($id > 0) {
        try {
            if ($action === 'supprimer') {
                $stmt = $conn->prepare("DELETE FROM avis WHERE id = ?");
                $stmt->execute([$id]);
                $message = "Avis supprimé avec succès";
            }
            elseif ($action === 'valider') {
                $stmt = $conn->prepare("UPDATE avis SET valide = 1 WHERE id = ?");
                $stmt->execute([$id]);
                $message = "Avis validé avec succès";
            }
            elseif ($action === 'invalider') {
                $stmt = $conn->prepare("UPDATE avis SET valide = 0 WHERE id = ?");
                $stmt->execute([$id]);
                $message = "Avis invalidé avec succès";
            }
        } catch (Exception $e) {
            $error = "Erreur lors de l'opération: " . $e->getMessage();
        }
    }
}

// Récupération de tous les avis
try {
    $stmt = $conn->prepare("SELECT * FROM avis ORDER BY date_creation DESC");
    $stmt->execute();
    $avis = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Statistiques
    $totalAvis = count($avis);
    $avisValides = count(array_filter($avis, fn($a) => $a['valide'] == 1));
    $avisEnAttente = $totalAvis - $avisValides;
    $moyenneNote = $totalAvis > 0 ? round(array_sum(array_column($avis, 'note')) / $totalAvis, 1) : 0;
} catch (Exception $e) {
    $error = "Erreur lors de la récupération des avis: " . $e->getMessage();
    $avis = [];
    $totalAvis = 0;
    $avisValides = 0;
    $avisEnAttente = 0;
    $moyenneNote = 0;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Avis - <?php echo defined('RESTAURANT_NAME') ? RESTAURANT_NAME : 'Restaurant'; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');

        body {
            font-family: 'Inter', sans-serif;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .stat-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .avis-row {
            transition: all 0.3s ease;
        }

        .avis-row:hover {
            background: rgba(99, 102, 241, 0.05);
            transform: scale(1.01);
        }

        .animate-fade-in {
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 via-gray-100 to-gray-200">

    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Header -->
            <header class="bg-white shadow-lg z-10">
                <div class="flex items-center justify-between px-8 py-4">
                    <div>
                        <h1 class="text-3xl font-bold bg-gradient-to-r from-indigo-600 to-purple-600 bg-clip-text text-transparent">
                            Gestion des Avis Clients
                        </h1>
                        <p class="text-gray-600 mt-1">Modérez et gérez les avis anonymes</p>
                    </div>

                    <div class="flex items-center space-x-4">
                        <button class="relative p-2 text-gray-600 hover:bg-gray-100 rounded-xl transition-all">
                            <i class="fas fa-bell text-xl"></i>
                            <?php if ($avisEnAttente > 0): ?>
                            <span class="absolute top-0 right-0 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                                <?= $avisEnAttente ?>
                            </span>
                            <?php endif; ?>
                        </button>
                        <div class="w-10 h-10 bg-gradient-to-r from-indigo-500 to-purple-600 rounded-xl flex items-center justify-center text-white font-bold">
                            A
                        </div>
                    </div>
                </div>
            </header>

            <!-- Content Area -->
            <main class="flex-1 overflow-y-auto p-8">
                <!-- Messages d'alerte -->
                <?php if (isset($message)): ?>
                <div class="mb-6 animate-fade-in">
                    <div class="bg-green-50 border-l-4 border-green-500 p-4 rounded-lg shadow-md">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle text-green-500 text-xl mr-3"></i>
                            <p class="text-green-800 font-medium"><?= $message ?></p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                <div class="mb-6 animate-fade-in">
                    <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-lg shadow-md">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-circle text-red-500 text-xl mr-3"></i>
                            <p class="text-red-800 font-medium"><?= $error ?></p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Info Banner -->
                <div class="mb-6 animate-fade-in">
                    <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded-lg shadow-md">
                        <div class="flex items-center">
                            <i class="fas fa-info-circle text-blue-500 text-xl mr-3"></i>
                            <p class="text-blue-800"><strong>Confidentialité :</strong> Tous les avis sont anonymes. Les noms et emails ne sont pas collectés.</p>
                        </div>
                    </div>
                </div>

                <!-- Statistiques Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8 animate-fade-in">
                    <!-- Total Avis -->
                    <div class="stat-card glass-card rounded-2xl p-6 shadow-lg">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-600 text-sm font-medium">Total Avis</p>
                                <h3 class="text-3xl font-bold text-gray-800 mt-2"><?= $totalAvis ?></h3>
                            </div>
                            <div class="bg-gradient-to-br from-blue-500 to-blue-600 p-4 rounded-xl">
                                <i class="fas fa-comments text-white text-2xl"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Avis Validés -->
                    <div class="stat-card glass-card rounded-2xl p-6 shadow-lg">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-600 text-sm font-medium">Validés</p>
                                <h3 class="text-3xl font-bold text-green-600 mt-2"><?= $avisValides ?></h3>
                            </div>
                            <div class="bg-gradient-to-br from-green-500 to-green-600 p-4 rounded-xl">
                                <i class="fas fa-check-circle text-white text-2xl"></i>
                            </div>
                        </div>
                    </div>

                    <!-- En Attente -->
                    <div class="stat-card glass-card rounded-2xl p-6 shadow-lg">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-600 text-sm font-medium">En Attente</p>
                                <h3 class="text-3xl font-bold text-orange-600 mt-2"><?= $avisEnAttente ?></h3>
                            </div>
                            <div class="bg-gradient-to-br from-orange-500 to-orange-600 p-4 rounded-xl">
                                <i class="fas fa-clock text-white text-2xl"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Note Moyenne -->
                    <div class="stat-card glass-card rounded-2xl p-6 shadow-lg">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-600 text-sm font-medium">Note Moyenne</p>
                                <h3 class="text-3xl font-bold text-yellow-600 mt-2"><?= $moyenneNote ?>/5</h3>
                            </div>
                            <div class="bg-gradient-to-br from-yellow-500 to-yellow-600 p-4 rounded-xl">
                                <i class="fas fa-star text-white text-2xl"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Table des Avis -->
                <div class="glass-card rounded-2xl shadow-xl overflow-hidden animate-fade-in">
                    <div class="px-8 py-6 bg-gradient-to-r from-indigo-600 to-purple-600">
                        <h2 class="text-2xl font-bold text-white flex items-center">
                            <i class="fas fa-list mr-3"></i>
                            Liste des Avis
                        </h2>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-100 border-b-2 border-gray-200">
                                <tr>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">ID</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Message</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Note</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Statut</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (count($avis) > 0): ?>
                                    <?php foreach ($avis as $index => $avi): ?>
                                    <tr class="avis-row" style="animation-delay: <?= $index * 0.05 ?>s">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="text-sm font-bold text-gray-900">#<?= $avi['id'] ?></span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <p class="text-sm text-gray-700 line-clamp-2 max-w-md">
                                                <?= nl2br(htmlspecialchars(substr($avi['message'], 0, 100))) ?>
                                                <?= strlen($avi['message']) > 100 ? '...' : '' ?>
                                            </p>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center space-x-1">
                                                <?php for($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star <?= $i <= $avi['note'] ? 'text-yellow-400' : 'text-gray-300' ?> text-sm"></i>
                                                <?php endfor; ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="text-sm text-gray-600">
                                                <?= date('d/m/Y', strtotime($avi['date_creation'])) ?>
                                            </span>
                                            <br>
                                            <span class="text-xs text-gray-400">
                                                <?= date('H:i', strtotime($avi['date_creation'])) ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php if ($avi['valide']): ?>
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                    <i class="fas fa-check-circle mr-1"></i>
                                                    Validé
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-800">
                                                    <i class="fas fa-clock mr-1"></i>
                                                    En attente
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <div class="flex items-center space-x-2">
                                                <?php if (!$avi['valide']): ?>
                                                    <a href="?action=valider&id=<?= $avi['id'] ?>"
                                                       class="inline-flex items-center px-3 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg transition-all duration-300 shadow-md hover:shadow-lg"
                                                       title="Valider">
                                                        <i class="fas fa-check"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <a href="?action=invalider&id=<?= $avi['id'] ?>"
                                                       class="inline-flex items-center px-3 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg transition-all duration-300 shadow-md hover:shadow-lg"
                                                       title="Invalider">
                                                        <i class="fas fa-times"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <a href="?action=supprimer&id=<?= $avi['id'] ?>"
                                                   class="inline-flex items-center px-3 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg transition-all duration-300 shadow-md hover:shadow-lg"
                                                   title="Supprimer"
                                                   onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet avis ?')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="px-6 py-12 text-center">
                                            <div class="flex flex-col items-center justify-center">
                                                <i class="fas fa-inbox text-6xl text-gray-300 mb-4"></i>
                                                <p class="text-gray-500 text-lg font-medium">Aucun avis pour le moment</p>
                                                <p class="text-gray-400 text-sm mt-2">Les avis apparaîtront ici une fois soumis</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Footer -->
                <footer class="mt-8 text-center text-gray-600 text-sm">
                    <p>&copy; <?= date('Y') ?> <?php echo defined('RESTAURANT_NAME') ? RESTAURANT_NAME : 'Restaurant'; ?>. Tous droits réservés.</p>
                </footer>
            </main>
        </div>
    </div>

    <script>
        // Animation au chargement
        document.addEventListener('DOMContentLoaded', function() {
            const rows = document.querySelectorAll('.avis-row');
            rows.forEach((row, index) => {
                setTimeout(() => {
                    row.classList.add('animate-fade-in');
                }, index * 50);
            });
        });

        // Auto-hide alerts après 5 secondes
        setTimeout(() => {
            const alerts = document.querySelectorAll('.animate-fade-in > div');
            alerts.forEach(alert => {
                if (alert.classList.contains('bg-green-50') || alert.classList.contains('bg-red-50')) {
                    alert.style.transition = 'opacity 0.5s';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                }
            });
        }, 5000);
    </script>
</body>
</html>
