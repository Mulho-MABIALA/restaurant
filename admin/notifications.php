<?php
// Connexion PDO
try {
    $conn = new PDO("mysql:host=localhost;dbname=restaurant", "root", "");
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// 🗑️ Supprimer toutes
if (isset($_POST['delete_all'])) {
    $conn->query("DELETE FROM notifications");
    $_SESSION['success'] = "Toutes les notifications ont été supprimées avec succès.";
    header("Location: notifications.php");
    exit;
}

// 🗑️ Supprimer une notification spécifique
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $idToDelete = intval($_GET['delete']);
    $del = $conn->prepare("DELETE FROM notifications WHERE id = ?");
    $del->execute([$idToDelete]);
    $_SESSION['success'] = "Notification supprimée avec succès.";
    header("Location: notifications.php");
    exit;
}

// 👁️ Marquer comme vue/non vue
if (isset($_GET['toggle_view']) && is_numeric($_GET['toggle_view'])) {
    $idToToggle = intval($_GET['toggle_view']);
    $current = $conn->prepare("SELECT vue FROM notifications WHERE id = ?");
    $current->execute([$idToToggle]);
    $currentState = $current->fetchColumn();
    
    $newState = $currentState ? 0 : 1;
    $toggle = $conn->prepare("UPDATE notifications SET vue = ? WHERE id = ?");
    $toggle->execute([$newState, $idToToggle]);
    
    $_SESSION['success'] = $newState ? "Notification marquée comme vue." : "Notification marquée comme non vue.";
    header("Location: notifications.php");
    exit;
}

// 📧 Marquer toutes comme vues/non vues
if (isset($_POST['mark_all_read'])) {
    $conn->query("UPDATE notifications SET vue = 1");
    $_SESSION['success'] = "Toutes les notifications ont été marquées comme vues.";
    header("Location: notifications.php");
    exit;
}

if (isset($_POST['mark_all_unread'])) {
    $conn->query("UPDATE notifications SET vue = 0");
    $_SESSION['success'] = "Toutes les notifications ont été marquées comme non vues.";
    header("Location: notifications.php");
    exit;
}

// 🔍 Filtrage avancé
$type = $_GET['type'] ?? 'all';
$status = $_GET['status'] ?? 'all'; // nouveau filtre par statut
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = intval($_GET['limit'] ?? 10);
$offset = ($page - 1) * $limit;

// Construction de la requête avec filtres multiples
$whereConditions = [];
$params = [];

if ($type !== 'all') {
    $whereConditions[] = "type = ?";
    $params[] = $type;
}

if ($status !== 'all') {
    if ($status === 'read') {
        $whereConditions[] = "vue = 1";
    } else {
        $whereConditions[] = "vue = 0";
    }
}

if (!empty($search)) {
    $whereConditions[] = "message LIKE ?";
    $params[] = "%$search%";
}

$whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

// Exécution des requêtes
$stmt = $conn->prepare("SELECT * FROM notifications $whereClause ORDER BY date DESC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$notifs = $stmt->fetchAll();

$countStmt = $conn->prepare("SELECT COUNT(*) FROM notifications $whereClause");
$countStmt->execute($params);
$count = $countStmt->fetchColumn();

// Statistiques
$stats = [
    'total' => $conn->query("SELECT COUNT(*) FROM notifications")->fetchColumn(),
    'unread' => $conn->query("SELECT COUNT(*) FROM notifications WHERE vue = 0")->fetchColumn(),
    'danger' => $conn->query("SELECT COUNT(*) FROM notifications WHERE type = 'danger'")->fetchColumn(),
    'warning' => $conn->query("SELECT COUNT(*) FROM notifications WHERE type = 'warning'")->fetchColumn(),
    'info' => $conn->query("SELECT COUNT(*) FROM notifications WHERE type = 'info'")->fetchColumn()
];

// Pagination
$totalPages = ceil($count / $limit);

session_start();
$successMessage = $_SESSION['success'] ?? '';
unset($_SESSION['success']);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Centre de Notifications - Restaurant</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --success-gradient: linear-gradient(135deg, #4ade80 0%, #22c55e 100%);
            --danger-gradient: linear-gradient(135deg, #f87171 0%, #ef4444 100%);
            --warning-gradient: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
        }

        body {
            background: var(--primary-gradient);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .notification-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .notification-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }

        .notification-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            transition: left 0.5s;
        }

        .notification-card:hover::before {
            left: 100%;
        }

        .btn-primary {
            background: var(--primary-gradient);
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }

        .stats-card {
            background: rgba(255, 255, 255, 0.9);
            border-left: 4px solid transparent;
            transition: all 0.3s ease;
        }

        .stats-card:hover {
            transform: scale(1.05);
            background: rgba(255, 255, 255, 1);
        }

        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .pulse-notification {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        .search-input {
            transition: all 0.3s ease;
        }

        .search-input:focus {
            transform: scale(1.02);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body class="min-h-screen">
    
    <div class="container mx-auto px-4 py-6 max-w-7xl">
        
        <!-- Message de succès -->
        <?php if ($successMessage): ?>
            <div class="glass-card rounded-2xl p-4 mb-6 bg-green-50 border-l-4 border-green-500 fade-in">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-green-500 text-xl mr-3"></i>
                    <p class="text-green-800 font-medium"><?= htmlspecialchars($successMessage) ?></p>
                </div>
            </div>
        <?php endif; ?>

        <!-- En-tête principal -->
        <div class="glass-card rounded-3xl p-8 mb-8 fade-in">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-center space-x-4 mb-6 lg:mb-0">
                    <div class="bg-gradient-to-r from-blue-500 to-purple-600 p-4 rounded-full">
                        <i class="fas fa-bell text-white text-2xl"></i>
                    </div>
                    <div>
                        <h1 class="text-4xl font-bold text-gray-800 mb-2">Centre de Notifications</h1>
                        <p class="text-gray-600">Gérez efficacement toutes vos notifications système</p>
                    </div>
                </div>
                
                <div class="flex flex-wrap gap-3">
                    <form method="post" class="inline">
                        <button name="mark_all_read" class="btn-primary text-white px-6 py-3 rounded-full font-medium hover:shadow-lg transition-all">
                            <i class="fas fa-eye mr-2"></i>
                            Marquer tout comme vu
                        </button>
                    </form>
                    <form method="post" class="inline">
                        <button name="mark_all_unread" class="bg-gray-600 text-white px-6 py-3 rounded-full font-medium hover:bg-gray-700 transition-all">
                            <i class="fas fa-eye-slash mr-2"></i>
                            Marquer tout comme non vu
                        </button>
                    </form>
                    <form method="post" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer toutes les notifications ?')" class="inline">
                        <button name="delete_all" class="bg-gradient-to-r from-red-500 to-pink-500 text-white px-6 py-3 rounded-full font-medium hover:shadow-lg transition-all">
                            <i class="fas fa-trash mr-2"></i>
                            Supprimer toutes
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Statistiques -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
            <div class="stats-card rounded-2xl p-6 border-l-blue-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm font-medium">Total</p>
                        <p class="text-3xl font-bold text-gray-800"><?= $stats['total'] ?></p>
                    </div>
                    <i class="fas fa-list text-blue-500 text-2xl"></i>
                </div>
            </div>
            
            <div class="stats-card rounded-2xl p-6 border-l-orange-500 <?= $stats['unread'] > 0 ? 'pulse-notification' : '' ?>">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm font-medium">Non vues</p>
                        <p class="text-3xl font-bold text-orange-600"><?= $stats['unread'] ?></p>
                    </div>
                    <i class="fas fa-eye-slash text-orange-500 text-2xl"></i>
                </div>
            </div>
            
            <div class="stats-card rounded-2xl p-6 border-l-red-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm font-medium">Critiques</p>
                        <p class="text-3xl font-bold text-red-600"><?= $stats['danger'] ?></p>
                    </div>
                    <i class="fas fa-exclamation-triangle text-red-500 text-2xl"></i>
                </div>
            </div>
            
            <div class="stats-card rounded-2xl p-6 border-l-yellow-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm font-medium">Avertissements</p>
                        <p class="text-3xl font-bold text-yellow-600"><?= $stats['warning'] ?></p>
                    </div>
                    <i class="fas fa-exclamation-circle text-yellow-500 text-2xl"></i>
                </div>
            </div>
            
            <div class="stats-card rounded-2xl p-6 border-l-green-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm font-medium">Informations</p>
                        <p class="text-3xl font-bold text-green-600"><?= $stats['info'] ?></p>
                    </div>
                    <i class="fas fa-info-circle text-green-500 text-2xl"></i>
                </div>
            </div>
        </div>

        <!-- Filtres avancés -->
        <div class="glass-card rounded-3xl p-8 mb-8">
            <form method="get" class="space-y-6">
                <div class="flex items-center mb-4">
                    <i class="fas fa-filter text-gray-700 text-xl mr-3"></i>
                    <h3 class="text-xl font-bold text-gray-800">Filtres avancés</h3>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <!-- Recherche -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-search mr-2"></i>Rechercher
                        </label>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                               placeholder="Rechercher dans les messages..." 
                               class="search-input w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-blue-500 focus:outline-none">
                    </div>
                    
                    <!-- Type -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-tag mr-2"></i>Type
                        </label>
                        <select name="type" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-blue-500 focus:outline-none">
                            <option value="all" <?= $type === 'all' ? 'selected' : '' ?>>Tous les types</option>
                            <option value="danger" <?= $type === 'danger' ? 'selected' : '' ?>>🚨 Critique</option>
                            <option value="warning" <?= $type === 'warning' ? 'selected' : '' ?>>⚠️ Avertissement</option>
                            <option value="info" <?= $type === 'info' ? 'selected' : '' ?>>ℹ️ Information</option>
                        </select>
                    </div>
                    
                    <!-- Statut -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-eye mr-2"></i>Statut
                        </label>
                        <select name="status" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-blue-500 focus:outline-none">
                            <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Tous les statuts</option>
                            <option value="unread" <?= $status === 'unread' ? 'selected' : '' ?>>Non vues</option>
                            <option value="read" <?= $status === 'read' ? 'selected' : '' ?>>Vues</option>
                        </select>
                    </div>
                    
                    <!-- Nombre par page -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-list-ol mr-2"></i>Par page
                        </label>
                        <select name="limit" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-blue-500 focus:outline-none">
                            <option value="5" <?= $limit === 5 ? 'selected' : '' ?>>5</option>
                            <option value="10" <?= $limit === 10 ? 'selected' : '' ?>>10</option>
                            <option value="25" <?= $limit === 25 ? 'selected' : '' ?>>25</option>
                            <option value="50" <?= $limit === 50 ? 'selected' : '' ?>>50</option>
                        </select>
                    </div>
                </div>
                
                <div class="flex gap-4">
                    <button type="submit" class="btn-primary text-white px-8 py-3 rounded-xl font-medium">
                        <i class="fas fa-search mr-2"></i>Appliquer les filtres
                    </button>
                    <a href="notifications.php" class="bg-gray-200 text-gray-700 px-8 py-3 rounded-xl font-medium hover:bg-gray-300 transition-colors">
                        <i class="fas fa-times mr-2"></i>Réinitialiser
                    </a>
                </div>
            </form>
        </div>

        <!-- Liste des notifications -->
        <div class="glass-card rounded-3xl p-8 mb-8">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-2xl font-bold text-gray-800">
                    <i class="fas fa-list mr-3"></i>
                    Notifications (<?= $count ?> résultat<?= $count > 1 ? 's' : '' ?>)
                </h3>
            </div>
            
            <?php if (count($notifs) > 0): ?>
                <div class="space-y-4">
                    <?php foreach ($notifs as $index => $notif): ?>
                        <div class="notification-card rounded-2xl p-6 <?php
                            echo $notif['type'] === 'danger' ? 'bg-gradient-to-r from-red-50 to-red-100 border-l-4 border-red-500' :
                                 ($notif['type'] === 'warning' ? 'bg-gradient-to-r from-yellow-50 to-yellow-100 border-l-4 border-yellow-500' :
                                 'bg-gradient-to-r from-blue-50 to-blue-100 border-l-4 border-blue-500');
                            echo !$notif['vue'] ? ' ring-2 ring-orange-200' : '';
                        ?> fade-in" style="animation-delay: <?= $index * 0.1 ?>s">
                            
                            <div class="flex items-start justify-between">
                                <div class="flex items-start space-x-4 flex-1">
                                    <!-- Icône de type -->
                                    <div class="flex-shrink-0 mt-1">
                                        <?php
                                            $icon = $notif['type'] === 'danger' ? 'fas fa-exclamation-triangle text-red-500' : 
                                                   ($notif['type'] === 'warning' ? 'fas fa-exclamation-circle text-yellow-500' : 
                                                   'fas fa-info-circle text-blue-500');
                                            echo '<i class="' . $icon . ' text-2xl"></i>';
                                        ?>
                                    </div>
                                    
                                    <!-- Contenu -->
                                    <div class="flex-1">
                                        <div class="flex items-center mb-2">
                                            <?php if (!$notif['vue']): ?>
                                                <span class="bg-orange-500 text-white text-xs px-2 py-1 rounded-full mr-3 font-medium">
                                                    NOUVEAU
                                                </span>
                                            <?php endif; ?>
                                            <span class="text-xs font-medium text-gray-500 bg-gray-100 px-2 py-1 rounded-full">
                                                <?= strtoupper($notif['type']) ?>
                                            </span>
                                        </div>
                                        
                                        <p class="text-gray-800 font-medium leading-relaxed mb-3 text-lg">
                                            <?= htmlspecialchars($notif['message']) ?>
                                        </p>
                                        
                                        <div class="flex items-center text-sm text-gray-500 space-x-4">
                                            <span class="flex items-center">
                                                <i class="fas fa-calendar mr-2"></i>
                                                <?= date('d/m/Y à H:i', strtotime($notif['date'])) ?>
                                            </span>
                                            <span class="flex items-center">
                                                <i class="<?= $notif['vue'] ? 'fas fa-eye text-green-500' : 'fas fa-eye-slash text-orange-500' ?> mr-2"></i>
                                                <?= $notif['vue'] ? 'Vue' : 'Non vue' ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Actions -->
                                <div class="flex items-center space-x-2 ml-4">
                                    <!-- Toggle vue/non vue -->
                                    <a href="?toggle_view=<?= $notif['id'] ?>&<?= http_build_query($_GET) ?>" 
                                       title="<?= $notif['vue'] ? 'Marquer comme non vue' : 'Marquer comme vue' ?>"
                                       class="p-3 rounded-full transition-all hover:bg-white hover:shadow-md <?= $notif['vue'] ? 'text-orange-500 hover:text-orange-600' : 'text-green-500 hover:text-green-600' ?>">
                                        <i class="<?= $notif['vue'] ? 'fas fa-eye-slash' : 'fas fa-eye' ?> text-lg"></i>
                                    </a>
                                    
                                    <!-- Supprimer -->
                                    <a href="?delete=<?= $notif['id'] ?>&<?= http_build_query($_GET) ?>" 
                                       onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette notification ?')"
                                       title="Supprimer la notification"
                                       class="p-3 rounded-full text-red-500 hover:text-red-600 hover:bg-white hover:shadow-md transition-all">
                                        <i class="fas fa-trash text-lg"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-16">
                    <div class="mb-6">
                        <i class="fas fa-inbox text-6xl text-gray-300"></i>
                    </div>
                    <h4 class="text-2xl font-bold text-gray-600 mb-2">Aucune notification trouvée</h4>
                    <p class="text-gray-500">
                        <?= !empty($search) || $type !== 'all' || $status !== 'all' ? 
                            'Aucune notification ne correspond à vos critères de recherche.' : 
                            'Vous êtes à jour ! Aucune notification à afficher.' ?>
                    </p>
                    <?php if (!empty($search) || $type !== 'all' || $status !== 'all'): ?>
                        <a href="notifications.php" class="inline-block mt-4 btn-primary text-white px-6 py-3 rounded-xl font-medium">
                            <i class="fas fa-times mr-2"></i>Réinitialiser les filtres
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pagination améliorée -->
        <?php if ($totalPages > 1): ?>
            <div class="flex justify-center">
                <div class="glass-card rounded-2xl p-6">
                    <div class="flex items-center space-x-2">
                        <?php
                        $currentParams = $_GET;
                        
                        // Bouton précédent
                        if ($page > 1):
                            $currentParams['page'] = $page - 1;
                        ?>
                            <a href="?<?= http_build_query($currentParams) ?>" 
                               class="px-4 py-2 rounded-xl bg-white text-gray-700 hover:bg-gray-50 transition-all font-medium border">
                                <i class="fas fa-chevron-left mr-1"></i>Précédent
                            </a>
                        <?php endif; ?>
                        
                        <?php
                        // Pages
                        $start = max(1, $page - 2);
                        $end = min($totalPages, $page + 2);
                        
                        if ($start > 1):
                        ?>
                            <a href="?<?= http_build_query(array_merge($currentParams, ['page' => 1])) ?>" 
                               class="px-4 py-2 rounded-xl bg-white text-gray-700 hover:bg-gray-50 transition-all font-medium">1</a>
                            <?php if ($start > 2): ?>
                                <span class="px-2 text-gray-500">...</span>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for ($i = $start; $i <= $end; $i++): ?>
                            <a href="?<?= http_build_query(array_merge($currentParams, ['page' => $i])) ?>" 
                               class="px-4 py-2 rounded-xl font-medium transition-all <?= $i == $page ? 'bg-gradient-to-r from-blue-500 to-purple-600 text-white shadow-lg transform scale-105' : 'bg-white text-gray-700 hover:bg-gray-50 hover:shadow-md' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($end < $totalPages): ?>
                            <?php if ($end < $totalPages - 1): ?>
                                <span class="px-2 text-gray-500">...</span>
                            <?php endif; ?>
                            <a href="?<?= http_build_query(array_merge($currentParams, ['page' => $totalPages])) ?>" 
                               class="px-4 py-2 rounded-xl bg-white text-gray-700 hover:bg-gray-50 transition-all font-medium"><?= $totalPages ?></a>
                        <?php endif; ?>
                        
                        <!-- Bouton suivant -->
                        <?php if ($page < $totalPages):
                            $currentParams['page'] = $page + 1;
                        ?>
                            <a href="?<?= http_build_query($currentParams) ?>" 
                               class="px-4 py-2 rounded-xl bg-white text-gray-700 hover:bg-gray-50 transition-all font-medium border">
                                Suivant<i class="fas fa-chevron-right ml-1"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <div class="text-center mt-4 text-sm text-gray-600">
                        Affichage de <?= ($page - 1) * $limit + 1 ?> à <?= min($page * $limit, $count) ?> sur <?= $count ?> notifications
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </div>

    <script>
        // Animation d'entrée
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.fade-in');
            cards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });

        // Auto-refresh des statistiques toutes les 30 secondes
        setInterval(() => {
            fetch(window.location.href)
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    
                    // Mettre à jour les statistiques
                    const statsCards = document.querySelectorAll('.stats-card');
                    const newStatsCards = doc.querySelectorAll('.stats-card');
                    
                    statsCards.forEach((card, index) => {
                        if (newStatsCards[index]) {
                            const newNumber = newStatsCards[index].querySelector('.text-3xl');
                            const currentNumber = card.querySelector('.text-3xl');
                            if (newNumber && currentNumber && newNumber.textContent !== currentNumber.textContent) {
                                currentNumber.textContent = newNumber.textContent;
                                card.style.animation = 'pulse 0.5s ease-in-out';
                                setTimeout(() => card.style.animation = '', 500);
                            }
                        }
                    });
                })
                .catch(console.error);
        }, 30000);

        // Confirmation améliorée pour les suppressions
        function confirmDelete(message, callback) {
            if (confirm(message)) {
                callback();
            }
        }

        // Recherche en temps réel (optionnel)
        const searchInput = document.querySelector('input[name="search"]');
        let searchTimeout;
        
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    if (this.value.length > 2 || this.value.length === 0) {
                        // Auto-submit après 500ms d'inactivité
                        this.form.submit();
                    }
                }, 500);
            });
        }

        // Gestion des raccourcis clavier
        document.addEventListener('keydown', function(e) {
            // Ctrl + A : Marquer tout comme lu
            if (e.ctrlKey && e.key === 'a') {
                e.preventDefault();
                const markAllRead = document.querySelector('button[name="mark_all_read"]');
                if (markAllRead) markAllRead.click();
            }
            
            // Ctrl + D : Supprimer toutes les notifications
            if (e.ctrlKey && e.key === 'd') {
                e.preventDefault();
                const deleteAll = document.querySelector('button[name="delete_all"]');
                if (deleteAll && confirm('Supprimer toutes les notifications ?')) {
                    deleteAll.click();
                }
            }
            
            // Escape : Réinitialiser les filtres
            if (e.key === 'Escape') {
                window.location.href = 'notifications.php';
            }
        });

        // Smooth scroll pour la pagination
        document.querySelectorAll('a[href*="page="]').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                window.scrollTo({ top: 0, behavior: 'smooth' });
                setTimeout(() => {
                    window.location.href = this.href;
                }, 300);
            });
        });

        // Toast notifications pour le feedback utilisateur
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg text-white font-medium transition-all duration-300 transform translate-x-full ${
                type === 'success' ? 'bg-green-500' : 
                type === 'error' ? 'bg-red-500' : 
                type === 'warning' ? 'bg-yellow-500' : 'bg-blue-500'
            }`;
            toast.textContent = message;
            
            document.body.appendChild(toast);
            
            // Animation d'entrée
            setTimeout(() => {
                toast.style.transform = 'translateX(0)';
            }, 100);
            
            // Suppression automatique
            setTimeout(() => {
                toast.style.transform = 'translateX(full)';
                setTimeout(() => {
                    document.body.removeChild(toast);
                }, 300);
            }, 3000);
        }

        // Détection des nouvelles notifications (simulation)
        let lastNotificationCount = <?= $stats['total'] ?>;
        
        function checkForNewNotifications() {
            fetch('check_notifications.php')
                .then(response => response.json())
                .then(data => {
                    if (data.count > lastNotificationCount) {
                        showToast(`${data.count - lastNotificationCount} nouvelle(s) notification(s)`, 'info');
                        lastNotificationCount = data.count;
                        
                        // Mettre à jour le titre de la page
                        const unreadCount = data.unread || 0;
                        document.title = unreadCount > 0 ? 
                            `(${unreadCount}) Centre de Notifications - Restaurant` : 
                            'Centre de Notifications - Restaurant';
                    }
                })
                .catch(console.error);
        }
        
        // Vérifier les nouvelles notifications toutes les minutes
        setInterval(checkForNewNotifications, 60000);

        // Gestion du mode sombre (bonus)
        function toggleDarkMode() {
            document.body.classList.toggle('dark-mode');
            localStorage.setItem('darkMode', document.body.classList.contains('dark-mode'));
        }

        // Restaurer le mode sombre au chargement
        if (localStorage.getItem('darkMode') === 'true') {
            document.body.classList.add('dark-mode');
        }

        // Préloader pour améliorer les performances
        function preloadNextPage() {
            const nextPageLink = document.querySelector('a[href*="page=' + (<?= $page ?> + 1) + '"]');
            if (nextPageLink) {
                const link = document.createElement('link');
                link.rel = 'prefetch';
                link.href = nextPageLink.href;
                document.head.appendChild(link);
            }
        }

        // Précharger la page suivante si elle existe
        preloadNextPage();

        // Animation de chargement pour les actions
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function() {
                const button = this.querySelector('button[type="submit"]');
                if (button) {
                    button.disabled = true;
                    button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>' + button.textContent;
                }
            });
        });

        // Gestion des erreurs réseau
        window.addEventListener('online', () => {
            showToast('Connexion rétablie', 'success');
        });

        window.addEventListener('offline', () => {
            showToast('Connexion perdue', 'error');
        });
    </script>

    <!-- Styles pour le mode sombre (bonus) -->
    <style>
        .dark-mode {
            filter: invert(1) hue-rotate(180deg);
        }
        
        .dark-mode img,
        .dark-mode video,
        .dark-mode iframe {
            filter: invert(1) hue-rotate(180deg);
        }

        /* Styles responsive améliorés */
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .glass-card {
                padding: 1rem;
            }
            
            .stats-card {
                padding: 1rem;
            }
            
            .notification-card {
                padding: 1rem;
            }
            
            h1 {
                font-size: 2rem;
            }
            
            .grid {
                grid-template-columns: 1fr;
            }
            
            .flex {
                flex-direction: column;
                gap: 1rem;
            }
            
            .flex.lg\\:flex-row {
                flex-direction: column;
            }
        }

        @media (max-width: 480px) {
            .text-4xl {
                font-size: 1.875rem;
            }
            
            .text-3xl {
                font-size: 1.5rem;
            }
            
            .px-8 {
                padding-left: 1rem;
                padding-right: 1rem;
            }
            
            .py-3 {
                padding-top: 0.5rem;
                padding-bottom: 0.5rem;
            }
        }

        /* Améliorations d'accessibilité */
        .notification-card:focus-within {
            outline: 2px solid #3b82f6;
            outline-offset: 2px;
        }

        button:focus,
        a:focus,
        input:focus,
        select:focus {
            outline: 2px solid #3b82f6;
            outline-offset: 2px;
        }

        /* Animation de chargement */
        .loading {
            position: relative;
            overflow: hidden;
        }

        .loading::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            animation: loading 1.5s infinite;
        }

        @keyframes loading {
            0% { left: -100%; }
            100% { left: 100%; }
        }

        /* Styles pour les notifications toast */
        .toast-enter {
            transform: translateX(100%);
            opacity: 0;
        }

        .toast-enter-active {
            transform: translateX(0);
            opacity: 1;
            transition: all 0.3s ease;
        }

        .toast-exit {
            transform: translateX(0);
            opacity: 1;
        }

        .toast-exit-active {
            transform: translateX(100%);
            opacity: 0;
            transition: all 0.3s ease;
        }

        /* Améliorations pour l'impression */
        @media print {
            .glass-card {
                background: white !important;
                box-shadow: none !important;
                border: 1px solid #ccc !important;
            }
            
            .btn-primary,
            button,
            .stats-card:hover {
                background: white !important;
                color: black !important;
                transform: none !important;
                box-shadow: none !important;
            }
            
            .notification-card {
                break-inside: avoid;
                margin-bottom: 1rem;
            }
            
            .fade-in {
                animation: none !important;
            }
        }

        /* Performance optimisations */
        .notification-card {
            contain: layout style;
        }

        .stats-card {
            will-change: transform;
        }

        .btn-primary {
            will-change: transform, box-shadow;
        }
    </style>

</body>
</html>