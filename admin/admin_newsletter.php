<?php
/**
 * Gestion simplifiée de la Newsletter
 */
session_start();
require_once '../config.php';

// Vérifier si admin connecté
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$message = '';
$error = '';

// ====================================
// ACTIONS
// ====================================

// Supprimer un abonné
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    try {
        $stmt = $conn->prepare("DELETE FROM newsletter WHERE id = ?");
        $stmt->execute([(int)$_GET['id']]);
        $message = "Abonné supprimé avec succès";
    } catch (Exception $e) {
        $error = "Erreur: " . $e->getMessage();
    }
}

// Changer le statut d'un abonné
if (isset($_GET['action']) && $_GET['action'] === 'toggle' && isset($_GET['id'])) {
    try {
        $stmt = $conn->prepare("UPDATE newsletter SET statut = IF(statut = 'actif', 'inactif', 'actif') WHERE id = ?");
        $stmt->execute([(int)$_GET['id']]);
        $message = "Statut modifié avec succès";
    } catch (Exception $e) {
        $error = "Erreur: " . $e->getMessage();
    }
}

// Ajouter un abonné manuellement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_subscriber'])) {
    try {
        $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');

        if (!$email) {
            throw new Exception('Email invalide');
        }

        // Vérifier si existe déjà
        $stmt = $conn->prepare("SELECT id FROM newsletter WHERE email = ?");
        $stmt->execute([$email]);

        if ($stmt->rowCount() > 0) {
            throw new Exception('Cet email est déjà inscrit');
        }

        // Insérer
        $stmt = $conn->prepare("INSERT INTO newsletter (email, first_name, last_name, source, statut, date_inscription) VALUES (?, ?, ?, 'admin', 'actif', NOW())");
        $stmt->execute([$email, $first_name, $last_name]);

        $message = "Abonné ajouté avec succès";
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Export CSV
if (isset($_GET['action']) && $_GET['action'] === 'export') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=newsletter_' . date('Y-m-d') . '.csv');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Email', 'Prénom', 'Nom', 'Statut', 'Source', 'Date inscription']);

    $stmt = $conn->query("SELECT email, first_name, last_name, statut, source, DATE_FORMAT(date_inscription, '%d/%m/%Y %H:%i') as date_fmt FROM newsletter ORDER BY date_inscription DESC");

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, $row);
    }

    fclose($output);
    exit;
}

// ====================================
// RÉCUPÉRATION DES DONNÉES
// ====================================

// Filtres
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$source = $_GET['source'] ?? '';

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Construction de la requête
$where = [];
$params = [];

if (!empty($search)) {
    $where[] = "(email LIKE ? OR first_name LIKE ? OR last_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($status)) {
    $where[] = "statut = ?";
    $params[] = $status;
}

if (!empty($source)) {
    $where[] = "source = ?";
    $params[] = $source;
}

$where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Compter le total
$count_sql = "SELECT COUNT(*) FROM newsletter $where_sql";
$stmt = $conn->prepare($count_sql);
$stmt->execute($params);
$total = $stmt->fetchColumn();

// Récupérer les abonnés
$sql = "SELECT id, email, first_name, last_name, statut, source,
        DATE_FORMAT(date_inscription, '%d/%m/%Y %H:%i') as date_fmt,
        total_opens, total_clicks
        FROM newsletter
        $where_sql
        ORDER BY date_inscription DESC
        LIMIT $per_page OFFSET $offset";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$subscribers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiques
$stats = [
    'total' => $conn->query("SELECT COUNT(*) FROM newsletter")->fetchColumn(),
    'actif' => $conn->query("SELECT COUNT(*) FROM newsletter WHERE statut = 'actif'")->fetchColumn(),
    'inactif' => $conn->query("SELECT COUNT(*) FROM newsletter WHERE statut = 'inactif'")->fetchColumn(),
];

// Sources disponibles
$sources = $conn->query("SELECT DISTINCT source FROM newsletter WHERE source IS NOT NULL ORDER BY source")->fetchAll(PDO::FETCH_COLUMN);

$total_pages = ceil($total / $per_page);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion Newsletter</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <!-- Header -->
    <div class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-4">
                    <a href="dashboard.php" class="text-gray-600 hover:text-gray-900">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <h1 class="text-2xl font-bold text-gray-900">
                        <i class="fas fa-envelope mr-2"></i>Gestion Newsletter
                    </h1>
                </div>
                <div class="flex space-x-2">
                    <a href="configure_smtp.php"
                       class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition">
                        <i class="fas fa-cog mr-2"></i>Config SMTP
                    </a>
                    <a href="send_newsletter.php"
                       class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                        <i class="fas fa-paper-plane mr-2"></i>Envoyer un email
                    </a>
                    <a href="?action=export&<?= http_build_query(['search' => $search, 'status' => $status, 'source' => $source]) ?>"
                       class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                        <i class="fas fa-download mr-2"></i>Export CSV
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <!-- Messages -->
        <?php if ($message): ?>
        <div class="mb-6 p-4 bg-green-50 border-l-4 border-green-500 rounded">
            <p class="text-green-800"><?= htmlspecialchars($message) ?></p>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="mb-6 p-4 bg-red-50 border-l-4 border-red-500 rounded">
            <p class="text-red-800"><?= htmlspecialchars($error) ?></p>
        </div>
        <?php endif; ?>

        <!-- Statistiques -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">Total abonnés</p>
                        <p class="text-3xl font-bold text-gray-900"><?= number_format($stats['total']) ?></p>
                    </div>
                    <div class="bg-blue-100 rounded-full p-4">
                        <i class="fas fa-users text-blue-600 text-2xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">Actifs</p>
                        <p class="text-3xl font-bold text-green-600"><?= number_format($stats['actif']) ?></p>
                    </div>
                    <div class="bg-green-100 rounded-full p-4">
                        <i class="fas fa-check-circle text-green-600 text-2xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">Inactifs</p>
                        <p class="text-3xl font-bold text-gray-600"><?= number_format($stats['inactif']) ?></p>
                    </div>
                    <div class="bg-gray-100 rounded-full p-4">
                        <i class="fas fa-times-circle text-gray-600 text-2xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Formulaire d'ajout rapide -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">
                <i class="fas fa-plus-circle mr-2"></i>Ajouter un abonné
            </h2>
            <form method="POST" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <input type="email" name="email" placeholder="Email" required
                       class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <input type="text" name="first_name" placeholder="Prénom"
                       class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <input type="text" name="last_name" placeholder="Nom"
                       class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <button type="submit" name="add_subscriber"
                        class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-semibold">
                    <i class="fas fa-plus mr-2"></i>Ajouter
                </button>
            </form>
        </div>

        <!-- Filtres -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <input type="text" name="search" placeholder="Rechercher..." value="<?= htmlspecialchars($search) ?>"
                       class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">

                <select name="status" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="">Tous les statuts</option>
                    <option value="actif" <?= $status === 'actif' ? 'selected' : '' ?>>Actif</option>
                    <option value="inactif" <?= $status === 'inactif' ? 'selected' : '' ?>>Inactif</option>
                </select>

                <select name="source" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <option value="">Toutes les sources</option>
                    <?php foreach ($sources as $src): ?>
                        <option value="<?= htmlspecialchars($src) ?>" <?= $source === $src ? 'selected' : '' ?>>
                            <?= htmlspecialchars($src) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <div class="flex space-x-2">
                    <button type="submit" class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                        <i class="fas fa-search mr-2"></i>Filtrer
                    </button>
                    <a href="?" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition">
                        <i class="fas fa-redo"></i>
                    </a>
                </div>
            </form>
        </div>

        <!-- Liste des abonnés -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Abonné</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Source</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Engagement</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($subscribers)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                                <i class="fas fa-inbox text-4xl mb-3"></i>
                                <p>Aucun abonné trouvé</p>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($subscribers as $sub): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <div>
                                    <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($sub['email']) ?></div>
                                    <?php if ($sub['first_name'] || $sub['last_name']): ?>
                                        <div class="text-sm text-gray-500">
                                            <?= htmlspecialchars(trim($sub['first_name'] . ' ' . $sub['last_name'])) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <?php if ($sub['source']): ?>
                                    <span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">
                                        <?= htmlspecialchars($sub['source']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-gray-400">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <?php if ($sub['statut'] === 'actif'): ?>
                                    <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">
                                        <i class="fas fa-check-circle mr-1"></i>Actif
                                    </span>
                                <?php else: ?>
                                    <span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-800">
                                        <i class="fas fa-times-circle mr-1"></i>Inactif
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                <div class="flex items-center space-x-3">
                                    <span title="Ouvertures">
                                        <i class="fas fa-envelope-open text-blue-500"></i> <?= $sub['total_opens'] ?>
                                    </span>
                                    <span title="Clics">
                                        <i class="fas fa-mouse-pointer text-green-500"></i> <?= $sub['total_clicks'] ?>
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                <?= htmlspecialchars($sub['date_fmt']) ?>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <div class="flex justify-center space-x-2">
                                    <a href="?action=toggle&id=<?= $sub['id'] ?>&<?= http_build_query(['page' => $page, 'search' => $search, 'status' => $status, 'source' => $source]) ?>"
                                       class="text-yellow-600 hover:text-yellow-800"
                                       title="Changer le statut">
                                        <i class="fas fa-toggle-on"></i>
                                    </a>
                                    <a href="?action=delete&id=<?= $sub['id'] ?>&<?= http_build_query(['page' => $page, 'search' => $search, 'status' => $status, 'source' => $source]) ?>"
                                       class="text-red-600 hover:text-red-800"
                                       onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet abonné ?')"
                                       title="Supprimer">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="bg-gray-50 px-6 py-4 border-t border-gray-200">
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-700">
                        Affichage de <?= $offset + 1 ?> à <?= min($offset + $per_page, $total) ?> sur <?= $total ?> résultats
                    </div>
                    <div class="flex space-x-2">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?>&<?= http_build_query(['search' => $search, 'status' => $status, 'source' => $source]) ?>"
                               class="px-3 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php endif; ?>

                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <a href="?page=<?= $i ?>&<?= http_build_query(['search' => $search, 'status' => $status, 'source' => $source]) ?>"
                               class="px-3 py-2 <?= $i === $page ? 'bg-blue-600 text-white' : 'bg-white border border-gray-300 hover:bg-gray-50' ?> rounded-lg">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?= $page + 1 ?>&<?= http_build_query(['search' => $search, 'status' => $status, 'source' => $source]) ?>"
                               class="px-3 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
    // Confirmation suppression
    document.querySelectorAll('[onclick^="return confirm"]').forEach(link => {
        link.addEventListener('click', function(e) {
            if (!confirm(this.getAttribute('onclick').match(/'([^']+)'/)[1])) {
                e.preventDefault();
            }
        });
    });
    </script>
</body>
</html>
