<?php
// admin.php
session_start();
require_once 'config.php';

// Vérification de l'authentification admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

// Traitement du changement de statut
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['commande_id']) && isset($_POST['nouveau_statut'])) {
    $commande_id = $_POST['commande_id'];
    $nouveau_statut = $_POST['nouveau_statut'];
    
    $stmt = $conn->prepare("UPDATE commandes SET statut = ? WHERE id = ?");
    $stmt->execute([$nouveau_statut, $commande_id]);
    
    // Ajouter une notification
    $notif = $conn->prepare("INSERT INTO notifications (message, type, date, vue) VALUES (?, ?, NOW(), 0)");
    $notif->execute(["Le statut de la commande #$commande_id a été modifié en '$nouveau_statut'.", 'info']);
    
    header("Location: admin.php?success=1");
    exit;
}

// Traitement de la suppression
if (isset($_GET['supprimer'])) {
    $commande_id = $_GET['supprimer'];
    
    // Commencer une transaction
    $conn->beginTransaction();
    
    try {
        // Récupérer les détails de la commande pour mise à jour du stock
        $stmt = $conn->prepare("SELECT * FROM commande_details WHERE commande_id = ?");
        $stmt->execute([$commande_id]);
        $details = $stmt->fetchAll();
        
        foreach ($details as $detail) {
            // Trouver l'ID du plat par son nom
            $stmt = $conn->prepare("SELECT id FROM plats WHERE nom = ?");
            $stmt->execute([$detail['nom_plat']]);
            $plat = $stmt->fetch();
            
            if ($plat) {
                // Mettre à jour le stock
                $update = $conn->prepare("UPDATE plats SET stock = stock + ? WHERE id = ?");
                $update->execute([$detail['quantite'], $plat['id']]);
            }
        }
        
        // Supprimer les détails de la commande
        $stmt = $conn->prepare("DELETE FROM commande_details WHERE commande_id = ?");
        $stmt->execute([$commande_id]);
        
        // Supprimer la commande principale
        $stmt = $conn->prepare("DELETE FROM commandes WHERE id = ?");
        $stmt->execute([$commande_id]);
        
        $conn->commit();
        
        // Notification
        $notif = $conn->prepare("INSERT INTO notifications (message, type, date, vue) VALUES (?, ?, NOW(), 0)");
        $notif->execute(["La commande #$commande_id a été supprimée.", 'warning']);
        
        header("Location: admin.php?success=2");
        exit;
        
    } catch (Exception $e) {
        $conn->rollBack();
        die("Erreur lors de la suppression: " . $e->getMessage());
    }
}

// Marquer toutes les notifications comme vues
if (isset($_GET['marquer_vues'])) {
    $update = $conn->prepare("UPDATE notifications SET vue = 1");
    $update->execute();
    header("Location: admin.php");
    exit;
}

// Récupérer les commandes
$stmt = $conn->prepare("SELECT * FROM commandes ORDER BY date_commande DESC");
$stmt->execute();
$commandes = $stmt->fetchAll();

// Récupérer les notifications non lues
$stmt = $conn->prepare("SELECT * FROM notifications WHERE vue = 0 ORDER BY date DESC");
$stmt->execute();
$notifications = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - Commandes Clients</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#3b82f6',
                        'primary-dark': '#2563eb',
                        secondary: '#10b981',
                        danger: '#ef4444'
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-100">
    <!-- Barre de navigation admin -->
    <nav class="bg-gray-800 text-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <span class="text-xl font-bold">🍔 Admin Restaurant</span>
                    </div>
                    <div class="hidden md:block">
                        <div class="ml-10 flex items-baseline space-x-4">
                            <a href="admin.php" class="px-3 py-2 rounded-md text-sm font-medium bg-gray-900 text-white">Commandes</a>
                            <a href="admin_plats.php" class="px-3 py-2 rounded-md text-sm font-medium text-gray-300 hover:bg-gray-700 hover:text-white">Gestion Plats</a>
                            <a href="admin_stats.php" class="px-3 py-2 rounded-md text-sm font-medium text-gray-300 hover:bg-gray-700 hover:text-white">Statistiques</a>
                        </div>
                    </div>
                </div>
                <div class="flex items-center">
                    <div class="relative ml-3">
                        <div class="flex items-center space-x-4">
                            <a href="?marquer_vues=1" class="relative p-1 text-gray-300 hover:text-white">
                                <i class="fas fa-bell text-xl"></i>
                                <?php if (count($notifications) > 0): ?>
                                    <span class="absolute top-0 right-0 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white transform translate-x-1/2 -translate-y-1/2 bg-red-600 rounded-full">
                                        <?= count($notifications) ?>
                                    </span>
                                <?php endif; ?>
                            </a>
                            <a href="deconnexion.php" class="px-4 py-2 bg-red-600 hover:bg-red-700 rounded-md text-sm font-medium">
                                <i class="fas fa-sign-out-alt mr-2"></i>Déconnexion
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Notifications -->
        <?php if (count($notifications) > 0): ?>
            <div class="mb-6 bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded-lg">
                <div class="flex justify-between items-center">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle text-yellow-400 text-xl mr-3"></i>
                        <p class="text-yellow-700 font-medium">
                            Vous avez <?= count($notifications) ?> notification(s) non lue(s)
                        </p>
                    </div>
                    <a href="?marquer_vues=1" class="text-sm text-yellow-700 hover:text-yellow-900 underline">
                        Marquer comme lues
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Messages de succès -->
        <?php if (isset($_GET['success'])): ?>
            <div class="mb-6 rounded-md bg-green-50 p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-green-800">
                            <?php 
                                if ($_GET['success'] == 1) echo "Statut de commande mis à jour avec succès!";
                                if ($_GET['success'] == 2) echo "Commande supprimée avec succès!";
                            ?>
                        </p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="bg-white shadow-xl rounded-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-2xl font-bold text-gray-800">Commandes Clients</h2>
                <p class="mt-1 text-gray-600">Gestion des commandes passées par les clients</p>
            </div>
            
            <!-- Tableau des commandes -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mode</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($commandes as $commande): ?>
                        <?php 
                            $statusColor = 'bg-gray-100 text-gray-800';
                            if ($commande['statut'] == 'En cours') $statusColor = 'bg-yellow-100 text-yellow-800';
                            if ($commande['statut'] == 'Prête') $statusColor = 'bg-blue-100 text-blue-800';
                            if ($commande['statut'] == 'Livrée') $statusColor = 'bg-green-100 text-green-800';
                            if ($commande['statut'] == 'Annulée') $statusColor = 'bg-red-100 text-red-800';
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">#<?= str_pad($commande['id'], 6, '0', STR_PAD_LEFT) ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($commande['nom_client']) ?></div>
                                <div class="text-sm text-gray-500"><?= htmlspecialchars($commande['email']) ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900"><?= date('d/m/Y', strtotime($commande['date_commande'])) ?></div>
                                <div class="text-sm text-gray-500"><?= date('H:i', strtotime($commande['date_commande'])) ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?= number_format($commande['total'], 0) ?> fcfa
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <form method="POST" class="flex items-center">
                                    <input type="hidden" name="commande_id" value="<?= $commande['id'] ?>">
                                    <select name="nouveau_statut" onchange="this.form.submit()" class="<?= $statusColor ?> text-xs font-semibold px-2.5 py-0.5 rounded-full cursor-pointer">
                                        <option value="En cours" <?= $commande['statut'] == 'En cours' ? 'selected' : '' ?>>En cours</option>
                                        <option value="Prête" <?= $commande['statut'] == 'Prête' ? 'selected' : '' ?>>Prête</option>
                                        <option value="Livrée" <?= $commande['statut'] == 'Livrée' ? 'selected' : '' ?>>Livrée</option>
                                        <option value="Annulée" <?= $commande['statut'] == 'Annulée' ? 'selected' : '' ?>>Annulée</option>
                                    </select>
                                </form>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= htmlspecialchars($commande['mode_retrait']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <a href="admin_commande_details.php?id=<?= $commande['id'] ?>" class="text-primary hover:text-primary-dark mr-3">
                                    <i class="fas fa-eye mr-1"></i> Détails
                                </a>
                                <a href="?supprimer=<?= $commande['id'] ?>" class="text-red-600 hover:text-red-900" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette commande ?');">
                                    <i class="fas fa-trash-alt mr-1"></i> Supprimer
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if (empty($commandes)): ?>
                <div class="text-center py-12">
                    <i class="fas fa-clipboard-list text-4xl text-gray-300 mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900">Aucune commande</h3>
                    <p class="mt-1 text-sm text-gray-500">Aucune commande n'a été passée pour le moment.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Auto-refresh toutes les 2 minutes
        setTimeout(() => {
            window.location.reload();
        }, 120000);
    </script>
</body>
</html>