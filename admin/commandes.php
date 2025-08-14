<?php
require_once '../config.php';
session_start();

// Vérifie l'accès admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Fonction pour échapper les valeurs
function e($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// Gestion de la suppression AJAX
if (isset($_POST['action']) && $_POST['action'] === 'supprimer' && isset($_POST['id'])) {
    header('Content-Type: application/json');
    
    try {
        $stmt = $conn->prepare("DELETE FROM commandes WHERE id = :id");
        $stmt->execute(['id' => $_POST['id']]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Commande supprimée avec succès']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Commande non trouvée']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression: ' . $e->getMessage()]);
    }
    exit;
}

// Recherche & filtre par statut
$search = $_GET['search'] ?? '';
$filtre_statut = $_GET['statut'] ?? '';

try {
    $sql = "SELECT * FROM commandes WHERE 1";
    $params = [];

    if (!empty($search)) {
        $sql .= " AND (nom_client LIKE :search OR email LIKE :search OR telephone LIKE :search)";
        $params['search'] = "%$search%";
    }

    if (!empty($filtre_statut)) {
        $sql .= " AND statut = :statut";
        $params['statut'] = $filtre_statut;
    }

    $sql .= " ORDER BY id DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $commandes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erreur : " . $e->getMessage());
}

// Statistiques
$total_cmd = count($commandes);
$total_ventes = array_sum(array_column($commandes, 'total'));
$moyenne_cmd = $total_cmd > 0 ? round($total_ventes / $total_cmd, 2) : 0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Commandes</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: ui-sans-serif, system-ui, sans-serif;
        }
        .stat-card {
            transition: all 0.2s ease-in-out;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        .table-row {
            transition: all 0.2s ease;
        }
        .table-row:hover {
            background-color: #f9fafb;
        }
        .action-btn {
            border-radius: 8px;
            font-weight: 500;
            font-size: 14px;
            padding: 8px 16px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
            border: 1px solid transparent;
        }
        .btn-voir {
            background-color: #dcfce7;
            color: #166534;
            border-color: #bbf7d0;
        }
        .btn-voir:hover {
            background-color: #bbf7d0;
            border-color: #86efac;
        }
        .btn-modifier {
            background-color: #dbeafe;
            color: #1e40af;
            border-color: #bfdbfe;
        }
        .btn-modifier:hover {
            background-color: #bfdbfe;
            border-color: #93c5fd;
        }
        .btn-supprimer {
            background-color: #fee2e2;
            color: #dc2626;
            border-color: #fecaca;
        }
        .btn-supprimer:hover {
            background-color: #fecaca;
            border-color: #fca5a5;
        }
        .status-badge {
            border-radius: 12px;
            font-weight: 500;
            font-size: 12px;
            padding: 4px 12px;
        }
        .modal-overlay {
            backdrop-filter: blur(4px);
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="flex h-screen overflow-hidden">
        <?php include 'sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Header -->
            <header class="bg-white shadow-sm border-b border-gray-200 px-6 py-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-800">Gestion des Commandes</h1>
                        <p class="text-lg text-gray-600 mt-1">Interface d'administration avancée</p>
                    </div>
                    <div class="flex items-center space-x-4">
                        <div class="px-4 py-2 bg-gradient-to-r from-emerald-500 to-teal-600 text-white rounded-lg shadow-sm">
                            <span class="font-medium">Premium Dashboard</span>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content Area -->
            <main class="flex-1 overflow-auto p-6">
                <!-- Section Header -->
                <div class="mb-6">
                    <h2 class="text-xs uppercase tracking-widest text-gray-500 font-semibold mb-4">TABLEAU DE BORD</h2>
                </div>

                <!-- Statistiques Cards (Priorité visuelle 1) -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <!-- Total Commandes -->
                    <div class="stat-card bg-white border border-gray-200 rounded-lg shadow-md p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-semibold text-gray-500 uppercase tracking-wide">Total Commandes</p>
                                <p class="text-2xl font-bold text-gray-800 mt-2"><?= $total_cmd ?></p>
                                <p class="text-xs text-gray-500 mt-1">Commandes traitées</p>
                            </div>
                            <div class="p-3 bg-emerald-100 rounded-lg">
                                <i class="fas fa-receipt text-emerald-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Total Ventes -->
                    <div class="stat-card bg-white border border-gray-200 rounded-lg shadow-md p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-semibold text-gray-500 uppercase tracking-wide">Total Ventes</p>
                                <p class="text-2xl font-bold text-gray-800 mt-2"><?= number_format($total_ventes, 0) ?></p>
                                <p class="text-xs text-gray-500 mt-1">FCFA générés</p>
                            </div>
                            <div class="p-3 bg-blue-100 rounded-lg">
                                <i class="fas fa-chart-line text-blue-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Moyenne par Commande -->
                    <div class="stat-card bg-white border border-gray-200 rounded-lg shadow-md p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-semibold text-gray-500 uppercase tracking-wide">Moyenne/Commande</p>
                                <p class="text-2xl font-bold text-gray-800 mt-2"><?= number_format($moyenne_cmd, 0) ?></p>
                                <p class="text-xs text-gray-500 mt-1">FCFA par commande</p>
                            </div>
                            <div class="p-3 bg-purple-100 rounded-lg">
                                <i class="fas fa-calculator text-purple-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="border-t border-gray-200 my-8"></div>

                <!-- Section Header -->
                <div class="mb-6">
                    <h2 class="text-xs uppercase tracking-widest text-gray-500 font-semibold mb-4">ACTIONS & FILTRES</h2>
                </div>

                <!-- Filtres et Recherche (Priorité visuelle 2) -->
                <div class="bg-white border border-gray-200 rounded-lg shadow-md p-6 mb-8">
                    <form method="get" class="grid grid-cols-1 md:grid-cols-12 gap-6 items-end">
                        <div class="md:col-span-6">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Recherche</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-search text-gray-400"></i>
                                </div>
                                <input type="text" 
                                       name="search" 
                                       placeholder="Recherche par nom, email ou téléphone..." 
                                       value="<?= e($search) ?>"
                                       class="block w-full pl-10 pr-3 py-3 bg-gray-50 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition-colors text-base">
                            </div>
                        </div>
                        
                        <div class="md:col-span-4">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Statut</label>
                            <select name="statut" class="block w-full px-3 py-3 bg-gray-50 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition-colors text-base">
                                <option value="">Tous les statuts</option>
                                <option value="En cours" <?= $filtre_statut == 'En cours' ? 'selected' : '' ?>>En cours</option>
                                <option value="Livré" <?= $filtre_statut == 'Livré' ? 'selected' : '' ?>>Livré</option>
                                <option value="Annulé" <?= $filtre_statut == 'Annulé' ? 'selected' : '' ?>>Annulé</option>
                            </select>
                        </div>
                        
                        <div class="md:col-span-2">
                            <button type="submit" class="w-full px-4 py-3 bg-gradient-to-r from-emerald-500 to-teal-600 text-white rounded-lg hover:from-emerald-600 hover:to-teal-700 focus:ring-2 focus:ring-emerald-500 transition-colors font-medium">
                                Filtrer
                            </button>
                        </div>
                    </form>
                </div>

                <div class="border-t border-gray-200 my-8"></div>

                <!-- Section Header -->
                <div class="mb-6">
                    <h2 class="text-xs uppercase tracking-widest text-gray-500 font-semibold mb-4">LISTE DES COMMANDES</h2>
                </div>

                <!-- Tableau des Commandes (Priorité visuelle 3) -->
                <div class="bg-white border border-gray-200 rounded-lg shadow-md overflow-hidden">
                    <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-bold text-gray-800">Commandes</h3>
                            <div class="px-3 py-1 bg-emerald-100 text-emerald-800 rounded-full">
                                <span class="text-sm font-medium"><?= count($commandes) ?> résultats</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Client</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Contact</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Total</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Statut</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Vu</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200" id="commandesTableBody">
                                <?php if (empty($commandes)): ?>
                                    <tr>
                                        <td colspan="8" class="px-6 py-20 text-center">
                                            <div class="flex flex-col items-center">
                                                <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                                    <i class="fas fa-search-minus text-gray-400 text-2xl"></i>
                                                </div>
                                                <h3 class="text-lg font-bold text-gray-700 mb-2">Aucune commande trouvée</h3>
                                                <p class="text-gray-500">Essayez de modifier vos critères de recherche</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($commandes as $cmd): ?>
                                        <tr class="table-row" id="commande-<?= $cmd['id'] ?>">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="text-base font-bold text-gray-800">#<?= e($cmd['id']) ?></span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <div class="w-8 h-8 bg-emerald-500 rounded-full flex items-center justify-center mr-3">
                                                        <span class="text-white font-medium text-sm"><?= strtoupper(substr(e($cmd['nom_client']), 0, 1)) ?></span>
                                                    </div>
                                                    <span class="text-base font-medium text-gray-800"><?= e($cmd['nom_client']) ?></span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="space-y-1">
                                                    <div class="text-sm text-gray-800"><?= e($cmd['email']) ?></div>
                                                    <div class="text-sm text-gray-500"><?= e($cmd['telephone']) ?></div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="text-base font-bold text-gray-800"><?= number_format($cmd['total'], 0) ?> FCFA</span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php
                                                $statutClass = '';
                                                switch($cmd['statut']) {
                                                    case 'En cours':
                                                        $statutClass = 'bg-yellow-100 text-yellow-800';
                                                        break;
                                                    case 'Livré':
                                                        $statutClass = 'bg-green-100 text-green-800';
                                                        break;
                                                    case 'Annulé':
                                                        $statutClass = 'bg-red-100 text-red-800';
                                                        break;
                                                    default:
                                                        $statutClass = 'bg-gray-100 text-gray-800';
                                                }
                                                ?>
                                                <span class="status-badge <?= $statutClass ?>"><?= e($cmd['statut']) ?></span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php if ($cmd['vu_admin']): ?>
                                                    <span class="status-badge bg-green-100 text-green-800">Consulté</span>
                                                <?php else: ?>
                                                    <span class="status-badge bg-red-100 text-red-800">Nouveau</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="text-sm text-gray-500"><?= e($cmd['date_commande'] ?? $cmd['created_at'] ?? 'Non défini') ?></span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex space-x-2">
                                                    <a href="recu.php?id=<?= $cmd['id'] ?>" 
                                                       target="_blank"
                                                       class="action-btn btn-voir">
                                                        <i class="fas fa-eye"></i>
                                                        Voir
                                                    </a>
                                                    <a href="modifier_commande.php?id=<?= $cmd['id'] ?>" 
                                                       target="_blank"
                                                       class="action-btn btn-modifier">
                                                        <i class="fas fa-edit"></i>
                                                        Modifier
                                                    </a>
                                                    <button onclick="confirmDelete(<?= $cmd['id'] ?>)"
                                                       class="action-btn btn-supprimer">
                                                        <i class="fas fa-trash"></i>
                                                        Supprimer
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal de confirmation de suppression -->
    <div id="deleteModal" class="fixed inset-0 bg-black/50 modal-overlay flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg p-6 m-4 max-w-md w-full border border-gray-200 shadow-xl">
            <div class="text-center">
                <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-exclamation-triangle text-red-600 text-2xl"></i>
                </div>
                <h3 class="text-lg font-bold text-gray-800 mb-2">Confirmer la suppression</h3>
                <p class="text-gray-600 mb-2">Vous êtes sur le point de supprimer définitivement la commande :</p>
                <div class="bg-gray-50 rounded-lg p-3 mb-4 border border-gray-200">
                    <p class="font-medium text-gray-800" id="deleteCommandeInfo"></p>
                </div>
                <p class="text-red-600 text-sm font-medium mb-6">Cette action est irréversible !</p>
                <div class="flex space-x-3">
                    <button onclick="closeDeleteModal()" 
                            class="flex-1 px-4 py-2 bg-gray-200 text-gray-800 rounded-lg font-medium hover:bg-gray-300 transition-colors">
                        Annuler
                    </button>
                    <button onclick="deleteCommande()" 
                            id="confirmDeleteBtn"
                            class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg font-medium hover:bg-red-700 transition-colors">
                        Supprimer
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Notification Container -->
    <div id="toastContainer" class="fixed top-4 right-4 z-50 space-y-4"></div>

    <script>
        let commandeToDelete = null;

        // Fonction pour afficher la modale de confirmation
        function confirmDelete(id) {
            commandeToDelete = id;
            const modal = document.getElementById('deleteModal');
            const commandeRow = document.getElementById('commande-' + id);
            const nomClient = commandeRow.querySelector('.text-base.font-medium.text-gray-800').textContent;
            
            document.getElementById('deleteCommandeInfo').textContent = `Commande #${id} - ${nomClient}`;
            modal.classList.remove('hidden');
        }

        // Fonction pour fermer la modale
        function closeDeleteModal() {
            const modal = document.getElementById('deleteModal');
            modal.classList.add('hidden');
            commandeToDelete = null;
        }

        // Fonction pour supprimer la commande via AJAX
        function deleteCommande() {
            if (!commandeToDelete) return;
            
            const confirmBtn = document.getElementById('confirmDeleteBtn');
            const originalText = confirmBtn.innerHTML;
            
            // Animation de chargement
            confirmBtn.innerHTML = `
                <i class="fas fa-spinner fa-spin mr-2"></i>
                Suppression...
            `;
            confirmBtn.disabled = true;
            
            // Requête AJAX
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=supprimer&id=${commandeToDelete}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Animation de suppression de la ligne
                    const row = document.getElementById('commande-' + commandeToDelete);
                    row.style.opacity = '0';
                    row.style.transform = 'translateX(-100%)';
                    
                    setTimeout(() => {
                        row.remove();
                        updateStats();
                    }, 300);
                    
                    showToast('Commande supprimée avec succès!', 'success');
                    closeDeleteModal();
                } else {
                    showToast('Erreur: ' + data.message, 'error');
                    confirmBtn.innerHTML = originalText;
                    confirmBtn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                showToast('Erreur de connexion', 'error');
                confirmBtn.innerHTML = originalText;
                confirmBtn.disabled = false;
            });
        }

        // Fonction pour afficher les notifications toast
        function showToast(message, type = 'success') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            const bgColor = type === 'success' ? 'bg-green-500' : 'bg-red-500';
            const icon = type === 'success' ? 'fas fa-check-circle' : 'fas fa-exclamation-circle';
            
            toast.className = `${bgColor} text-white px-6 py-4 rounded-lg shadow-lg transform translate-x-full transition-all duration-300 font-medium max-w-sm`;
            toast.innerHTML = `
                <div class="flex items-center">
                    <i class="${icon} mr-3"></i>
                    <span>${message}</span>
                    <button onclick="this.closest('div').remove()" class="ml-4 text-white/80 hover:text-white">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            
            container.appendChild(toast);
            
            setTimeout(() => {
                toast.style.transform = 'translateX(0)';
            }, 100);
            
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.style.transform = 'translateX(100%)';
                    setTimeout(() => toast.remove(), 300);
                }
            }, 4000);
        }

        // Fonction pour mettre à jour les statistiques
        function updateStats() {
            const remainingRows = document.querySelectorAll('#commandesTableBody tr:not([colspan])');
            const totalCommandes = remainingRows.length;
            
            // Mettre à jour le compteur total
            const totalElements = document.querySelectorAll('.text-2xl.font-bold.text-gray-800');
            if (totalElements[0]) {
                totalElements[0].textContent = totalCommandes;
            }
            
            // Recalculer et mettre à jour le total des ventes
            let totalVentes = 0;
            remainingRows.forEach(row => {
                const totalCell = row.querySelector('.text-base.font-bold.text-gray-800');
                if (totalCell && totalCell.textContent.includes('FCFA')) {
                    const amount = parseInt(totalCell.textContent.replace(/[^\d]/g, ''));
                    totalVentes += amount;
                }
            });
            
            if (totalElements[1]) {
                totalElements[1].textContent = totalVentes.toLocaleString();
            }
            
            // Recalculer la moyenne
            const moyenne = totalCommandes > 0 ? Math.round(totalVentes / totalCommandes) : 0;
            if (totalElements[2]) {
                totalElements[2].textContent = moyenne.toLocaleString();
            }
            
            // Mettre à jour le compteur de résultats
            const resultatsElement = document.querySelector('.text-sm.font-medium');
            if (resultatsElement && resultatsElement.textContent.includes('résultats')) {
                resultatsElement.textContent = `${totalCommandes} résultats`;
            }
            
            // Vérifier s'il n'y a plus de commandes
            if (totalCommandes === 0) {
                const tbody = document.getElementById('commandesTableBody');
                tbody.innerHTML = `
                    <tr>
                        <td colspan="8" class="px-6 py-20 text-center">
                            <div class="flex flex-col items-center">
                                <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                    <i class="fas fa-search-minus text-gray-400 text-2xl"></i>
                                </div>
                                <h3 class="text-lg font-bold text-gray-700 mb-2">Aucune commande trouvée</h3>
                                <p class="text-gray-500">Toutes les commandes ont été supprimées</p>
                            </div>
                        </td>
                    </tr>
                `;
            }
        }

        // Fermer la modale en cliquant à l'extérieur
        document.getElementById('deleteModal').addEventListener('click', (e) => {
            if (e.target.id === 'deleteModal') {
                closeDeleteModal();
            }
        });

        // Fermer la modale avec Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !document.getElementById('deleteModal').classList.contains('hidden')) {
                closeDeleteModal();
            }
        });
    </script>
</body>
</html>