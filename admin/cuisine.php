<?php
session_start();
require_once '../config.php';
require_once 'includes/permissions.php';

// Vérifie l'accès admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Vérifier que admin_id existe
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// Vérifier les permissions pour la cuisine
requireAccess($conn, $_SESSION['admin_id'], 'commandes');

// Récupérer les infos de l'admin
$stmt_admin = $conn->prepare("SELECT username, email FROM admin WHERE id = ?");
$stmt_admin->execute([$_SESSION['admin_id']]);
$admin_info = $stmt_admin->fetch(PDO::FETCH_ASSOC);
$admin_name = $admin_info['username'] ?? $_SESSION['admin_username'] ?? 'Admin';

// Fonction helper pour échapper les valeurs
function e($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// ===== GESTION AJAX POUR RÉCUPÉRER LES COMMANDES EN ATTENTE =====
if (isset($_POST['action']) && $_POST['action'] === 'get_commandes_cuisine') {
    header('Content-Type: application/json');

    try {
        // Récupérer les commandes "En cours" et "En préparation"
        $stmt = $conn->prepare("
            SELECT c.*,
                   CASE
                       WHEN c.type_commande = 'manuelle' THEN 'Manuelle'
                       ELSE 'Client'
                   END as origine,
                   TIMESTAMPDIFF(MINUTE, c.created_at, NOW()) as temps_ecoule
            FROM commandes c
            WHERE c.statut IN ('En cours', 'En préparation')
            ORDER BY c.created_at ASC
        ");
        $stmt->execute();
        $commandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Pour chaque commande, récupérer les détails
        foreach ($commandes as &$commande) {
            $stmt_details = $conn->prepare("
                SELECT nom_plat, quantite, prix
                FROM commande_details
                WHERE commande_id = ?
            ");
            $stmt_details->execute([$commande['id']]);
            $commande['details'] = $stmt_details->fetchAll(PDO::FETCH_ASSOC);
        }

        echo json_encode([
            'success' => true,
            'commandes' => $commandes
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Erreur: ' . $e->getMessage()
        ]);
    }
    exit;
}

// ===== GESTION AJAX POUR DÉMARRER LA PRÉPARATION =====
if (isset($_POST['action']) && $_POST['action'] === 'demarrer_preparation' && isset($_POST['id'])) {
    header('Content-Type: application/json');

    try {
        $id = $_POST['id'];

        $stmt = $conn->prepare("UPDATE commandes SET statut = 'En préparation' WHERE id = ?");
        $result = $stmt->execute([$id]);

        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Préparation démarrée'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Erreur lors du démarrage'
            ]);
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Erreur: ' . $e->getMessage()
        ]);
    }
    exit;
}

// ===== GESTION AJAX POUR MARQUER COMME PRÊT =====
if (isset($_POST['action']) && $_POST['action'] === 'marquer_pret' && isset($_POST['id'])) {
    header('Content-Type: application/json');

    try {
        $id = $_POST['id'];

        // Marquer la commande comme "Prête"
        $stmt = $conn->prepare("UPDATE commandes SET statut = 'Prêt', vu_admin = 0 WHERE id = ?");
        $result = $stmt->execute([$id]);

        if ($result) {
            // Créer une notification pour l'admin/serveur
            $stmt_notif = $conn->prepare("
                INSERT INTO notifications (message, type, date, vue)
                VALUES (?, 'success', NOW(), 0)
            ");
            $stmt_notif->execute(["🍽️ Commande #$id est prête pour le service"]);

            echo json_encode([
                'success' => true,
                'message' => 'Commande marquée comme prête'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour'
            ]);
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Erreur: ' . $e->getMessage()
        ]);
    }
    exit;
}

// ===== GESTION AJAX POUR ANNULER UNE COMMANDE =====
if (isset($_POST['action']) && $_POST['action'] === 'annuler_commande' && isset($_POST['id'])) {
    header('Content-Type: application/json');

    try {
        $id = $_POST['id'];
        $raison = $_POST['raison'] ?? 'Annulée par la cuisine';

        $stmt = $conn->prepare("UPDATE commandes SET statut = 'Annulée' WHERE id = ?");
        $result = $stmt->execute([$id]);

        if ($result) {
            // Notification
            $stmt_notif = $conn->prepare("
                INSERT INTO notifications (message, type, date, vue)
                VALUES (?, 'warning', NOW(), 0)
            ");
            $stmt_notif->execute(["⚠️ Commande #$id annulée: $raison"]);

            echo json_encode([
                'success' => true,
                'message' => 'Commande annulée'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Erreur lors de l\'annulation'
            ]);
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Erreur: ' . $e->getMessage()
        ]);
    }
    exit;
}

// Récupérer les statistiques
try {
    $stat_attente = $conn->query("SELECT COUNT(*) FROM commandes WHERE statut = 'En cours'")->fetchColumn();
    $stat_preparation = $conn->query("SELECT COUNT(*) FROM commandes WHERE statut = 'En préparation'")->fetchColumn();
} catch (Exception $e) {
    $stat_attente = 0;
    $stat_preparation = 0;
}
?>

<!-- Container Flex Parent -->
<div class="flex min-h-screen">
    <?php require_once '.x/includes/sidebar.php'; ?>
    <!-- Main Content -->
    <div class="flex-1 flex flex-col min-h-screen overflow-hidden lg:ml-64">

    
    <!-- Header avec notifications -->
    <header class="bg-gradient-to-r from-surface via-surface-light to-surface border-b border-gray-700/40 sticky top-0 z-40 backdrop-blur-sm">
        <div class="px-6 py-4">
            <div class="flex items-center justify-between">
                <!-- Titre -->
                <div class="flex items-center space-x-4">
                    <div class="bg-gradient-to-br from-primary to-primary-dark p-3 rounded-xl shadow-lg">
                        <i class="fas fa-utensils text-white text-2xl"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-white">Gestion Cuisine</h1>
                        <p class="text-sm text-gray-400">Suivi des commandes en temps réel</p>
                    </div>
                </div>

                <!-- Statistiques en temps réel -->
                <div class="flex items-center space-x-4">
                    <!-- Badge En attente -->
                    <div class="bg-gradient-to-br from-yellow-500/20 to-yellow-600/20 border border-yellow-500/50 rounded-xl px-4 py-3 backdrop-blur-sm">
                        <div class="flex items-center space-x-3">
                            <div class="bg-yellow-500/20 p-2 rounded-lg">
                                <i class="fas fa-clock text-yellow-400 text-xl"></i>
                            </div>
                            <div>
                                <p class="text-xs text-yellow-300 font-medium">En attente</p>
                                <p class="text-2xl font-bold text-yellow-400" id="stat-attente"><?php echo $stat_attente; ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Badge En préparation -->
                    <div class="bg-gradient-to-br from-blue-500/20 to-blue-600/20 border border-blue-500/50 rounded-xl px-4 py-3 backdrop-blur-sm animate-pulse-soft">
                        <div class="flex items-center space-x-3">
                            <div class="bg-blue-500/20 p-2 rounded-lg">
                                <i class="fas fa-fire text-blue-400 text-xl"></i>
                            </div>
                            <div>
                                <p class="text-xs text-blue-300 font-medium">En préparation</p>
                                <p class="text-2xl font-bold text-blue-400" id="stat-preparation"><?php echo $stat_preparation; ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Bouton refresh -->
                    <button onclick="chargerCommandes()" class="bg-primary/20 hover:bg-primary/30 border border-primary/50 text-primary px-4 py-3 rounded-xl transition-all duration-300 flex items-center space-x-2 group">
                        <i class="fas fa-sync-alt group-hover:rotate-180 transition-transform duration-500"></i>
                        <span class="text-sm font-medium">Actualiser</span>
                    </button>
                </div>
            </div>
        </div>
    </header>

    <!-- Container principal -->
    <main class="flex-1 p-6 bg-gradient-to-br from-gray-900 via-gray-800 to-gray-900 overflow-y-auto">

        <!-- Indicateur de rafraîchissement -->
        <div id="refreshIndicator" class="fixed top-20 right-6 bg-gray-800/90 backdrop-blur-sm border border-primary/30 rounded-xl px-4 py-2 shadow-xl hidden z-50">
            <div class="flex items-center space-x-2">
                <i class="fas fa-sync-alt text-primary animate-spin"></i>
                <span class="text-sm text-gray-300">Mise à jour...</span>
            </div>
        </div>

        <!-- Notification toast -->
        <div id="notificationToast" class="fixed top-20 left-1/2 transform -translate-x-1/2 bg-gradient-to-r from-primary to-primary-dark text-white px-6 py-3 rounded-xl shadow-2xl hidden z-50 animate-fade-in">
            <div class="flex items-center space-x-3">
                <i class="fas fa-bell text-xl"></i>
                <span id="notificationText" class="font-medium"></span>
            </div>
        </div>

        <!-- Filtres et tri -->
        <div class="mb-6 flex flex-wrap gap-4 items-center justify-between bg-gray-800/50 backdrop-blur-sm rounded-2xl p-4 border border-gray-700/50">
            <div class="flex items-center space-x-3">
                <button onclick="filtrerCommandes('tous')" id="filter-tous" class="filter-btn active px-4 py-2 rounded-lg transition-all duration-300 font-medium text-sm">
                    <i class="fas fa-th-large mr-2"></i>Toutes
                </button>
                <button onclick="filtrerCommandes('en-cours')" id="filter-en-cours" class="filter-btn px-4 py-2 rounded-lg transition-all duration-300 font-medium text-sm">
                    <i class="fas fa-clock mr-2"></i>En attente
                </button>
                <button onclick="filtrerCommandes('en-preparation')" id="filter-en-preparation" class="filter-btn px-4 py-2 rounded-lg transition-all duration-300 font-medium text-sm">
                    <i class="fas fa-fire mr-2"></i>En préparation
                </button>
            </div>
            <div class="flex items-center space-x-3">
                <label class="text-gray-400 text-sm font-medium">Tri:</label>
                <select onchange="trierCommandes(this.value)" class="bg-gray-700/50 border border-gray-600 text-white px-4 py-2 rounded-lg text-sm focus:outline-none focus:border-primary transition-all">
                    <option value="temps">Plus ancien</option>
                    <option value="temps-inverse">Plus récent</option>
                    <option value="montant">Montant croissant</option>
                    <option value="montant-inverse">Montant décroissant</option>
                </select>
            </div>
        </div>

        <!-- Container des commandes -->
        <div id="commandesContainer" class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 gap-6 auto-rows-fr">
            <!-- État vide initial -->
            <div class="col-span-full">
                <div class="bg-gradient-to-br from-gray-800 to-gray-900 border border-gray-700/50 rounded-2xl p-12 text-center shadow-xl">
                    <div class="inline-block bg-gray-700/30 p-6 rounded-full mb-6 animate-pulse">
                        <i class="fas fa-clock text-gray-500 text-5xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-300 mb-2">Chargement des commandes...</h3>
                    <p class="text-gray-500">Veuillez patienter</p>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <?php require_once 'footer.php'; ?>
    </div>
</div>

<style>
    /* Animations personnalisées */
    @keyframes pulse-border {
        0%, 100% {
            border-color: rgba(59, 130, 246, 0.3);
            box-shadow: 0 0 0 0 rgba(59, 130, 246, 0.7);
        }
        50% {
            border-color: rgba(59, 130, 246, 0.8);
            box-shadow: 0 0 0 10px rgba(59, 130, 246, 0);
        }
    }

    @keyframes pulse-glow {
        0%, 100% {
            box-shadow: 0 0 20px rgba(239, 68, 68, 0.3);
        }
        50% {
            box-shadow: 0 0 30px rgba(239, 68, 68, 0.6);
        }
    }

    .commande-card.en-preparation {
        animation: pulse-border 2s infinite;
    }

    .commande-card.urgent {
        animation: pulse-glow 2s infinite;
    }

    .commande-card {
        transition: all 0.3s ease;
        height: 100%;
        display: flex;
        flex-direction: column;
    }

    .commande-card:hover {
        transform: translateY(-4px) scale(1.02);
    }

    @keyframes slideInDown {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @keyframes fadeOut {
        from {
            opacity: 1;
            transform: scale(1);
        }
        to {
            opacity: 0;
            transform: scale(0.95);
        }
    }

    .card-enter {
        animation: slideInDown 0.4s ease;
    }

    .card-exit {
        animation: fadeOut 0.3s ease;
    }

    /* Styles pour les filtres */
    .filter-btn {
        background: rgba(75, 85, 99, 0.3);
        color: #9ca3af;
        border: 1px solid rgba(75, 85, 99, 0.3);
    }

    .filter-btn:hover {
        background: rgba(75, 85, 99, 0.5);
        color: #fff;
        border-color: rgba(75, 85, 99, 0.5);
    }

    .filter-btn.active {
        background: linear-gradient(135deg, #4f46e5, #6366f1);
        color: white;
        border-color: #6366f1;
        box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
    }

    /* Animation pour les statistiques */
    @keyframes pulse-soft {
        0%, 100% {
            opacity: 1;
        }
        50% {
            opacity: 0.85;
        }
    }

    .animate-pulse-soft {
        animation: pulse-soft 2s infinite;
    }

    /* Responsive Grid */
    @media (max-width: 1024px) {
        #commandesContainer {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 768px) {
        #commandesContainer {
            grid-template-columns: 1fr;
        }
    }

    /* Scrollbar personnalisée */
    ::-webkit-scrollbar {
        width: 8px;
    }

    ::-webkit-scrollbar-track {
        background: rgba(31, 41, 55, 0.5);
    }

    ::-webkit-scrollbar-thumb {
        background: rgba(79, 70, 229, 0.5);
        border-radius: 4px;
    }

    ::-webkit-scrollbar-thumb:hover {
        background: rgba(79, 70, 229, 0.7);
    }

    /* Scrollbar pour les articles */
    .custom-scrollbar::-webkit-scrollbar {
        width: 4px;
    }

    .custom-scrollbar::-webkit-scrollbar-track {
        background: rgba(31, 41, 55, 0.3);
        border-radius: 4px;
    }

    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: rgba(79, 70, 229, 0.4);
        border-radius: 4px;
    }

    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
        background: rgba(79, 70, 229, 0.6);
    }

    /* Animation pour le chargement */
    @keyframes shimmer {
        0% {
            background-position: -1000px 0;
        }
        100% {
            background-position: 1000px 0;
        }
    }

    .skeleton {
        animation: shimmer 2s infinite;
        background: linear-gradient(90deg, rgba(55, 65, 81, 0.3) 25%, rgba(75, 85, 99, 0.3) 50%, rgba(55, 65, 81, 0.3) 75%);
        background-size: 1000px 100%;
    }
</style>

<script>
// Configuration
const AUTO_REFRESH_INTERVAL = 5000; // 5 secondes
let refreshTimer;
let nouvellesCommandesAudio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBSuFzvLZiTYIG2m98OScTgwOUKjo77RgGwU7k9n0zHkpBSh+zPDekD8IElyx6OyrVBUIRp3e8r1rHwUrhc/y2ogzBx1qwPDlm0wLDlOq6e+yXhoEOpPY88x3KAUpfs/v3o8+BxJbr+frrVMUB0ae3/O9aB0FLoXP8tuIMQcdbMPz5ppKCg5TqunwsVsaBDyU2fPNdSYEK4HQ8d+OOwYSXLLo7K1SFAdGoN/zv2YbBCyEz/PciC4HH23E9OaYSAkNVKzq8bBZGQQ8ldv0znMjBCuB0fLgjDoFEl2z6e2uUBIHSKHh9L9kGQMrhNDz3YgrBiBuxfTnlkYIDVWs6/KvVxgEPJXc9c9xIQMrgtPz4Ys4BRJftOrur04QBkii4/TAYhYDK4XR9N6HJwYgb8X16JRDBgxWre70rlUWAz2W3vbRbx0CK4PU9eGJNAQSYLXr8LFNDQZJo+T1w2AUAy2G0/feh/IAIHDHzuyYSQsLV67w97RRFgM+l+H4025hBSuF1fXii/AFFE+56/OyTAkFSabm9sVhMQMuhNL54Yf5ACFxx+HvmkYLCliy8vq1TxMCPpjk+tNsHQEric3z5I/vBBJPuu/1tEoFBUqn6PjIYiwCLoTS+eCI8wAjcsrj9ZZCKQ0=');

// Dernières commandes connues (pour détecter les nouvelles)
let commandesIds = [];
let commandesData = [];
let filtreActif = 'tous';
let triActif = 'temps';

// Fonction pour afficher une notification
function showNotification(message, type = 'success') {
    const toast = document.getElementById('notificationToast');
    const text = document.getElementById('notificationText');

    text.textContent = message;

    // Changer la couleur selon le type
    if (type === 'warning') {
        toast.className = 'fixed top-20 left-1/2 transform -translate-x-1/2 bg-gradient-to-r from-yellow-500 to-yellow-600 text-white px-6 py-3 rounded-xl shadow-2xl z-50 animate-fade-in';
    } else if (type === 'error') {
        toast.className = 'fixed top-20 left-1/2 transform -translate-x-1/2 bg-gradient-to-r from-red-500 to-red-600 text-white px-6 py-3 rounded-xl shadow-2xl z-50 animate-fade-in';
    } else {
        toast.className = 'fixed top-20 left-1/2 transform -translate-x-1/2 bg-gradient-to-r from-primary to-primary-dark text-white px-6 py-3 rounded-xl shadow-2xl z-50 animate-fade-in';
    }

    toast.classList.remove('hidden');

    setTimeout(() => {
        toast.classList.add('hidden');
    }, 3000);
}

// Fonction pour charger les commandes
async function chargerCommandes(silent = false) {
    if (!silent) {
        document.getElementById('refreshIndicator').classList.remove('hidden');
    }

    try {
        const formData = new FormData();
        formData.append('action', 'get_commandes_cuisine');

        const response = await fetch('cuisine.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            // Vérifier s'il y a de nouvelles commandes
            const nouveauxIds = data.commandes.map(c => c.id);
            const nouvellesCommandes = nouveauxIds.filter(id => !commandesIds.includes(id));

            if (nouvellesCommandes.length > 0 && commandesIds.length > 0) {
                showNotification(`🔔 ${nouvellesCommandes.length} nouvelle(s) commande(s) !`, 'warning');
                // Jouer un son
                try {
                    nouvellesCommandesAudio.play();
                } catch (e) {
                    console.log('Son désactivé:', e);
                }
            }

            commandesIds = nouveauxIds;
            commandesData = data.commandes;
            afficherCommandesFiltrees();

            // Mettre à jour les stats
            const enAttente = data.commandes.filter(c => c.statut === 'En cours').length;
            const enPreparation = data.commandes.filter(c => c.statut === 'En préparation').length;

            document.getElementById('stat-attente').textContent = enAttente;
            document.getElementById('stat-preparation').textContent = enPreparation;
        } else {
            console.error('Erreur:', data.message);
        }
    } catch (error) {
        console.error('Erreur de chargement:', error);
    } finally {
        document.getElementById('refreshIndicator').classList.add('hidden');
    }
}

// Fonction pour afficher les commandes
function afficherCommandes(commandes) {
    const container = document.getElementById('commandesContainer');

    if (commandes.length === 0) {
        container.innerHTML = `
            <div class="col-span-full">
                <div class="bg-gradient-to-br from-gray-800 to-gray-900 border border-gray-700/50 rounded-2xl p-12 text-center">
                    <div class="inline-block bg-primary/10 p-6 rounded-full mb-6">
                        <i class="fas fa-check-circle text-primary text-5xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-white mb-2">Aucune commande en attente</h3>
                    <p class="text-gray-400">Toutes les commandes ont été traitées !</p>
                </div>
            </div>
        `;
        return;
    }

    container.innerHTML = commandes.map((commande, index) => {
        const tempsEcoule = parseInt(commande.temps_ecoule);
        const isUrgent = tempsEcoule > 15;
        const isCritical = tempsEcoule > 30;
        const statutClass = commande.statut === 'En préparation' ? 'en-preparation' : (isCritical ? 'urgent' : '');
        const bordureColor = commande.statut === 'En préparation' ? 'border-blue-500/50' : (isUrgent ? 'border-red-500/50' : 'border-yellow-500/50');

        return `
            <div class="commande-card ${statutClass} bg-gradient-to-br from-gray-800/95 to-gray-900/95 backdrop-blur-sm border-2 ${bordureColor} rounded-2xl p-5 shadow-xl hover:shadow-2xl relative overflow-hidden card-enter" style="animation-delay: ${index * 0.05}s">
                <!-- Effet de fond -->
                <div class="absolute inset-0 bg-gradient-to-br ${commande.statut === 'En préparation' ? 'from-blue-500/5 to-transparent' : (isUrgent ? 'from-red-500/10 to-transparent' : 'from-yellow-500/5 to-transparent')} pointer-events-none"></div>

                <div class="relative z-10">
                    <!-- Badge origine -->
                    <span class="absolute -top-2 -right-2 ${commande.origine === 'Manuelle' ? 'bg-yellow-500/20 text-yellow-400 border-yellow-500/50' : 'bg-blue-500/20 text-blue-400 border-blue-500/50'} border px-2.5 py-1 rounded-lg text-xs font-bold backdrop-blur-sm shadow-lg">
                        <i class="fas ${commande.origine === 'Manuelle' ? 'fa-hand-pointer' : 'fa-mobile-alt'} mr-1"></i>
                        ${commande.origine}
                    </span>

                    <!-- Header -->
                    <div class="mb-4 pb-4 border-b border-gray-700/50">
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="text-2xl font-bold bg-gradient-to-r from-white to-gray-300 bg-clip-text text-transparent">#${String(commande.id).padStart(4, '0')}</h3>
                            <div class="${isCritical ? 'bg-red-500/30 text-red-300 border-red-500/70 animate-pulse' : (isUrgent ? 'bg-red-500/20 text-red-400 border-red-500/50' : 'bg-gray-700/30 text-gray-300 border-gray-600/50')} border px-3 py-1.5 rounded-lg backdrop-blur-sm shadow-lg">
                                <i class="fas ${isCritical ? 'fa-exclamation-triangle' : 'fa-clock'} mr-1"></i>
                                <span class="text-sm font-bold">${tempsEcoule} min</span>
                            </div>
                        </div>
                        <div class="flex items-center mt-2">
                            <span class="w-2 h-2 rounded-full mr-2 ${commande.statut === 'En préparation' ? 'bg-blue-400 animate-pulse' : 'bg-yellow-400'}"></span>
                            <span class="text-xs font-medium ${commande.statut === 'En préparation' ? 'text-blue-400' : 'text-yellow-400'}">${commande.statut}</span>
                        </div>
                    </div>

                    <!-- Informations -->
                    <div class="space-y-2.5 mb-4">
                        <div class="flex items-center text-gray-300 bg-gray-900/40 rounded-lg px-3 py-2 border border-gray-700/30">
                            <div class="bg-primary/20 p-1.5 rounded-lg mr-3">
                                <i class="fas fa-user text-primary text-xs"></i>
                            </div>
                            <span class="text-sm font-medium">${escapeHtml(commande.nom_client)}</span>
                        </div>
                        ${commande.num_table ? `
                            <div class="flex items-center text-gray-300 bg-gray-900/40 rounded-lg px-3 py-2 border border-gray-700/30">
                                <div class="bg-primary/20 p-1.5 rounded-lg mr-3">
                                    <i class="fas fa-chair text-primary text-xs"></i>
                                </div>
                                <span class="text-sm font-medium">Table ${escapeHtml(commande.num_table)}</span>
                            </div>
                        ` : ''}
                        <div class="flex items-center text-gray-300 bg-gradient-to-r from-primary/10 to-primary/5 rounded-lg px-3 py-2 border border-primary/30">
                            <div class="bg-primary/30 p-1.5 rounded-lg mr-3">
                                <i class="fas fa-coins text-primary text-xs"></i>
                            </div>
                            <span class="text-sm font-bold text-primary">${parseFloat(commande.total).toLocaleString()} FCFA</span>
                        </div>
                    </div>

                    <!-- Articles -->
                    <div class="bg-gradient-to-br from-gray-900/70 to-gray-800/70 rounded-xl p-4 mb-4 border border-gray-700/40 backdrop-blur-sm">
                        <h4 class="text-xs font-bold text-gray-400 mb-3 flex items-center uppercase tracking-wider">
                            <i class="fas fa-utensils mr-2 text-primary"></i>
                            Articles (${commande.details.length})
                        </h4>
                        <div class="space-y-2 max-h-32 overflow-y-auto custom-scrollbar">
                            ${commande.details.map(detail => `
                                <div class="flex items-center justify-between text-sm bg-gray-800/50 rounded-lg px-3 py-2 hover:bg-gray-800/70 transition-all">
                                    <span class="text-gray-300 font-medium flex-1 truncate">${escapeHtml(detail.nom_plat)}</span>
                                    <span class="bg-gradient-to-r from-primary to-primary-dark text-white px-2.5 py-1 rounded-lg font-bold text-xs ml-2 shadow-lg">×${detail.quantite}</span>
                                </div>
                            `).join('')}
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex gap-2 mt-4">
                        ${commande.statut === 'En cours' ? `
                            <button onclick="demarrerPreparation(${commande.id})" class="flex-1 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white font-bold py-3 px-4 rounded-xl transition-all duration-300 flex items-center justify-center space-x-2 shadow-lg hover:shadow-xl hover:scale-105">
                                <i class="fas fa-fire"></i>
                                <span>Démarrer</span>
                            </button>
                        ` : ''}
                        ${commande.statut === 'En préparation' ? `
                            <button onclick="marquerPret(${commande.id})" class="flex-1 bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white font-bold py-3 px-4 rounded-xl transition-all duration-300 flex items-center justify-center space-x-2 shadow-lg hover:shadow-xl hover:scale-105">
                                <i class="fas fa-check-circle"></i>
                                <span>Prêt</span>
                            </button>
                        ` : ''}
                        <button onclick="annulerCommande(${commande.id})" class="bg-red-500/20 hover:bg-red-500/40 border border-red-500/50 hover:border-red-500 text-red-400 hover:text-red-300 font-bold py-3 px-4 rounded-xl transition-all duration-300 flex items-center justify-center hover:scale-105">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

// Fonction pour démarrer la préparation
async function demarrerPreparation(id) {
    try {
        const formData = new FormData();
        formData.append('action', 'demarrer_preparation');
        formData.append('id', id);

        const response = await fetch('cuisine.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            showNotification('✅ Préparation démarrée !', 'success');
            await chargerCommandes(true);
        } else {
            showNotification('❌ ' + data.message, 'error');
        }
    } catch (error) {
        console.error('Erreur:', error);
        showNotification('❌ Erreur de connexion', 'error');
    }
}

// Fonction pour marquer comme prêt
async function marquerPret(id) {
    try {
        const formData = new FormData();
        formData.append('action', 'marquer_pret');
        formData.append('id', id);

        const response = await fetch('cuisine.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            showNotification('🍽️ Commande prête pour le service !', 'success');
            await chargerCommandes(true);
        } else {
            showNotification('❌ ' + data.message, 'error');
        }
    } catch (error) {
        console.error('Erreur:', error);
        showNotification('❌ Erreur de connexion', 'error');
    }
}

// Fonction pour annuler une commande
async function annulerCommande(id) {
    const raison = prompt('Raison de l\'annulation:');
    if (!raison) return;

    try {
        const formData = new FormData();
        formData.append('action', 'annuler_commande');
        formData.append('id', id);
        formData.append('raison', raison);

        const response = await fetch('cuisine.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            showNotification('⚠️ Commande annulée', 'warning');
            await chargerCommandes(true);
        } else {
            showNotification('❌ ' + data.message, 'error');
        }
    } catch (error) {
        console.error('Erreur:', error);
        showNotification('❌ Erreur de connexion', 'error');
    }
}

// Fonction pour filtrer les commandes
function filtrerCommandes(filtre) {
    filtreActif = filtre;

    // Mettre à jour l'UI des boutons
    document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
    document.getElementById(`filter-${filtre}`).classList.add('active');

    // Appliquer le filtre et le tri
    afficherCommandesFiltrees();
}

// Fonction pour trier les commandes
function trierCommandes(tri) {
    triActif = tri;
    afficherCommandesFiltrees();
}

// Fonction pour appliquer filtres et tri
function afficherCommandesFiltrees() {
    let commandesFiltrees = [...commandesData];

    // Appliquer le filtre
    if (filtreActif === 'en-cours') {
        commandesFiltrees = commandesFiltrees.filter(c => c.statut === 'En cours');
    } else if (filtreActif === 'en-preparation') {
        commandesFiltrees = commandesFiltrees.filter(c => c.statut === 'En préparation');
    }

    // Appliquer le tri
    switch(triActif) {
        case 'temps':
            commandesFiltrees.sort((a, b) => parseInt(b.temps_ecoule) - parseInt(a.temps_ecoule));
            break;
        case 'temps-inverse':
            commandesFiltrees.sort((a, b) => parseInt(a.temps_ecoule) - parseInt(b.temps_ecoule));
            break;
        case 'montant':
            commandesFiltrees.sort((a, b) => parseFloat(a.total) - parseFloat(b.total));
            break;
        case 'montant-inverse':
            commandesFiltrees.sort((a, b) => parseFloat(b.total) - parseFloat(a.total));
            break;
    }

    afficherCommandes(commandesFiltrees);
}

// Fonction utilitaire pour échapper le HTML
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return String(text).replace(/[&<>"']/g, m => map[m]);
}

// Démarrage automatique
window.addEventListener('DOMContentLoaded', () => {
    console.log('🍽️ Cuisine.php - Initialisation...');

    // Charger immédiatement
    chargerCommandes();

    // Rafraîchissement automatique
    refreshTimer = setInterval(() => {
        chargerCommandes(true);
    }, AUTO_REFRESH_INTERVAL);
});

// Nettoyer le timer lors de la fermeture
window.addEventListener('beforeunload', () => {
    if (refreshTimer) {
        clearInterval(refreshTimer);
    }
});
</script>

</body>
</html>
