<?php
require_once '../config.php'; // Connexion à la base
session_start();
// Vérifie que l'admin est connecté
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Traitement du formulaire d'ajout
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'ajouter') {
    $nom = $_POST['nom'];
    $quantite = $_POST['quantite'];
    $unite = $_POST['unite'];
    $seuil_alerte = $_POST['seuil_alerte'];
    
    $stmt = $conn->prepare("INSERT INTO stocks (nom, quantite, unite, seuil_alerte) VALUES (?, ?, ?, ?)");
    $success = $stmt->execute([$nom, $quantite, $unite, $seuil_alerte]);
    
    if ($success) {
        $message = "Ingrédient ajouté avec succès !";
        $message_type = "success";
    } else {
        $message = "Erreur lors de l'ajout de l'ingrédient.";
        $message_type = "error";
    }
}

// Récupération des stocks
$result = $conn->query("SELECT * FROM stocks");
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des stocks</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100">
    <div class="flex h-screen overflow-hidden">
        <?php include 'sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Header -->
            <header class="bg-white shadow-sm border-b border-gray-200">
                <div class="px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900 flex items-center">
                                <i class="fas fa-boxes text-blue-600 mr-3"></i>
                                Gestion des Stocks
                            </h1>
                            <p class="text-gray-600 mt-1">Surveillez et gérez vos ingrédients en temps réel</p>
                        </div>
                        <div class="flex items-center space-x-3">
                            <div class="bg-blue-50 px-4 py-2 rounded-full">
                                <span class="text-sm font-medium text-blue-800">
                                    <i class="fas fa-clock mr-1"></i>
                                    Mis à jour maintenant
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </header>
            
            <!-- Main Content Area -->
            <main class="flex-1 overflow-auto p-6">
                <div class="max-w-7xl mx-auto">
                    <!-- Message de succès/erreur -->
                    <?php if (isset($message)): ?>
                        <div class="mb-6 p-4 rounded-lg <?= $message_type === 'success' ? 'bg-green-100 border border-green-400 text-green-700' : 'bg-red-100 border border-red-400 text-red-700' ?>">
                            <div class="flex items-center">
                                <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-2"></i>
                                <?= $message ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Action Bar -->
                    <div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                        <div class="flex items-center space-x-4">
                            <div class="bg-white rounded-lg px-4 py-2 shadow-sm border border-gray-200">
                                <span class="text-sm text-gray-600">Total d'ingrédients :</span>
                                <span class="ml-2 font-semibold text-gray-900"><?= $result->rowCount() ?></span>
                            </div>
                        </div>
                        <button onclick="openModal()" 
                           class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-green-500 to-green-600 text-white font-medium rounded-lg shadow-md hover:from-green-600 hover:to-green-700 transform hover:scale-105 transition-all duration-200">
                            <i class="fas fa-plus mr-2"></i>
                            Ajouter un ingrédient
                        </button>
                    </div>
                    
                    <!-- Stocks Table Card -->
                    <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-200">
                        <!-- Table Header -->
                        <div class="bg-gradient-to-r from-gray-50 to-gray-100 px-6 py-4 border-b border-gray-200">
                            <h2 class="text-lg font-semibold text-gray-800 flex items-center">
                                <i class="fas fa-list mr-2 text-gray-600"></i>
                                Liste des Ingrédients
                            </h2>
                        </div>
                        
                        <!-- Table -->
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="bg-gray-50 border-b border-gray-200">
                                    <tr>
                                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            <i class="fas fa-tag mr-2"></i>Ingrédient
                                        </th>
                                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            <i class="fas fa-sort-numeric-up mr-2"></i>Quantité
                                        </th>
                                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            <i class="fas fa-ruler mr-2"></i>Unité
                                        </th>
                                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            <i class="fas fa-exclamation-triangle mr-2"></i>Statut
                                        </th>
                                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            <i class="fas fa-cogs mr-2"></i>Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php 
                                    // Reset the result pointer to fetch data again
                                    $result = $conn->query("SELECT * FROM stocks");
                                    while($row = $result->fetch(PDO::FETCH_ASSOC)): 
                                    ?>
                                        <?php
                                            $alerte = $row['quantite'] <= $row['seuil_alerte'];
                                            $rowClass = $alerte ? 'bg-red-50 border-l-4 border-red-400' : 'hover:bg-gray-50';
                                        ?>
                                        <tr class="<?= $rowClass ?> transition-colors duration-200">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <div class="w-10 h-10 bg-gradient-to-br from-blue-100 to-blue-200 rounded-full flex items-center justify-center mr-3">
                                                        <i class="fas fa-leaf text-blue-600"></i>
                                                    </div>
                                                    <div>
                                                        <div class="text-sm font-medium text-gray-900">
                                                            <?= htmlspecialchars($row['nom']) ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-semibold text-gray-900">
                                                    <?= $row['quantite'] ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                    <?= $row['unite'] ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php if ($alerte): ?>
                                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800 animate-pulse">
                                                        <i class="fas fa-exclamation-triangle mr-1"></i>
                                                        Stock faible
                                                    </span>
                                                <?php else: ?>
                                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                        <i class="fas fa-check-circle mr-1"></i>
                                                        Stock OK
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <a href="modifier_stock.php?id=<?= $row['id'] ?>" 
                                                   class="inline-flex items-center px-3 py-2 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 transition-colors duration-200">
                                                    <i class="fas fa-edit mr-1"></i>
                                                    Modifier
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Footer Info -->
                    <div class="mt-6 bg-white rounded-lg p-4 shadow-sm border border-gray-200">
                        <div class="flex items-center justify-center text-sm text-gray-600">
                            <i class="fas fa-info-circle mr-2"></i>
                            Les ingrédients avec un stock faible sont surlignés en rouge pour une identification rapide
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal d'ajout d'ingrédient -->
    <div id="addModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-1/2 lg:w-1/3 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <!-- Header du modal -->
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                        <i class="fas fa-plus-circle text-green-600 mr-2"></i>
                        Ajouter un nouvel ingrédient
                    </h3>
                    <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>

                <!-- Formulaire -->
                <form method="POST" action="" class="space-y-4">
                    <input type="hidden" name="action" value="ajouter">
                    
                    <div>
                        <label for="nom" class="block text-sm font-medium text-gray-700 mb-1">
                            <i class="fas fa-tag mr-1"></i>Nom de l'ingrédient
                        </label>
                        <input type="text" id="nom" name="nom" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
                               placeholder="Ex: Farine, Sucre, Œufs...">
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="quantite" class="block text-sm font-medium text-gray-700 mb-1">
                                <i class="fas fa-sort-numeric-up mr-1"></i>Quantité
                            </label>
                            <input type="number" id="quantite" name="quantite" required min="0" step="0.01"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
                                   placeholder="Ex: 100">
                        </div>

                        <div>
                            <label for="unite" class="block text-sm font-medium text-gray-700 mb-1">
                                <i class="fas fa-ruler mr-1"></i>Unité
                            </label>
                            <select id="unite" name="unite" required 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                                <option value="">Choisir une unité</option>
                                <option value="kg">Kilogramme (kg)</option>
                                <option value="g">Gramme (g)</option>
                                <option value="l">Litre (l)</option>
                                <option value="ml">Millilitre (ml)</option>
                                <option value="pièce">Pièce</option>
                                <option value="paquet">Paquet</option>
                                <option value="boîte">Boîte</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label for="seuil_alerte" class="block text-sm font-medium text-gray-700 mb-1">
                            <i class="fas fa-exclamation-triangle mr-1"></i>Seuil d'alerte
                        </label>
                        <input type="number" id="seuil_alerte" name="seuil_alerte" required min="0" step="0.01"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
                               placeholder="Ex: 10">
                        <p class="text-xs text-gray-500 mt-1">Quantité minimale avant alerte de stock faible</p>
                    </div>

                    <!-- Boutons -->
                    <div class="flex items-center justify-end space-x-3 pt-4 border-t border-gray-200">
                        <button type="button" onclick="closeModal()" 
                                class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition-colors duration-200">
                            <i class="fas fa-times mr-1"></i>Annuler
                        </button>
                        <button type="submit" 
                                class="px-6 py-2 bg-gradient-to-r from-green-500 to-green-600 text-white rounded-md hover:from-green-600 hover:to-green-700 transition-all duration-200">
                            <i class="fas fa-plus mr-1"></i>Ajouter
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openModal() {
            document.getElementById('addModal').classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        }

        function closeModal() {
            document.getElementById('addModal').classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }

        // Fermer le modal en cliquant à l'extérieur
        document.getElementById('addModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        // Fermer le modal avec la touche Échap
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });

        // Auto-hide success/error messages after 5 seconds
        <?php if (isset($message)): ?>
        setTimeout(function() {
            const messageDiv = document.querySelector('.bg-green-100, .bg-red-100');
            if (messageDiv) {
                messageDiv.style.transition = 'opacity 0.5s';
                messageDiv.style.opacity = '0';
                setTimeout(() => messageDiv.remove(), 500);
            }
        }, 5000);
        <?php endif; ?>
    </script>
</body>
</html>