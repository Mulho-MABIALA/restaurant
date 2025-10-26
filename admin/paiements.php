<?php
/**
 * Dashboard de gestion des paiements
 * Affiche tous les paiements avec statistiques
 */

session_start();
require_once '../config.php';

// Vérifier l'authentification
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Filtres
$filterProvider = $_GET['provider'] ?? '';
$filterStatus = $_GET['status'] ?? '';
$filterDate = $_GET['date'] ?? '';

// Construction de la requête avec filtres
$whereConditions = [];
$params = [];

if ($filterProvider) {
    $whereConditions[] = "p.provider = ?";
    $params[] = $filterProvider;
}

if ($filterStatus) {
    $whereConditions[] = "p.statut = ?";
    $params[] = $filterStatus;
}

if ($filterDate) {
    $whereConditions[] = "DATE(p.created_at) = ?";
    $params[] = $filterDate;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Récupérer les paiements
$query = "
    SELECT
        p.*,
        c.nom_client,
        c.telephone,
        c.email,
        c.num_table
    FROM paiements p
    LEFT JOIN commandes c ON p.commande_id = c.id
    $whereClause
    ORDER BY p.created_at DESC
    LIMIT $perPage OFFSET $offset
";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$paiements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Compter le total pour la pagination
$countQuery = "SELECT COUNT(*) FROM paiements p $whereClause";
$countStmt = $conn->prepare($countQuery);
$countStmt->execute($params);
$totalPaiements = $countStmt->fetchColumn();
$totalPages = ceil($totalPaiements / $perPage);

// Statistiques du jour
$statsQuery = "
    SELECT
        COUNT(*) as total_count,
        SUM(CASE WHEN statut = 'success' THEN 1 ELSE 0 END) as success_count,
        SUM(CASE WHEN statut = 'failed' THEN 1 ELSE 0 END) as failed_count,
        SUM(CASE WHEN statut = 'pending' THEN 1 ELSE 0 END) as pending_count,
        SUM(montant) as total_amount,
        SUM(CASE WHEN statut = 'success' THEN montant ELSE 0 END) as success_amount
    FROM paiements
    WHERE DATE(created_at) = CURDATE()
";
$statsStmt = $conn->query($statsQuery);
$todayStats = $statsStmt->fetch(PDO::FETCH_ASSOC);

// Statistiques par provider (aujourd'hui)
$providerStatsQuery = "
    SELECT
        provider,
        COUNT(*) as count,
        SUM(montant) as total,
        SUM(CASE WHEN statut = 'success' THEN montant ELSE 0 END) as success_total
    FROM paiements
    WHERE DATE(created_at) = CURDATE()
    GROUP BY provider
";
$providerStats = $conn->query($providerStatsQuery)->fetchAll(PDO::FETCH_ASSOC);

// Taux de réussite
$successRate = $todayStats['total_count'] > 0
    ? round(($todayStats['success_count'] / $todayStats['total_count']) * 100, 1)
    : 0;

// Fonction helper pour formater les montants
function formatCurrency($amount) {
    return number_format($amount ?? 0, 0, ',', ' ') . ' FCFA';
}

// Fonction helper pour les badges de statut
function getStatusBadge($status) {
    $badges = [
        'success' => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">✓ Réussi</span>',
        'pending' => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">⏳ En attente</span>',
        'processing' => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">⚡ En cours</span>',
        'failed' => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">✗ Échoué</span>',
        'refunded' => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-purple-100 text-purple-800">↩ Remboursé</span>',
        'cancelled' => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">⊘ Annulé</span>'
    ];
    return $badges[$status] ?? '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">' . $status . '</span>';
}

// Fonction helper pour les badges de provider
function getProviderBadge($provider) {
    $badges = [
        'orange_money' => '<span class="px-2 py-1 text-xs font-semibold rounded bg-orange-100 text-orange-800">🍊 Orange Money</span>',
        'wave' => '<span class="px-2 py-1 text-xs font-semibold rounded bg-blue-100 text-blue-800">📱 Wave</span>',
        'paydunya' => '<span class="px-2 py-1 text-xs font-semibold rounded bg-purple-100 text-purple-800">💳 Paydunya</span>',
        'cash' => '<span class="px-2 py-1 text-xs font-semibold rounded bg-gray-100 text-gray-800">💵 Espèces</span>'
    ];
    return $badges[$provider] ?? '<span class="px-2 py-1 text-xs font-semibold rounded bg-gray-100 text-gray-800">' . $provider . '</span>';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Paiements - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100">
    <div class="flex h-screen overflow-hidden">
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 overflow-x-hidden overflow-y-auto">
            <div class="container-fluid px-4 py-6">
    <!-- Header -->
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-800 mb-2">💳 Gestion des Paiements</h1>
        <p class="text-gray-600">Suivez et gérez tous les paiements en ligne</p>
    </div>

    <!-- Statistiques du jour -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <!-- Total aujourd'hui -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-medium">Aujourd'hui</p>
                    <p class="text-2xl font-bold text-gray-800 mt-1">
                        <?= formatCurrency($todayStats['total_amount']) ?>
                    </p>
                    <p class="text-xs text-gray-500 mt-1"><?= $todayStats['total_count'] ?> transactions</p>
                </div>
                <div class="bg-blue-100 rounded-full p-3">
                    <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Paiements réussis -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-medium">Réussis</p>
                    <p class="text-2xl font-bold text-green-600 mt-1">
                        <?= formatCurrency($todayStats['success_amount']) ?>
                    </p>
                    <p class="text-xs text-gray-500 mt-1"><?= $todayStats['success_count'] ?> paiements</p>
                </div>
                <div class="bg-green-100 rounded-full p-3">
                    <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Taux de réussite -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-medium">Taux de réussite</p>
                    <p class="text-2xl font-bold text-gray-800 mt-1"><?= $successRate ?>%</p>
                    <p class="text-xs text-gray-500 mt-1">
                        <?= $todayStats['success_count'] ?> / <?= $todayStats['total_count'] ?>
                    </p>
                </div>
                <div class="bg-purple-100 rounded-full p-3">
                    <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                </div>
            </div>
        </div>

        <!-- En attente -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm font-medium">En attente</p>
                    <p class="text-2xl font-bold text-yellow-600 mt-1"><?= $todayStats['pending_count'] ?></p>
                    <p class="text-xs text-gray-500 mt-1">À vérifier</p>
                </div>
                <div class="bg-yellow-100 rounded-full p-3">
                    <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats par provider -->
    <?php if (!empty($providerStats)): ?>
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Répartition par méthode (aujourd'hui)</h3>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <?php foreach ($providerStats as $pStat): ?>
            <div class="border rounded-lg p-4">
                <?= getProviderBadge($pStat['provider']) ?>
                <p class="text-2xl font-bold text-gray-800 mt-2"><?= formatCurrency($pStat['success_total']) ?></p>
                <p class="text-sm text-gray-500"><?= $pStat['count'] ?> transactions</p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Filtres -->
    <div class="bg-white rounded-lg shadow p-4 mb-6">
        <form method="GET" class="flex flex-wrap gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Provider</label>
                <select name="provider" class="border rounded px-3 py-2">
                    <option value="">Tous</option>
                    <option value="orange_money" <?= $filterProvider === 'orange_money' ? 'selected' : '' ?>>Orange Money</option>
                    <option value="wave" <?= $filterProvider === 'wave' ? 'selected' : '' ?>>Wave</option>
                    <option value="paydunya" <?= $filterProvider === 'paydunya' ? 'selected' : '' ?>>Paydunya</option>
                    <option value="cash" <?= $filterProvider === 'cash' ? 'selected' : '' ?>>Espèces</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Statut</label>
                <select name="status" class="border rounded px-3 py-2">
                    <option value="">Tous</option>
                    <option value="success" <?= $filterStatus === 'success' ? 'selected' : '' ?>>Réussi</option>
                    <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>>En attente</option>
                    <option value="failed" <?= $filterStatus === 'failed' ? 'selected' : '' ?>>Échoué</option>
                    <option value="refunded" <?= $filterStatus === 'refunded' ? 'selected' : '' ?>>Remboursé</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                <input type="date" name="date" value="<?= htmlspecialchars($filterDate) ?>" class="border rounded px-3 py-2">
            </div>

            <div class="flex items-end gap-2">
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                    Filtrer
                </button>
                <a href="paiements.php" class="bg-gray-200 text-gray-700 px-4 py-2 rounded hover:bg-gray-300">
                    Réinitialiser
                </a>
            </div>
        </form>
    </div>

    <!-- Liste des paiements -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Montant</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Provider</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($paiements)): ?>
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                            </svg>
                            <p class="mt-2 text-sm font-medium">Aucun paiement trouvé</p>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($paiements as $p): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                #<?= $p['id'] ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($p['nom_client'] ?? 'N/A') ?></div>
                                <div class="text-sm text-gray-500"><?= htmlspecialchars($p['telephone'] ?? '') ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-bold text-gray-900"><?= formatCurrency($p['montant']) ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?= getProviderBadge($p['provider']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?= getStatusBadge($p['statut']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= date('d/m/Y H:i', strtotime($p['created_at'])) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <a href="voir_paiement.php?id=<?= $p['id'] ?>" class="text-blue-600 hover:text-blue-900 mr-3">Détails</a>
                                <?php if ($p['statut'] === 'success' && $p['provider'] === 'wave'): ?>
                                <button onclick="confirmRefund(<?= $p['id'] ?>)" class="text-red-600 hover:text-red-900">Rembourser</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
            <div class="flex-1 flex justify-between sm:hidden">
                <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>&<?= http_build_query($_GET) ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">Précédent</a>
                <?php endif; ?>
                <?php if ($page < $totalPages): ?>
                <a href="?page=<?= $page + 1 ?>&<?= http_build_query($_GET) ?>" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">Suivant</a>
                <?php endif; ?>
            </div>
            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm text-gray-700">
                        Affichage <span class="font-medium"><?= $offset + 1 ?></span> à
                        <span class="font-medium"><?= min($offset + $perPage, $totalPaiements) ?></span> sur
                        <span class="font-medium"><?= $totalPaiements ?></span> résultats
                    </p>
                </div>
                <div>
                    <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <?php if ($i === $page): ?>
                                <span class="relative inline-flex items-center px-4 py-2 border border-blue-500 bg-blue-50 text-sm font-medium text-blue-600"><?= $i ?></span>
                            <?php else: ?>
                                <a href="?page=<?= $i ?>&<?= http_build_query($_GET) ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50"><?= $i ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </nav>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
            </div>
        </div>
    </div>

<script>
function confirmRefund(paymentId) {
    if (confirm('Êtes-vous sûr de vouloir rembourser ce paiement?')) {
        // TODO: Implémenter l'endpoint de remboursement
        window.location.href = 'refund_payment.php?id=' + paymentId;
    }
}
</script>

</body>
</html>
