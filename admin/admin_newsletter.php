<?php
session_start();
// admin_newsletter.php - Version complète et organisée
require_once '../config.php';
require_once './permissions.php';

// Rediriger si l'admin n'est pas connecté
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
requireAccess($conn, $_SESSION['admin_id'], 'admin_newsletter');

// Vérifier que admin_id existe
if (!isset($_SESSION['admin_id'])) {
    // Tenter de récupérer depuis la DB si username existe
    if (isset($_SESSION['admin_username'])) {
        $stmt = $conn->prepare("SELECT id FROM admin WHERE username = ?");
        $stmt->execute([$_SESSION['admin_username']]);
        $admin_data = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($admin_data) {
            $_SESSION['admin_id'] = (int)$admin_data['id'];
        }
    }

    // Si toujours pas défini, rediriger vers login
    if (!isset($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit;
    }
}

// Récupérer les infos de l'admin
$stmt_admin = $conn->prepare("SELECT username, email FROM admin WHERE id = ?");
$stmt_admin->execute([$_SESSION['admin_id']]);
$admin_info = $stmt_admin->fetch(PDO::FETCH_ASSOC);
$admin_name = $admin_info['username'] ?? $_SESSION['admin_username'] ?? 'Admin';
$admin_email = $admin_info['email'] ?? 'admin@restaurant.com';
$admin_photo = null; // Photo non disponible dans la base de données

// Actions spéciales
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $subscriber_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    switch ($action) {
        case 'export_advanced':
            exportAdvancedData($conn);
            break;
        case 'bulk_action':
            processBulkAction($conn);
            break;
        case 'add_to_segment':
            addToSegment($conn, $subscriber_id, $_GET['segment_id'] ?? 0);
            break;
    }
}

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = isset($_GET['limit']) ? min(100, max(10, intval($_GET['limit']))) : 20;
$offset = ($page - 1) * $limit;

// Filtres avancés
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$source_filter = isset($_GET['source']) ? $_GET['source'] : '';
$segment_filter = isset($_GET['segment']) ? (int)$_GET['segment'] : 0;
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$engagement_filter = isset($_GET['engagement']) ? $_GET['engagement'] : '';

// Construction de la requête avec filtres avancés
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(n.email LIKE ? OR n.first_name LIKE ? OR n.last_name LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}

if (!empty($status_filter)) {
    $where_conditions[] = "n.statut = ?";
    $params[] = $status_filter;
}

if (!empty($source_filter)) {
    $where_conditions[] = "n.source = ?";
    $params[] = $source_filter;
}

if (!empty($date_from)) {
    $where_conditions[] = "DATE(n.date_inscription) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(n.date_inscription) <= ?";
    $params[] = $date_to;
}

if ($segment_filter > 0) {
    $where_conditions[] = "EXISTS (SELECT 1 FROM newsletter_subscriber_segments nss WHERE nss.subscriber_id = n.id AND nss.segment_id = ?)";
    $params[] = $segment_filter;
}

if (!empty($engagement_filter)) {
    switch ($engagement_filter) {
        case 'high':
            $where_conditions[] = "n.total_opens >= 10";
            break;
        case 'medium':
            $where_conditions[] = "n.total_opens BETWEEN 3 AND 9";
            break;
        case 'low':
            $where_conditions[] = "n.total_opens BETWEEN 1 AND 2";
            break;
        case 'none':
            $where_conditions[] = "n.total_opens = 0";
            break;
    }
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
$order_by = isset($_GET['sort']) ? $_GET['sort'] : 'date_inscription';
$order_dir = isset($_GET['dir']) && $_GET['dir'] === 'asc' ? 'ASC' : 'DESC';

try {
    // Compter le total pour la pagination
    $count_query = "SELECT COUNT(*) FROM newsletter n $where_clause";
    $count_stmt = $conn->prepare($count_query);
    $count_stmt->execute($params);
    $total = $count_stmt->fetchColumn();

    // Récupérer les abonnés avec informations étendues
    $query = "
        SELECT n.*,
               GROUP_CONCAT(ns.name SEPARATOR ', ') as segments,
               (SELECT COUNT(*) FROM newsletter_tracking nt WHERE nt.subscriber_id = n.id AND nt.opened_at IS NOT NULL) as campaign_opens,
               (SELECT COUNT(*) FROM newsletter_tracking nt WHERE nt.subscriber_id = n.id AND nt.clicked_at IS NOT NULL) as campaign_clicks,
               DATEDIFF(NOW(), n.last_activity) as days_inactive
        FROM newsletter n
        LEFT JOIN newsletter_subscriber_segments nss ON n.id = nss.subscriber_id
        LEFT JOIN newsletter_segments ns ON nss.segment_id = ns.id
        $where_clause
        GROUP BY n.id
        ORDER BY n.$order_by $order_dir
        LIMIT $limit OFFSET $offset
    ";

    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $subscribers = $stmt->fetchAll();

    // Statistiques avancées
    $stats_query = "
        SELECT
            COUNT(*) as total,
            COUNT(CASE WHEN statut = 'actif' THEN 1 END) as actifs,
            COUNT(CASE WHEN statut = 'inactif' THEN 1 END) as inactifs,
            COUNT(CASE WHEN DATE(date_inscription) = CURDATE() THEN 1 END) as aujourd_hui,
            COUNT(CASE WHEN date_inscription >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as cette_semaine,
            COUNT(CASE WHEN date_inscription >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as ce_mois,
            COUNT(CASE WHEN total_opens >= 10 THEN 1 END) as high_engagement,
            COUNT(CASE WHEN total_opens BETWEEN 3 AND 9 THEN 1 END) as medium_engagement,
            COUNT(CASE WHEN total_opens BETWEEN 1 AND 2 THEN 1 END) as low_engagement,
            COUNT(CASE WHEN total_opens = 0 THEN 1 END) as no_engagement,
            AVG(total_opens) as avg_opens,
            AVG(total_clicks) as avg_clicks
        FROM newsletter n
        $where_clause
    ";

    $stats_stmt = $conn->prepare($stats_query);
    $stats_stmt->execute($params);
    $stats = $stats_stmt->fetch();

    // Récupérer les segments disponibles
    $segments = $conn->query("SELECT * FROM newsletter_segments WHERE is_active = 1 ORDER BY name")->fetchAll();

    // Récupérer les sources d'inscription
    $sources = $conn->query("SELECT DISTINCT source FROM newsletter WHERE source IS NOT NULL ORDER BY source")->fetchAll();

} catch (PDOException $e) {
    error_log("Erreur admin newsletter enhanced: " . $e->getMessage());
    $subscribers = [];
    $total = 0;
    $stats = [
        'total' => 0, 'actifs' => 0, 'inactifs' => 0, 'aujourd_hui' => 0,
        'cette_semaine' => 0, 'ce_mois' => 0, 'high_engagement' => 0,
        'medium_engagement' => 0, 'low_engagement' => 0, 'no_engagement' => 0,
        'avg_opens' => 0, 'avg_clicks' => 0
    ];
    $segments = [];
    $sources = [];
}

$total_pages = ceil($total / $limit);

// ===== FONCTIONS UTILITAIRES =====

function exportAdvancedData($conn) {
    // Export CSV avancé avec toutes les données
    $query = "
        SELECT n.email, n.first_name, n.last_name, n.phone, n.statut, n.source,
               n.date_inscription, n.confirmed_at, n.unsubscribed_at, n.unsubscribe_reason,
               n.total_opens, n.total_clicks, n.last_activity,
               GROUP_CONCAT(ns.name SEPARATOR '; ') as segments
        FROM newsletter n
        LEFT JOIN newsletter_subscriber_segments nss ON n.id = nss.subscriber_id
        LEFT JOIN newsletter_segments ns ON nss.segment_id = ns.id
        GROUP BY n.id
        ORDER BY n.date_inscription DESC
    ";

    $stmt = $conn->query($query);
    $data = $stmt->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=newsletter_abonnes_complet_' . date('Y-m-d') . '.csv');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8

    // En-têtes
    fputcsv($output, [
        'Email', 'Prénom', 'Nom', 'Téléphone', 'Statut', 'Source',
        'Date inscription', 'Confirmé le', 'Désabonné le', 'Raison désabonnement',
        'Total ouvertures', 'Total clics', 'Dernière activité', 'Segments'
    ]);

    foreach ($data as $row) {
        fputcsv($output, [
            $row['email'],
            $row['first_name'],
            $row['last_name'],
            $row['phone'],
            $row['statut'],
            $row['source'],
            $row['date_inscription'] ? date('d/m/Y H:i', strtotime($row['date_inscription'])) : '',
            $row['confirmed_at'] ? date('d/m/Y H:i', strtotime($row['confirmed_at'])) : '',
            $row['unsubscribed_at'] ? date('d/m/Y H:i', strtotime($row['unsubscribed_at'])) : '',
            $row['unsubscribe_reason'],
            $row['total_opens'],
            $row['total_clicks'],
            $row['last_activity'] ? date('d/m/Y H:i', strtotime($row['last_activity'])) : '',
            $row['segments']
        ]);
    }

    fclose($output);
    exit;
}

function processBulkAction($conn) {
    $action = $_POST['bulk_action'] ?? '';
    $selected_ids = $_POST['selected_subscribers'] ?? [];

    if (empty($selected_ids) || empty($action)) {
        $_SESSION['error_message'] = "Aucun abonné sélectionné ou action non spécifiée";
        return;
    }

    $placeholders = str_repeat('?,', count($selected_ids) - 1) . '?';
    $count = 0;

    try {
        switch ($action) {
            case 'activate':
                $stmt = $conn->prepare("UPDATE newsletter SET statut = 'actif' WHERE id IN ($placeholders)");
                $stmt->execute($selected_ids);
                $count = $stmt->rowCount();
                $_SESSION['success_message'] = "$count abonné(s) activé(s)";
                break;

            case 'deactivate':
                $stmt = $conn->prepare("UPDATE newsletter SET statut = 'inactif', unsubscribed_at = NOW() WHERE id IN ($placeholders)");
                $stmt->execute($selected_ids);
                $count = $stmt->rowCount();
                $_SESSION['success_message'] = "$count abonné(s) désactivé(s)";
                break;

            case 'delete':
                $stmt = $conn->prepare("DELETE FROM newsletter WHERE id IN ($placeholders)");
                $stmt->execute($selected_ids);
                $count = $stmt->rowCount();
                $_SESSION['success_message'] = "$count abonné(s) supprimé(s)";
                break;

            case 'add_segment':
                $segment_id = (int)($_POST['target_segment'] ?? 0);
                if ($segment_id > 0) {
                    $stmt = $conn->prepare("
                        INSERT IGNORE INTO newsletter_subscriber_segments (subscriber_id, segment_id)
                        SELECT id, ? FROM newsletter WHERE id IN ($placeholders)
                    ");
                    $stmt->execute(array_merge([$segment_id], $selected_ids));
                    $count = $stmt->rowCount();
                    $_SESSION['success_message'] = "$count abonné(s) ajouté(s) au segment";
                }
                break;

            case 'remove_segment':
                $segment_id = (int)($_POST['target_segment'] ?? 0);
                if ($segment_id > 0) {
                    $stmt = $conn->prepare("
                        DELETE FROM newsletter_subscriber_segments
                        WHERE subscriber_id IN ($placeholders) AND segment_id = ?
                    ");
                    $stmt->execute(array_merge($selected_ids, [$segment_id]));
                    $count = $stmt->rowCount();
                    $_SESSION['success_message'] = "$count abonné(s) retiré(s) du segment";
                }
                break;
        }
    } catch (PDOException $e) {
        error_log("Erreur bulk action: " . $e->getMessage());
        $_SESSION['error_message'] = "Erreur lors de l'opération groupée";
    }

    header('Location: admin_newsletter.php');
    exit;
}

function addToSegment($conn, $subscriber_id, $segment_id) {
    try {
        $stmt = $conn->prepare("INSERT IGNORE INTO newsletter_subscriber_segments (subscriber_id, segment_id) VALUES (?, ?)");
        $stmt->execute([$subscriber_id, $segment_id]);
        $_SESSION['success_message'] = "Abonné ajouté au segment";
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Erreur lors de l'ajout au segment";
    }

    header('Location: admin_newsletter.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion Newsletter</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#eff6ff',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a'
                        }
                    }
                }
            }
        }
    </script>
    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .animate-fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }

        /* Fix pour le sidebar */
        #sidebar {
            background: rgba(15, 23, 42, 0.95) !important;
            z-index: 50;
        }

        .stats-card {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border: 1px solid #e5e7eb;
        }

        .stats-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }

        .stats-card.blue {
            border-left: 4px solid #3b82f6;
        }
        .stats-card.green {
            border-left: 4px solid #10b981;
        }
        .stats-card.orange {
            border-left: 4px solid #f59e0b;
        }
        .stats-card.red {
            border-left: 4px solid #ef4444;
        }
        .stats-card.purple {
            border-left: 4px solid #8b5cf6;
        }

        .engagement-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        .engagement-high {
            background-color: #d1fae5;
            color: #065f46;
        }
        .engagement-medium {
            background-color: #dbeafe;
            color: #1e40af;
        }
        .engagement-low {
            background-color: #fef3c7;
            color: #92400e;
        }
        .engagement-none {
            background-color: #f3f4f6;
            color: #374151;
        }

        .sortable {
            cursor: pointer;
            user-select: none;
        }

        .sortable:hover {
            background-color: #f9fafb;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50 min-h-screen">

    <div class="flex h-screen overflow-hidden">

        <?php include 'sidebar.php'; ?>

        <div class="flex-1 overflow-y-auto">

            <!-- Header -->
            <header class="bg-slate-900 shadow-lg sticky top-0 z-40">
                <div class="px-4 sm:px-6 lg:px-8 py-4">
                    <div class="flex justify-between items-center">
                        <div class="flex items-center space-x-4">
                            <div class="w-12 h-12 bg-indigo-600 rounded-xl flex items-center justify-center">
                                <i class="fas fa-envelope-open-text text-white text-lg"></i>
                            </div>
                            <div>
                                <h1 class="text-2xl font-bold text-white">Gestion Newsletter</h1>
                                <p class="text-gray-400 text-sm">Gérez vos abonnés et campagnes</p>
                            </div>
                        </div>

                        <!-- Actions rapides -->
                        <div class="flex items-center space-x-3">
                            <a href="admin_newsletter_import.php" class="px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg transition-colors text-sm font-medium">
                                <i class="fas fa-upload mr-2"></i>Importer
                            </a>
                            <a href="admin_newsletter_compose.php" class="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg transition-colors text-sm font-medium">
                                <i class="fas fa-edit mr-2"></i>Composer
                            </a>
                            <a href="admin_newsletter_campaigns.php" class="px-4 py-2 bg-purple-500 hover:bg-purple-600 text-white rounded-lg transition-colors text-sm font-medium">
                                <i class="fas fa-paper-plane mr-2"></i>Campagnes
                            </a>
                            <a href="admin_newsletter_analytics.php" class="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg transition-colors text-sm font-medium">
                                <i class="fas fa-chart-bar mr-2"></i>Analytics
                            </a>
                        </div>
                    </div>
                </div>
            </header>

            <div class="p-8">

                <!-- Messages -->
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6 flex items-center">
                        <i class="fas fa-check-circle mr-3 text-green-500"></i>
                        <span><?= htmlspecialchars($_SESSION['success_message']) ?></span>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6 flex items-center">
                        <i class="fas fa-exclamation-circle mr-3 text-red-500"></i>
                        <span><?= htmlspecialchars($_SESSION['error_message']) ?></span>
                    </div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>

                <!-- Statistiques principales -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-6 mb-8">
                    <div class="stats-card blue">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-600 text-sm font-medium mb-1">Total</p>
                                <p class="text-2xl font-bold text-gray-900"><?= number_format($stats['total']) ?></p>
                            </div>
                            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-users text-blue-500 text-xl"></i>
                            </div>
                        </div>
                    </div>

                    <div class="stats-card green">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-600 text-sm font-medium mb-1">Actifs</p>
                                <p class="text-2xl font-bold text-gray-900"><?= number_format($stats['actifs']) ?></p>
                            </div>
                            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-user-check text-green-500 text-xl"></i>
                            </div>
                        </div>
                    </div>

                    <div class="stats-card orange">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-600 text-sm font-medium mb-1">Cette semaine</p>
                                <p class="text-2xl font-bold text-gray-900"><?= number_format($stats['cette_semaine']) ?></p>
                            </div>
                            <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-calendar-week text-orange-500 text-xl"></i>
                            </div>
                        </div>
                    </div>

                    <div class="stats-card red">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-600 text-sm font-medium mb-1">Aujourd'hui</p>
                                <p class="text-2xl font-bold text-gray-900"><?= number_format($stats['aujourd_hui']) ?></p>
                            </div>
                            <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-calendar-day text-red-500 text-xl"></i>
                            </div>
                        </div>
                    </div>

                    <div class="stats-card purple">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-600 text-sm font-medium mb-1">Moy. Ouv.</p>
                                <p class="text-2xl font-bold text-gray-900"><?= number_format($stats['avg_opens'], 1) ?></p>
                            </div>
                            <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-envelope-open text-purple-500 text-xl"></i>
                            </div>
                        </div>
                    </div>

                    <div class="stats-card blue">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-600 text-sm font-medium mb-1">Moy. Clics</p>
                                <p class="text-2xl font-bold text-gray-900"><?= number_format($stats['avg_clicks'], 1) ?></p>
                            </div>
                            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-mouse-pointer text-blue-500 text-xl"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Répartition de l'engagement -->
                <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white rounded-lg shadow-sm p-6 border-l-4 border-green-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-600 mb-1">Fort engagement</p>
                                <p class="text-2xl font-bold text-green-600"><?= number_format($stats['high_engagement']) ?></p>
                                <p class="text-xs text-gray-500 mt-1">≥ 10 ouvertures</p>
                            </div>
                            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-fire text-green-500 text-xl"></i>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-lg shadow-sm p-6 border-l-4 border-blue-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-600 mb-1">Engagement moyen</p>
                                <p class="text-2xl font-bold text-blue-600"><?= number_format($stats['medium_engagement']) ?></p>
                                <p class="text-xs text-gray-500 mt-1">3-9 ouvertures</p>
                            </div>
                            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-chart-line text-blue-500 text-xl"></i>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-lg shadow-sm p-6 border-l-4 border-yellow-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-600 mb-1">Faible engagement</p>
                                <p class="text-2xl font-bold text-yellow-600"><?= number_format($stats['low_engagement']) ?></p>
                                <p class="text-xs text-gray-500 mt-1">1-2 ouvertures</p>
                            </div>
                            <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-chart-bar text-yellow-500 text-xl"></i>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-lg shadow-sm p-6 border-l-4 border-gray-500">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-600 mb-1">Aucun engagement</p>
                                <p class="text-2xl font-bold text-gray-600"><?= number_format($stats['no_engagement']) ?></p>
                                <p class="text-xs text-gray-500 mt-1">0 ouverture</p>
                            </div>
                            <div class="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-user-times text-gray-500 text-xl"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filtres -->
                <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                    <form method="GET" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Recherche</label>
                                <input type="text"
                                       name="search"
                                       value="<?= htmlspecialchars($search) ?>"
                                       placeholder="Email, prénom, nom..."
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-4">
                            <button type="submit" class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors font-medium">
                                <i class="fas fa-search mr-2"></i>Filtrer
                            </button>

                            <a href="?" class="px-6 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg transition-colors font-medium">
                                <i class="fas fa-times mr-2"></i>Reset
                            </a>

                            <div class="ml-auto flex items-center gap-3">
                                <label class="text-sm text-gray-700 font-medium">Affichage:</label>
                                <select name="limit" onchange="this.form.submit()" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                                    <option value="10" <?= $limit === 10 ? 'selected' : '' ?>>10</option>
                                    <option value="20" <?= $limit === 20 ? 'selected' : '' ?>>20</option>
                                    <option value="50" <?= $limit === 50 ? 'selected' : '' ?>>50</option>
                                    <option value="100" <?= $limit === 100 ? 'selected' : '' ?>>100</option>
                                </select>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Actions groupées et export -->
                <div class="flex justify-between items-center mb-6">
                    <div class="text-gray-600 font-medium">
                        Affichage de <?= $offset + 1 ?> à <?= min($offset + $limit, $total) ?> sur <?= number_format($total) ?> résultats
                    </div>

                    <div class="flex gap-3">
                        <a href="?action=export_advanced&<?= http_build_query($_GET) ?>"
                           class="px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg transition-colors font-medium">
                            <i class="fas fa-download mr-2"></i>Export Complet
                        </a>

                        <button onclick="toggleBulkActions()" id="bulkToggle"
                                class="px-4 py-2 bg-purple-500 hover:bg-purple-600 text-white rounded-lg transition-colors font-medium">
                            <i class="fas fa-tasks mr-2"></i>Actions Groupées
                        </button>

                        <button onclick="refreshPage()"
                                class="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg transition-colors font-medium">
                            <i class="fas fa-sync-alt mr-2"></i>Actualiser
                        </button>
                    </div>
                </div>

                <!-- Formulaire actions groupées -->
                <div id="bulkActionsForm" class="hidden bg-purple-50 rounded-lg shadow-sm p-4 mb-6 border-l-4 border-purple-500">
                    <form method="POST" action="?action=bulk_action" onsubmit="return confirmBulkAction()">
                        <div class="flex flex-wrap items-center gap-4">
                            <div>
                                <select name="bulk_action" required class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                                    <option value="">Choisir une action...</option>
                                    <option value="activate">Activer</option>
                                    <option value="deactivate">Désactiver</option>
                                    <option value="delete">Supprimer</option>
                                    <option value="add_segment">Ajouter au segment</option>
                                    <option value="remove_segment">Retirer du segment</option>
                                </select>
                            </div>

                            <div id="segmentSelect" class="hidden">
                                <select name="target_segment" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                                    <option value="">Choisir un segment...</option>
                                    <?php foreach ($segments as $segment): ?>
                                    <option value="<?= $segment['id'] ?>"><?= htmlspecialchars($segment['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <button type="submit" class="px-6 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg transition-colors font-medium">
                                <i class="fas fa-play mr-2"></i>Exécuter
                            </button>

                            <span class="text-sm text-gray-600 font-medium">
                                <span id="selectedCount">0</span> abonné(s) sélectionné(s)
                            </span>
                        </div>
                    </form>
                </div>

                <!-- Tableau des abonnés -->
                <div class="bg-white shadow-lg rounded-lg overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-4 text-left">
                                        <input type="checkbox" id="selectAll" onchange="toggleSelectAll()" class="w-4 h-4 text-blue-600 rounded focus:ring-2 focus:ring-blue-500">
                                    </th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider sortable" onclick="sortTable('email')">
                                        Email
                                        <?php if ($order_by === 'email'): ?>
                                            <i class="fas fa-sort-<?= $order_dir === 'ASC' ? 'up' : 'down' ?> ml-1"></i>
                                        <?php endif; ?>
                                    </th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider sortable" onclick="sortTable('first_name')">
                                        Nom complet
                                        <?php if ($order_by === 'first_name'): ?>
                                            <i class="fas fa-sort-<?= $order_dir === 'ASC' ? 'up' : 'down' ?> ml-1"></i>
                                        <?php endif; ?>
                                    </th>
                                    <th class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider sortable" onclick="sortTable('date_inscription')">
                                        Inscription
                                        <?php if ($order_by === 'date_inscription'): ?>
                                            <i class="fas fa-sort-<?= $order_dir === 'ASC' ? 'up' : 'down' ?> ml-1"></i>
                                        <?php endif; ?>
                                    </th>
                                    <th class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Statut</th>
                                    <th class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Engagement</th>
                                    <th class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Segments</th>
                                    <th class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (count($subscribers) > 0): ?>
                                    <?php foreach ($subscribers as $subscriber): ?>
                                    <?php
                                    // Déterminer le niveau d'engagement
                                    $engagement_level = 'none';
                                    $engagement_class = 'engagement-none';
                                    if ($subscriber['total_opens'] >= 10) {
                                        $engagement_level = 'high';
                                        $engagement_class = 'engagement-high';
                                    } elseif ($subscriber['total_opens'] >= 3) {
                                        $engagement_level = 'medium';
                                        $engagement_class = 'engagement-medium';
                                    } elseif ($subscriber['total_opens'] >= 1) {
                                        $engagement_level = 'low';
                                        $engagement_class = 'engagement-low';
                                    }
                                    ?>
                                    <tr class="hover:bg-gray-50 transition-colors" data-subscriber-id="<?= $subscriber['id'] ?>">
                                        <td class="px-4 py-4">
                                            <input type="checkbox" name="selected_subscribers[]" value="<?= $subscriber['id'] ?>"
                                                   class="subscriber-checkbox w-4 h-4 text-blue-600 rounded focus:ring-2 focus:ring-blue-500" onchange="updateSelectedCount()">
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-10 w-10">
                                                    <div class="h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center">
                                                        <i class="fas fa-user text-blue-500"></i>
                                                    </div>
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($subscriber['email']) ?></div>
                                                    <?php if ($subscriber['source']): ?>
                                                        <div class="text-xs text-gray-500">Source: <?= htmlspecialchars($subscriber['source']) ?></div>
                                                    <?php endif; ?>
                                                    <?php if ($subscriber['phone']): ?>
                                                        <div class="text-xs text-gray-500">
                                                            <i class="fas fa-phone mr-1"></i><?= htmlspecialchars($subscriber['phone']) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?= htmlspecialchars(trim($subscriber['first_name'] . ' ' . $subscriber['last_name'])) ?: 'Non renseigné' ?>
                                            </div>
                                            <?php if ($subscriber['last_activity']): ?>
                                                <div class="text-xs text-gray-500">
                                                    Dernière activité: <?= date('d/m/Y', strtotime($subscriber['last_activity'])) ?>
                                                    <?php if ($subscriber['days_inactive'] !== null && $subscriber['days_inactive'] > 30): ?>
                                                        <span class="text-orange-500">(<?= $subscriber['days_inactive'] ?>j inactif)</span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <div class="text-sm text-gray-900">
                                                <?= date('d/m/Y', strtotime($subscriber['date_inscription'])) ?>
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                <?= date('H:i', strtotime($subscriber['date_inscription'])) ?>
                                            </div>
                                            <?php if ($subscriber['confirmed_at']): ?>
                                                <div class="text-xs text-green-600 mt-1">
                                                    <i class="fas fa-check-circle mr-1"></i>Confirmé
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <span class="inline-flex px-3 py-1 text-xs font-semibold rounded-full
                                                <?= $subscriber['statut'] === 'actif' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                                <?= $subscriber['statut'] === 'actif' ? 'Actif' : 'Inactif' ?>
                                            </span>
                                            <?php if ($subscriber['unsubscribed_at']): ?>
                                                <div class="text-xs text-gray-500 mt-1">
                                                    Désab: <?= date('d/m/Y', strtotime($subscriber['unsubscribed_at'])) ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <div class="space-y-1">
                                                <span class="engagement-badge <?= $engagement_class ?>">
                                                    <?= ucfirst($engagement_level) ?>
                                                </span>
                                                <div class="text-xs text-gray-600">
                                                    <div><i class="fas fa-envelope-open mr-1"></i><?= $subscriber['total_opens'] ?> ouv.</div>
                                                    <div><i class="fas fa-mouse-pointer mr-1"></i><?= $subscriber['total_clicks'] ?> clics</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <?php if ($subscriber['segments']): ?>
                                                <div class="flex flex-wrap justify-center gap-1">
                                                    <?php
                                                    $segments_list = explode(', ', $subscriber['segments']);
                                                    foreach (array_slice($segments_list, 0, 2) as $segment_name):
                                                    ?>
                                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-blue-100 text-blue-800">
                                                            <?= htmlspecialchars($segment_name) ?>
                                                        </span>
                                                    <?php endforeach; ?>
                                                    <?php if (count($segments_list) > 2): ?>
                                                        <span class="text-xs text-gray-500">+<?= count($segments_list) - 2 ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-gray-400 text-xs">Aucun</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            <div class="flex items-center justify-center space-x-2">
                                                <a href="mailto:<?= htmlspecialchars($subscriber['email']) ?>"
                                                   class="w-8 h-8 bg-blue-100 text-blue-600 rounded-lg flex items-center justify-center hover:bg-blue-200 transition-colors"
                                                   title="Envoyer un email">
                                                    <i class="fas fa-envelope text-sm"></i>
                                                </a>

                                                <button onclick="editSubscriber(<?= $subscriber['id'] ?>)"
                                                        class="w-8 h-8 bg-green-100 text-green-600 rounded-lg flex items-center justify-center hover:bg-green-200 transition-colors"
                                                        title="Éditer">
                                                    <i class="fas fa-edit text-sm"></i>
                                                </button>

                                                <button onclick="toggleStatus(<?= $subscriber['id'] ?>, '<?= $subscriber['statut'] ?>')"
                                                        class="w-8 h-8 bg-orange-100 text-orange-600 rounded-lg flex items-center justify-center hover:bg-orange-200 transition-colors"
                                                        title="Changer le statut">
                                                    <i class="fas fa-toggle-<?= $subscriber['statut'] === 'actif' ? 'on' : 'off' ?> text-sm"></i>
                                                </button>

                                                <button onclick="confirmDelete(<?= $subscriber['id'] ?>, '<?= htmlspecialchars($subscriber['email']) ?>')"
                                                        class="w-8 h-8 bg-red-100 text-red-600 rounded-lg flex items-center justify-center hover:bg-red-200 transition-colors"
                                                        title="Supprimer">
                                                    <i class="fas fa-trash-alt text-sm"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="px-6 py-12 text-center">
                                            <div class="flex flex-col items-center justify-center">
                                                <i class="fas fa-inbox text-6xl text-gray-300 mb-4"></i>
                                                <p class="text-lg text-gray-500 font-medium">Aucun abonné trouvé</p>
                                                <p class="text-sm text-gray-400">Ajustez vos filtres ou ajoutez de nouveaux abonnés</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="flex justify-center mt-8">
                        <nav class="flex space-x-2">
                            <?php
                            $current_params = $_GET;
                            unset($current_params['page']);
                            $base_url = '?' . http_build_query($current_params);
                            ?>

                            <?php if ($page > 1): ?>
                                <a href="<?= $base_url ?>&page=<?= $page - 1 ?>"
                                   class="px-4 py-2 text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            <?php endif; ?>

                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <a href="<?= $base_url ?>&page=<?= $i ?>"
                                   class="px-4 py-2 <?= $i === $page ? 'bg-blue-500 text-white' : 'text-gray-600 bg-white hover:bg-gray-50' ?> border border-gray-300 rounded-lg transition-colors">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <a href="<?= $base_url ?>&page=<?= $page + 1 ?>"
                                   class="px-4 py-2 text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </nav>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    // Gestion de la sélection groupée
    function toggleSelectAll() {
        const selectAll = document.getElementById('selectAll');
        const checkboxes = document.querySelectorAll('.subscriber-checkbox');

        checkboxes.forEach(checkbox => {
            checkbox.checked = selectAll.checked;
        });

        updateSelectedCount();
    }

    function updateSelectedCount() {
        const selected = document.querySelectorAll('.subscriber-checkbox:checked');
        document.getElementById('selectedCount').textContent = selected.length;

        const selectAll = document.getElementById('selectAll');
        const total = document.querySelectorAll('.subscriber-checkbox').length;
        selectAll.checked = selected.length === total && total > 0;
        selectAll.indeterminate = selected.length > 0 && selected.length < total;
    }

    function toggleBulkActions() {
        const form = document.getElementById('bulkActionsForm');
        const button = document.getElementById('bulkToggle');

        if (form.classList.contains('hidden')) {
            form.classList.remove('hidden');
            button.innerHTML = '<i class="fas fa-times mr-2"></i>Masquer Actions';
        } else {
            form.classList.add('hidden');
            button.innerHTML = '<i class="fas fa-tasks mr-2"></i>Actions Groupées';
        }
    }

    function confirmBulkAction() {
        const selected = document.querySelectorAll('.subscriber-checkbox:checked');
        const action = document.querySelector('select[name="bulk_action"]').value;

        if (selected.length === 0) {
            alert('Veuillez sélectionner au moins un abonné');
            return false;
        }

        if (!action) {
            alert('Veuillez choisir une action');
            return false;
        }

        const actionNames = {
            'activate': 'activer',
            'deactivate': 'désactiver',
            'delete': 'supprimer',
            'add_segment': 'ajouter au segment',
            'remove_segment': 'retirer du segment'
        };

        return confirm(`Êtes-vous sûr de vouloir ${actionNames[action]} ${selected.length} abonné(s) ?`);
    }

    // Gestion du tri
    function sortTable(column) {
        const currentSort = new URLSearchParams(window.location.search).get('sort');
        const currentDir = new URLSearchParams(window.location.search).get('dir');

        let newDir = 'desc';
        if (currentSort === column && currentDir === 'desc') {
            newDir = 'asc';
        }

        const url = new URL(window.location);
        url.searchParams.set('sort', column);
        url.searchParams.set('dir', newDir);
        window.location.href = url.toString();
    }

    // Actions sur les abonnés
    function confirmDelete(id, email) {
        if (confirm(`Êtes-vous sûr de vouloir supprimer l'email "${email}" de la newsletter ?`)) {
            window.location.href = 'admin_newsletter_delete.php?id=' + id;
        }
    }

    function toggleStatus(id, currentStatus) {
        const newStatus = currentStatus === 'actif' ? 'inactif' : 'actif';
        const action = newStatus === 'actif' ? 'réactiver' : 'désactiver';

        if (confirm(`Êtes-vous sûr de vouloir ${action} cet abonné ?`)) {
            window.location.href = `admin_newsletter_toggle.php?id=${id}&status=${newStatus}`;
        }
    }

    function editSubscriber(id) {
        window.location.href = `admin_newsletter_edit.php?id=${id}`;
    }

    function refreshPage() {
        window.location.reload();
    }

    // Gestion du select d'action groupée
    document.querySelector('select[name="bulk_action"]').addEventListener('change', function() {
        const segmentSelect = document.getElementById('segmentSelect');
        if (this.value === 'add_segment' || this.value === 'remove_segment') {
            segmentSelect.classList.remove('hidden');
        } else {
            segmentSelect.classList.add('hidden');
        }
    });

    // Initialiser le compteur de sélection
    document.addEventListener('DOMContentLoaded', function() {
        updateSelectedCount();

        document.querySelectorAll('.subscriber-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', updateSelectedCount);
        });
    });
    </script>
</body>
</html>