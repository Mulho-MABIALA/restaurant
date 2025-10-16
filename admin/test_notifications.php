<?php
/**
 * Fichier de test pour le système de notifications
 *
 * Ce fichier permet de tester facilement :
 * - La création de réservations de test
 * - La vérification des notifications
 * - Le marquage des réservations comme lues/non lues
 */

session_start();
require_once '../config.php';

// Vérifier que l'admin est connecté
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$message = '';
$type = '';

// Traiter les actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'create_test':
            // Créer une réservation de test
            try {
                $stmt = $conn->prepare("
                    INSERT INTO reservations
                    (nom, email, telephone, date_reservation, heure_reservation, personnes, message, statut, date_envoi)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'non_lu', NOW())
                ");

                $testData = [
                    'Test Client ' . rand(1, 999),
                    'test' . rand(1, 999) . '@example.com',
                    '77' . rand(1000000, 9999999),
                    date('Y-m-d', strtotime('+' . rand(0, 7) . ' days')),
                    sprintf('%02d:%02d:00', rand(11, 22), rand(0, 59)),
                    rand(1, 6),
                    'Message de test pour vérifier les notifications'
                ];

                $stmt->execute($testData);
                $message = '✅ Réservation de test créée avec succès !';
                $type = 'success';
            } catch (Exception $e) {
                $message = '❌ Erreur : ' . $e->getMessage();
                $type = 'error';
            }
            break;

        case 'create_today':
            // Créer une réservation pour aujourd'hui
            try {
                $stmt = $conn->prepare("
                    INSERT INTO reservations
                    (nom, email, telephone, date_reservation, heure_reservation, personnes, message, statut, date_envoi)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'non_lu', NOW())
                ");

                $testData = [
                    'Client Aujourd\'hui ' . rand(1, 999),
                    'today' . rand(1, 999) . '@example.com',
                    '77' . rand(1000000, 9999999),
                    date('Y-m-d'), // Aujourd'hui
                    sprintf('%02d:%02d:00', rand(12, 20), rand(0, 59)),
                    rand(2, 4),
                    'Réservation pour aujourd\'hui - TEST'
                ];

                $stmt->execute($testData);
                $message = '✅ Réservation pour aujourd\'hui créée avec succès !';
                $type = 'success';
            } catch (Exception $e) {
                $message = '❌ Erreur : ' . $e->getMessage();
                $type = 'error';
            }
            break;

        case 'mark_all_unread':
            // Marquer toutes les réservations comme non lues
            try {
                $stmt = $conn->prepare("UPDATE reservations SET statut = 'non_lu'");
                $stmt->execute();
                $affected = $stmt->rowCount();
                $message = "✅ $affected réservation(s) marquée(s) comme non lue(s)";
                $type = 'success';
            } catch (Exception $e) {
                $message = '❌ Erreur : ' . $e->getMessage();
                $type = 'error';
            }
            break;

        case 'mark_all_read':
            // Marquer toutes les réservations comme lues
            try {
                $stmt = $conn->prepare("UPDATE reservations SET statut = 'lu'");
                $stmt->execute();
                $affected = $stmt->rowCount();
                $message = "✅ $affected réservation(s) marquée(s) comme lue(s)";
                $type = 'success';
            } catch (Exception $e) {
                $message = '❌ Erreur : ' . $e->getMessage();
                $type = 'error';
            }
            break;

        case 'delete_test':
            // Supprimer toutes les réservations de test
            try {
                $stmt = $conn->prepare("DELETE FROM reservations WHERE nom LIKE 'Test%' OR nom LIKE 'Client Aujourd%'");
                $stmt->execute();
                $affected = $stmt->rowCount();
                $message = "✅ $affected réservation(s) de test supprimée(s)";
                $type = 'success';
            } catch (Exception $e) {
                $message = '❌ Erreur : ' . $e->getMessage();
                $type = 'error';
            }
            break;
    }
}

// Récupérer les statistiques
$stmt_total = $conn->query("SELECT COUNT(*) as total FROM reservations");
$total_reservations = $stmt_total->fetch()['total'];

$stmt_nouvelles = $conn->query("SELECT COUNT(*) as total FROM reservations WHERE statut = 'non_lu'");
$nouvelles_reservations = $stmt_nouvelles->fetch()['total'];

$stmt_aujourdhui = $conn->prepare("SELECT COUNT(*) as total FROM reservations WHERE date_reservation = ?");
$stmt_aujourdhui->execute([date('Y-m-d')]);
$reservations_aujourdhui = $stmt_aujourdhui->fetch()['total'];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test des Notifications</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50 min-h-screen p-8">

    <div class="max-w-4xl mx-auto">
        <!-- Header -->
        <div class="bg-white rounded-2xl shadow-xl p-6 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800 mb-2">
                        <i class="fas fa-flask text-blue-600 mr-3"></i>
                        Test des Notifications
                    </h1>
                    <p class="text-gray-600">Testez le système de notifications pour les réservations</p>
                </div>
                <a href="reservations.php" class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white px-6 py-3 rounded-xl font-semibold hover:shadow-lg transition-all">
                    <i class="fas fa-arrow-left mr-2"></i>Retour
                </a>
            </div>
        </div>

        <!-- Message de résultat -->
        <?php if ($message): ?>
        <div class="bg-<?php echo $type === 'success' ? 'green' : 'red' ?>-100 border border-<?php echo $type === 'success' ? 'green' : 'red' ?>-200 rounded-xl p-4 mb-6">
            <p class="text-<?php echo $type === 'success' ? 'green' : 'red' ?>-800 font-medium"><?php echo $message ?></p>
        </div>
        <?php endif; ?>

        <!-- Statistiques -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm mb-1">Total</p>
                        <p class="text-3xl font-bold text-gray-900"><?php echo $total_reservations ?></p>
                    </div>
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-calendar-check text-blue-600 text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm mb-1">Non lues</p>
                        <p class="text-3xl font-bold text-amber-600"><?php echo $nouvelles_reservations ?></p>
                    </div>
                    <div class="w-12 h-12 bg-amber-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-bell text-amber-600 text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm mb-1">Aujourd'hui</p>
                        <p class="text-3xl font-bold text-teal-600"><?php echo $reservations_aujourdhui ?></p>
                    </div>
                    <div class="w-12 h-12 bg-teal-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-calendar-day text-teal-600 text-xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions de test -->
        <div class="bg-white rounded-2xl shadow-xl p-6 mb-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">
                <i class="fas fa-tools text-blue-600 mr-2"></i>
                Actions de test
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Créer réservation de test -->
                <form method="post" class="border border-gray-200 rounded-xl p-4 hover:border-blue-500 transition-all">
                    <input type="hidden" name="action" value="create_test">
                    <div class="flex items-start mb-3">
                        <i class="fas fa-plus-circle text-blue-600 text-2xl mr-3 mt-1"></i>
                        <div>
                            <h3 class="font-semibold text-gray-800 mb-1">Créer une réservation de test</h3>
                            <p class="text-sm text-gray-600">Génère une réservation avec des données aléatoires</p>
                        </div>
                    </div>
                    <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 transition-colors">
                        Créer
                    </button>
                </form>

                <!-- Créer réservation aujourd'hui -->
                <form method="post" class="border border-gray-200 rounded-xl p-4 hover:border-teal-500 transition-all">
                    <input type="hidden" name="action" value="create_today">
                    <div class="flex items-start mb-3">
                        <i class="fas fa-calendar-day text-teal-600 text-2xl mr-3 mt-1"></i>
                        <div>
                            <h3 class="font-semibold text-gray-800 mb-1">Réservation pour aujourd'hui</h3>
                            <p class="text-sm text-gray-600">Créer une réservation pour tester les rappels</p>
                        </div>
                    </div>
                    <button type="submit" class="w-full bg-teal-600 text-white py-2 rounded-lg hover:bg-teal-700 transition-colors">
                        Créer
                    </button>
                </form>

                <!-- Marquer toutes comme non lues -->
                <form method="post" class="border border-gray-200 rounded-xl p-4 hover:border-amber-500 transition-all">
                    <input type="hidden" name="action" value="mark_all_unread">
                    <div class="flex items-start mb-3">
                        <i class="fas fa-envelope text-amber-600 text-2xl mr-3 mt-1"></i>
                        <div>
                            <h3 class="font-semibold text-gray-800 mb-1">Marquer comme non lues</h3>
                            <p class="text-sm text-gray-600">Toutes les réservations deviennent "non lues"</p>
                        </div>
                    </div>
                    <button type="submit" class="w-full bg-amber-600 text-white py-2 rounded-lg hover:bg-amber-700 transition-colors">
                        Marquer
                    </button>
                </form>

                <!-- Marquer toutes comme lues -->
                <form method="post" class="border border-gray-200 rounded-xl p-4 hover:border-green-500 transition-all">
                    <input type="hidden" name="action" value="mark_all_read">
                    <div class="flex items-start mb-3">
                        <i class="fas fa-check-circle text-green-600 text-2xl mr-3 mt-1"></i>
                        <div>
                            <h3 class="font-semibold text-gray-800 mb-1">Marquer comme lues</h3>
                            <p class="text-sm text-gray-600">Toutes les réservations deviennent "lues"</p>
                        </div>
                    </div>
                    <button type="submit" class="w-full bg-green-600 text-white py-2 rounded-lg hover:bg-green-700 transition-colors">
                        Marquer
                    </button>
                </form>

                <!-- Supprimer les tests -->
                <form method="post" class="border border-gray-200 rounded-xl p-4 hover:border-red-500 transition-all md:col-span-2" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer toutes les réservations de test ?')">
                    <input type="hidden" name="action" value="delete_test">
                    <div class="flex items-start mb-3">
                        <i class="fas fa-trash text-red-600 text-2xl mr-3 mt-1"></i>
                        <div>
                            <h3 class="font-semibold text-gray-800 mb-1">Supprimer les réservations de test</h3>
                            <p class="text-sm text-gray-600">Supprime uniquement les réservations créées via ce formulaire de test</p>
                        </div>
                    </div>
                    <button type="submit" class="w-full bg-red-600 text-white py-2 rounded-lg hover:bg-red-700 transition-colors">
                        Supprimer les tests
                    </button>
                </form>
            </div>
        </div>

        <!-- Instructions -->
        <div class="bg-gradient-to-r from-blue-600 to-indigo-600 rounded-2xl shadow-xl p-6 text-white">
            <h2 class="text-xl font-bold mb-4">
                <i class="fas fa-info-circle mr-2"></i>
                Comment tester
            </h2>
            <ol class="space-y-2 list-decimal list-inside">
                <li>Ouvrez la page <strong>Réservations</strong> dans un autre onglet</li>
                <li>Revenez ici et créez une réservation de test</li>
                <li>Retournez sur la page Réservations</li>
                <li>Observez :
                    <ul class="list-disc list-inside ml-6 mt-2 space-y-1">
                        <li>Le badge rouge avec le nombre de nouvelles réservations</li>
                        <li>La notification toast dans le coin supérieur droit</li>
                        <li>Le son de notification (si autorisé)</li>
                        <li>Les détails dans le panneau de notifications</li>
                    </ul>
                </li>
                <li>Pour tester les rappels du jour, créez une réservation pour aujourd'hui</li>
            </ol>
        </div>
    </div>

</body>
</html>
