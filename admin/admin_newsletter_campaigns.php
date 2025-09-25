<?php
// admin_newsletter_campaigns.php
require_once '../config.php';
session_start();

// Rediriger si l'admin n'est pas connecté
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Actions
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $campaign_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    switch ($action) {
        case 'delete':
            try {
                // Supprimer de la file d'attente
                $conn->prepare("DELETE FROM newsletter_queue WHERE campaign_id = ?")->execute([$campaign_id]);
                
                // Supprimer le tracking
                $conn->prepare("DELETE FROM newsletter_tracking WHERE campaign_id = ?")->execute([$campaign_id]);
                
                // Supprimer la campagne
                $conn->prepare("DELETE FROM newsletter_campaigns WHERE id = ?")->execute([$campaign_id]);
                
                $_SESSION['success_message'] = "Campagne supprimée avec succès";
            } catch (PDOException $e) {
                $_SESSION['error_message'] = "Erreur lors de la suppression";
            }
            break;
            
        case 'pause':
            try {
                $conn->prepare("UPDATE newsletter_campaigns SET status = 'paused' WHERE id = ?")->execute([$campaign_id]);
                $_SESSION['success_message'] = "Campagne mise en pause";
            } catch (PDOException $e) {
                $_SESSION['error_message'] = "Erreur lors de la mise en pause";
            }
            break;
            
        case 'resume':
            try {
                $conn->prepare("UPDATE newsletter_campaigns SET status = 'sending' WHERE id = ?")->execute([$campaign_id]);
                $_SESSION['success_message'] = "Campagne relancée";
            } catch (PDOException $e) {
                $_SESSION['error_message'] = "Erreur lors de la relance";
            }
            break;
            
        case 'duplicate':
            try {
                // Récupérer la campagne originale
                $stmt = $conn->prepare("SELECT * FROM newsletter_campaigns WHERE id = ?");
                $stmt->execute([$campaign_id]);
                $original = $stmt->fetch();
                
                if ($original) {
                    // Créer la copie
                    $new_name = $original['name'] . ' (Copie)';
                    $stmt = $conn->prepare("INSERT INTO newsletter_campaigns (name, subject, content, template_id, status) VALUES (?, ?, ?, ?, 'draft')");
                    $stmt->execute([$new_name, $original['subject'], $original['content'], $original['template_id']]);
                    
                    $_SESSION['success_message'] = "Campagne dupliquée avec succès";
                }
            } catch (PDOException $e) {
                $_SESSION['error_message'] = "Erreur lors de la duplication";
            }
            break;
    }
    
    header('Location: admin_newsletter_campaigns.php');
    exit;
}

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Filtres
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Construction de la requête
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(name LIKE ? OR subject LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($status_filter)) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

try {
    // Compter le total
    $count_query = "SELECT COUNT(*) FROM newsletter_campaigns $where_clause";
    $count_stmt = $conn->prepare($count_query);
    $count_stmt->execute($params);
    $total = $count_stmt->fetchColumn();
    
    // Récupérer les campagnes
    $query = "SELECT nc.*, nt.name as template_name,
                     (SELECT COUNT(*) FROM newsletter_queue WHERE campaign_id = nc.id AND status = 'pending') as pending_count,
                     (SELECT COUNT(*) FROM newsletter_queue WHERE campaign_id = nc.id AND status = 'sent') as sent_count,
                     (SELECT COUNT(*) FROM newsletter_tracking WHERE campaign_id = nc.id AND opened_at IS NOT NULL) as opened_count,
                     (SELECT COUNT(*) FROM newsletter_tracking WHERE campaign_id = nc.id AND clicked_at IS NOT NULL) as clicked_count
              FROM newsletter_campaigns nc
              LEFT JOIN newsletter_templates nt ON nc.template_id = nt.id
              $where_clause 
              ORDER BY nc.created_at DESC 
              LIMIT $limit OFFSET $offset";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $campaigns = $stmt->fetchAll();
    
    // Statistiques générales - VERSION CORRIGÉE
    $stats_query = "
        SELECT 
            COUNT(*) as total_campaigns,
            COUNT(CASE WHEN status = 'draft' THEN 1 END) as draft_count,
            COUNT(CASE WHEN status = 'sending' THEN 1 END) as sending_count,
            COUNT(CASE WHEN status = 'sent' THEN 1 END) as sent_count,
            COUNT(CASE WHEN status = 'scheduled' THEN 1 END) as scheduled_count,
            COALESCE(SUM(total_recipients), 0) as total_recipients_all,
            COALESCE(SUM(sent_count), 0) as total_sent_all,
            COALESCE(SUM(opened_count), 0) as total_opened_all,
            COALESCE(SUM(clicked_count), 0) as total_clicked_all
        FROM newsletter_campaigns
    ";
    
    $stats_result = $conn->query($stats_query)->fetch();
    
    // S'assurer que toutes les valeurs ne sont pas null
    $stats = [
        'total_campaigns' => (int)($stats_result['total_campaigns'] ?? 0),
        'draft_count' => (int)($stats_result['draft_count'] ?? 0),
        'sending_count' => (int)($stats_result['sending_count'] ?? 0),
        'sent_count' => (int)($stats_result['sent_count'] ?? 0),
        'scheduled_count' => (int)($stats_result['scheduled_count'] ?? 0),
        'total_recipients_all' => (int)($stats_result['total_recipients_all'] ?? 0),
        'total_sent_all' => (int)($stats_result['total_sent_all'] ?? 0),
        'total_opened_all' => (int)($stats_result['total_opened_all'] ?? 0),
        'total_clicked_all' => (int)($stats_result['total_clicked_all'] ?? 0)
    ];
    
} catch (PDOException $e) {
    error_log("Erreur campaigns: " . $e->getMessage());
    $campaigns = [];
    $total = 0;
    $stats = [
        'total_campaigns' => 0, 'draft_count' => 0, 'sending_count' => 0, 
        'sent_count' => 0, 'scheduled_count' => 0, 'total_recipients_all' => 0,
        'total_sent_all' => 0, 'total_opened_all' => 0, 'total_clicked_all' => 0
    ];
}

$total_pages = ceil($total / $limit);

// Calculer les taux moyens - VERSION CORRIGÉE
$total_envoyes = (int)$stats['total_sent_all'];
$total_ouverts = (int)$stats['total_opened_all'];
$total_cliques = (int)$stats['total_clicked_all'];

$avg_open_rate = ($total_envoyes > 0) ? ($total_ouverts / $total_envoyes) * 100 : 0;
$avg_click_rate = ($total_envoyes > 0) ? ($total_cliques / $total_envoyes) * 100 : 0;
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Campagnes</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .status-badge {
            @apply px-3 py-1 rounded-full text-xs font-medium;
        }
        .status-draft { @apply bg-gray-100 text-gray-800; }
        .status-scheduled { @apply bg-blue-100 text-blue-800; }
        .status-sending { @apply bg-yellow-100 text-yellow-800; }
        .status-sent { @apply bg-green-100 text-green-800; }
        .status-paused { @apply bg-orange-100 text-orange-800; }
        .status-cancelled { @apply bg-red-100 text-red-800; }
        
        .progress-bar {
            background: linear-gradient(90deg, #4ade80 0%, #22c55e 100%);
            transition: width 0.3s ease;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="container mx-auto p-6">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800 mb-2">
                        <i class="fas fa-paper-plane text-blue-600 mr-3"></i>
                        Gestion des Campagnes
                    </h1>
                    <p class="text-gray-600">Suivez et gérez vos campagnes email en temps réel</p>
                </div>
                <div class="flex gap-3">
                    <a href="admin_newsletter.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition-colors">
                        <i class="fas fa-users mr-2"></i>Abonnés
                    </a>
                    <a href="admin_newsletter_analytics.php" class="bg-purple-500 hover:bg-purple-600 text-white px-4 py-2 rounded-lg transition-colors">
                        <i class="fas fa-chart-bar mr-2"></i>Analytics
                    </a>
                    <a href="admin_newsletter_compose.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors">
                        <i class="fas fa-plus mr-2"></i>Nouvelle Campagne
                    </a>
                </div>
            </div>
        </div>

        <!-- Messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <i class="fas fa-check-circle mr-2"></i>
                <?= htmlspecialchars($_SESSION['success_message']) ?>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?= htmlspecialchars($_SESSION['error_message']) ?>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <!-- Statistiques -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white border-2 border-blue-200 rounded-lg p-6 hover:border-blue-400 transition-colors">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm font-medium">Total Campagnes</p>
                        <p class="text-2xl font-bold text-gray-900"><?= number_format((int)$stats['total_campaigns']) ?></p>
                    </div>
                    <i class="fas fa-paper-plane text-3xl text-blue-500"></i>
                </div>
            </div>

            <div class="bg-white border-2 border-green-200 rounded-lg p-6 hover:border-green-400 transition-colors">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm font-medium">Emails Envoyés</p>
                        <p class="text-2xl font-bold text-gray-900"><?= number_format((int)$stats['total_sent_all']) ?></p>
                    </div>
                    <i class="fas fa-envelope text-3xl text-green-500"></i>
                </div>
            </div>

            <div class="bg-white border-2 border-orange-200 rounded-lg p-6 hover:border-orange-400 transition-colors">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm font-medium">Taux d'Ouverture</p>
                        <p class="text-2xl font-bold text-gray-900"><?= number_format(floatval($avg_open_rate), 1) ?>%</p>
                    </div>
                    <i class="fas fa-envelope-open text-3xl text-orange-500"></i>
                </div>
            </div>

            <div class="bg-white border-2 border-purple-200 rounded-lg p-6 hover:border-purple-400 transition-colors">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm font-medium">Taux de Clic</p>
                        <p class="text-2xl font-bold text-gray-900"><?= number_format(floatval($avg_click_rate), 1) ?>%</p>
                    </div>
                    <i class="fas fa-mouse-pointer text-3xl text-purple-500"></i>
                </div>
            </div>
        </div>

        <!-- Filtres -->
        <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
            <form method="GET" class="flex flex-wrap items-center gap-4">
                <div class="flex-1 min-w-[200px]">
                    <input type="text" 
                           name="search" 
                           value="<?= htmlspecialchars($search) ?>"
                           placeholder="Rechercher par nom ou sujet..." 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                
                <div>
                    <select name="status" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">Tous les statuts</option>
                        <option value="draft" <?= $status_filter === 'draft' ? 'selected' : '' ?>>Brouillons</option>
                        <option value="scheduled" <?= $status_filter === 'scheduled' ? 'selected' : '' ?>>Programmées</option>
                        <option value="sending" <?= $status_filter === 'sending' ? 'selected' : '' ?>>En cours</option>
                        <option value="sent" <?= $status_filter === 'sent' ? 'selected' : '' ?>>Envoyées</option>
                        <option value="paused" <?= $status_filter === 'paused' ? 'selected' : '' ?>>En pause</option>
                    </select>
                </div>
                
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors">
                    <i class="fas fa-search mr-2"></i>Filtrer
                </button>
                
                <a href="?" class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors">
                    <i class="fas fa-times mr-2"></i>Reset
                </a>
            </form>
        </div>

        <!-- Liste des campagnes -->
        <div class="bg-white shadow-lg rounded-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full table-auto">
                    <thead class="bg-gray-50">
                        <tr class="text-gray-600 uppercase text-sm leading-normal">
                            <th class="py-4 px-6 text-left">Campagne</th>
                            <th class="py-4 px-6 text-center">Statut</th>
                            <th class="py-4 px-6 text-center">Progression</th>
                            <th class="py-4 px-6 text-center">Performance</th>
                            <th class="py-4 px-6 text-center">Date</th>
                            <th class="py-4 px-6 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-600 text-sm font-light">
                        <?php if (count($campaigns) > 0): ?>
                            <?php foreach ($campaigns as $campaign): ?>
                            <?php
                            // Calculer les pourcentages - VERSION CORRIGÉE
                            $total_destinataires = (int)($campaign['total_recipients'] ?? 0);
                            $nombre_envoyes = (int)($campaign['sent_count'] ?? 0);
                            $nombre_ouverts = (int)($campaign['opened_count'] ?? 0);
                            $nombre_cliques = (int)($campaign['clicked_count'] ?? 0);
                            
                            $progress = ($total_destinataires > 0) ? ($nombre_envoyes / $total_destinataires) * 100 : 0;
                            $open_rate = ($nombre_envoyes > 0) ? ($nombre_ouverts / $nombre_envoyes) * 100 : 0;
                            $click_rate = ($nombre_envoyes > 0) ? ($nombre_cliques / $nombre_envoyes) * 100 : 0;
                            ?>
                            <tr class="border-b border-gray-200 hover:bg-gray-50 transition-colors">
                                <td class="py-4 px-6 text-left">
                                    <div>
                                        <div class="font-medium text-gray-900"><?= htmlspecialchars($campaign['name']) ?></div>
                                        <div class="text-gray-500 text-xs"><?= htmlspecialchars($campaign['subject']) ?></div>
                                        <?php if ($campaign['template_name']): ?>
                                            <div class="text-blue-500 text-xs mt-1">
                                                <i class="fas fa-palette mr-1"></i><?= htmlspecialchars($campaign['template_name']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="py-4 px-6 text-center">
                                    <span class="status-badge status-<?= $campaign['status'] ?>">
                                        <?php
                                        $status_labels = [
                                            'draft' => 'Brouillon',
                                            'scheduled' => 'Programmée',
                                            'sending' => 'En cours',
                                            'sent' => 'Envoyée',
                                            'paused' => 'En pause',
                                            'cancelled' => 'Annulée'
                                        ];
                                        echo $status_labels[$campaign['status']] ?? $campaign['status'];
                                        ?>
                                    </span>
                                    <?php if ($campaign['scheduled_at'] && $campaign['status'] === 'scheduled'): ?>
                                        <div class="text-xs text-gray-500 mt-1">
                                            <?= date('d/m/Y H:i', strtotime($campaign['scheduled_at'])) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="py-4 px-6 text-center">
                                    <div class="w-full bg-gray-200 rounded-full h-2 mb-1">
                                        <div class="progress-bar h-2 rounded-full" style="width: <?= $progress ?>%"></div>
                                    </div>
                                    <div class="text-xs text-gray-600">
                                        <?= number_format($nombre_envoyes) ?> / <?= number_format($total_destinataires) ?>
                                        (<?= number_format($progress, 1) ?>%)
                                    </div>
                                    <?php if (($campaign['pending_count'] ?? 0) > 0): ?>
                                        <div class="text-xs text-orange-600 mt-1">
                                            <i class="fas fa-clock mr-1"></i><?= number_format((int)$campaign['pending_count']) ?> en attente
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="py-4 px-6 text-center">
                                    <?php if ($nombre_envoyes > 0): ?>
                                        <div class="text-xs space-y-1">
                                            <div class="flex items-center justify-center">
                                                <i class="fas fa-envelope-open text-green-500 mr-1"></i>
                                                <span><?= number_format($open_rate, 1) ?>%</span>
                                            </div>
                                            <div class="flex items-center justify-center">
                                                <i class="fas fa-mouse-pointer text-blue-500 mr-1"></i>
                                                <span><?= number_format($click_rate, 1) ?>%</span>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-gray-400 text-xs">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-4 px-6 text-center">
                                    <div class="text-gray-900 text-xs">
                                        <?= date('d/m/Y', strtotime($campaign['created_at'])) ?>
                                    </div>
                                    <div class="text-gray-500 text-xs">
                                        <?= date('H:i', strtotime($campaign['created_at'])) ?>
                                    </div>
                                    <?php if ($campaign['sent_at']): ?>
                                        <div class="text-green-600 text-xs mt-1">
                                            Envoyée: <?= date('d/m H:i', strtotime($campaign['sent_at'])) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="py-4 px-6 text-center">
                                    <div class="flex items-center justify-center space-x-1">
                                        <!-- Voir les détails -->
                                        <a href="admin_newsletter_campaign_details.php?id=<?= $campaign['id'] ?>" 
                                           class="w-8 h-8 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center hover:bg-blue-200 transition-colors"
                                           title="Voir les détails">
                                            <i class="fas fa-eye text-sm"></i>
                                        </a>
                                        
                                        <?php if ($campaign['status'] === 'draft'): ?>
                                            <!-- Éditer -->
                                            <a href="admin_newsletter_compose.php?edit=<?= $campaign['id'] ?>" 
                                               class="w-8 h-8 bg-orange-100 text-orange-600 rounded-full flex items-center justify-center hover:bg-orange-200 transition-colors"
                                               title="Éditer">
                                                <i class="fas fa-edit text-sm"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($campaign['status'] === 'sending'): ?>
                                            <!-- Pause -->
                                            <a href="?action=pause&id=<?= $campaign['id'] ?>" 
                                               class="w-8 h-8 bg-yellow-100 text-yellow-600 rounded-full flex items-center justify-center hover:bg-yellow-200 transition-colors"
                                               title="Mettre en pause"
                                               onclick="return confirm('Mettre en pause cette campagne ?')">
                                                <i class="fas fa-pause text-sm"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($campaign['status'] === 'paused'): ?>
                                            <!-- Reprendre -->
                                            <a href="?action=resume&id=<?= $campaign['id'] ?>" 
                                               class="w-8 h-8 bg-green-100 text-green-600 rounded-full flex items-center justify-center hover:bg-green-200 transition-colors"
                                               title="Reprendre l'envoi"
                                               onclick="return confirm('Reprendre cette campagne ?')">
                                                <i class="fas fa-play text-sm"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <!-- Dupliquer -->
                                        <a href="?action=duplicate&id=<?= $campaign['id'] ?>" 
                                           class="w-8 h-8 bg-purple-100 text-purple-600 rounded-full flex items-center justify-center hover:bg-purple-200 transition-colors"
                                           title="Dupliquer"
                                           onclick="return confirm('Dupliquer cette campagne ?')">
                                            <i class="fas fa-copy text-sm"></i>
                                        </a>
                                        
                                        <!-- Supprimer -->
                                        <a href="?action=delete&id=<?= $campaign['id'] ?>" 
                                           class="w-8 h-8 bg-red-100 text-red-600 rounded-full flex items-center justify-center hover:bg-red-200 transition-colors"
                                           title="Supprimer"
                                           onclick="return confirm('Supprimer définitivement cette campagne ?')">
                                            <i class="fas fa-trash-alt text-sm"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="py-12 px-6 text-center text-gray-500">
                                    <i class="fas fa-paper-plane text-4xl mb-4 text-gray-300"></i>
                                    <p class="text-lg">Aucune campagne trouvée</p>
                                    <p class="text-sm">Créez votre première campagne pour commencer</p>
                                    <a href="admin_newsletter_compose.php" class="inline-block mt-4 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors">
                                        <i class="fas fa-plus mr-2"></i>Créer une campagne
                                    </a>
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
                    $current_url = '?' . http_build_query(array_merge($_GET, ['page' => '']));
                    $current_url = rtrim($current_url, '=');
                    ?>
                    
                    <?php if ($page > 1): ?>
                        <a href="<?= $current_url ?>=<?= $page - 1 ?>" 
                           class="px-3 py-2 text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <a href="<?= $current_url ?>=<?= $i ?>" 
                           class="px-3 py-2 <?= $i === $page ? 'bg-blue-500 text-white' : 'text-gray-500 bg-white hover:bg-gray-50' ?> border border-gray-300 rounded-md">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="<?= $current_url ?>=<?= $page + 1 ?>" 
                           class="px-3 py-2 text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </nav>
            </div>
        <?php endif; ?>

        <!-- Campagnes en cours -->
        <?php
        try {
            $active_campaigns = $conn->query("
                SELECT id, name, status, 
                       (SELECT COUNT(*) FROM newsletter_queue WHERE campaign_id = id AND status = 'pending') as pending,
                       (SELECT COUNT(*) FROM newsletter_queue WHERE campaign_id = id AND status = 'sent') as sent,
                       COALESCE(total_recipients, 0) as total_recipients
                FROM newsletter_campaigns 
                WHERE status IN ('sending', 'scheduled') 
                ORDER BY created_at DESC 
                LIMIT 3
            ")->fetchAll();
        } catch (PDOException $e) {
            $active_campaigns = [];
        }
        ?>

        <?php if (count($active_campaigns) > 0): ?>
            <div class="mt-8 bg-white rounded-lg shadow-sm p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">
                    <i class="fas fa-clock text-orange-500 mr-2"></i>
                    Campagnes en cours
                </h3>
                
                <div class="space-y-4">
                    <?php foreach ($active_campaigns as $active): ?>
                        <?php
                        $active_sent = (int)($active['sent'] ?? 0);
                        $active_total = (int)($active['total_recipients'] ?? 0);
                        $active_pending = (int)($active['pending'] ?? 0);
                        $active_progress = ($active_total > 0) ? ($active_sent / $active_total) * 100 : 0;
                        ?>
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                            <div class="flex-1">
                                <div class="font-medium text-gray-900"><?= htmlspecialchars($active['name']) ?></div>
                                <div class="text-sm text-gray-600">
                                    <?= number_format($active_sent) ?> / <?= number_format($active_total) ?> envoyés
                                    <?php if ($active_pending > 0): ?>
                                        (<?= number_format($active_pending) ?> en attente)
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="flex items-center space-x-2">
                                <div class="w-32 bg-gray-200 rounded-full h-2">
                                    <div class="progress-bar h-2 rounded-full" style="width: <?= $active_progress ?>%"></div>
                                </div>
                                <span class="text-sm font-medium text-gray-700"><?= number_format($active_progress, 1) ?>%</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
    // Auto-refresh pour les campagnes en cours
    function autoRefresh() {
        const activeCampaigns = <?= json_encode(array_column($active_campaigns, 'id')) ?>;
        if (activeCampaigns.length > 0) {
            // Rafraîchir la page toutes les 30 secondes si il y a des campagnes actives
            setTimeout(() => {
                window.location.reload();
            }, 30000);
        }
    }

    // Démarrer l'auto-refresh
    autoRefresh();

    // Confirmer les actions sensibles
    document.querySelectorAll('a[href*="action=delete"]').forEach(link => {
        link.addEventListener('click', function(e) {
            if (!confirm('Êtes-vous sûr de vouloir supprimer cette campagne ? Cette action est irréversible.')) {
                e.preventDefault();
            }
        });
    });

    // Animation des barres de progression
    document.addEventListener('DOMContentLoaded', function() {
        const progressBars = document.querySelectorAll('.progress-bar');
        progressBars.forEach(bar => {
            const width = bar.style.width;
            bar.style.width = '0%';
            setTimeout(() => {
                bar.style.width = width;
            }, 100);
        });
    });

    // Filtrage en temps réel
    const searchInput = document.querySelector('input[name="search"]');
    let searchTimeout;
    
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            // Optionnel: filtrage AJAX en temps réel
            // pour une meilleure UX sur de grandes listes
        }, 500);
    });

    // Raccourcis clavier
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + N pour nouvelle campagne
        if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
            e.preventDefault();
            window.location.href = 'admin_newsletter_compose.php';
        }
        
        // R pour rafraîchir
        if (e.key === 'r' && !e.ctrlKey && !e.metaKey) {
            window.location.reload();
        }
    });

    // Tooltip simple pour les icônes
    document.querySelectorAll('[title]').forEach(element => {
        element.addEventListener('mouseenter', function() {
            const tooltip = document.createElement('div');
            tooltip.className = 'fixed bg-gray-800 text-white text-xs px-2 py-1 rounded pointer-events-none z-50';
            tooltip.textContent = this.getAttribute('title');
            document.body.appendChild(tooltip);
            
            const rect = this.getBoundingClientRect();
            tooltip.style.left = rect.left + rect.width / 2 - tooltip.offsetWidth / 2 + 'px';
            tooltip.style.top = rect.top - tooltip.offsetHeight - 5 + 'px';
            
            this._tooltip = tooltip;
        });
        
        element.addEventListener('mouseleave', function() {
            if (this._tooltip) {
                document.body.removeChild(this._tooltip);
                this._tooltip = null;
            }
        });
    });
    </script>
</body>
</html>