<?php
session_start();
require_once '../../config.php';
require_once '../permissions.php';
requireAccess($conn, $_SESSION['admin_id'], 'annonces_public');

$message = "";
$messageType = "";

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'ajouter':
                $titre = $_POST['titre'];
                $contenu = $_POST['contenu'];
                $type = $_POST['type_annonce'];
                $couleur = $_POST['couleur'];
                $date_debut = !empty($_POST['date_debut']) ? $_POST['date_debut'] : null;
                $date_fin = !empty($_POST['date_fin']) ? $_POST['date_fin'] : null;

                $query = "INSERT INTO annonce_public (titre, contenu, type_annonce, couleur, date_debut, date_fin)
                          VALUES (:titre, :contenu, :type, :couleur, :date_debut, :date_fin)";
                $stmt = $conn->prepare($query);
                $success = $stmt->execute([
                    ':titre' => $titre,
                    ':contenu' => $contenu,
                    ':type' => $type,
                    ':couleur' => $couleur,
                    ':date_debut' => $date_debut,
                    ':date_fin' => $date_fin
                ]);

                if ($success) {
                    $message = "Annonce ajoutée avec succès!";
                    $messageType = "success";
                } else {
                    $message = "Erreur lors de l'ajout";
                    $messageType = "error";
                }
                break;

            case 'modifier':
                $id = intval($_POST['id']);
                $titre = $_POST['titre'];
                $contenu = $_POST['contenu'];
                $type = $_POST['type_annonce'];
                $couleur = $_POST['couleur'];
                $statut = $_POST['statut'];
                $date_debut = !empty($_POST['date_debut']) ? $_POST['date_debut'] : null;
                $date_fin = !empty($_POST['date_fin']) ? $_POST['date_fin'] : null;

                $query = "UPDATE annonce_public
                          SET titre = :titre, contenu = :contenu, type_annonce = :type,
                              couleur = :couleur, statut = :statut,
                              date_debut = :date_debut, date_fin = :date_fin
                          WHERE id = :id";
                $stmt = $conn->prepare($query);
                $success = $stmt->execute([
                    ':titre' => $titre,
                    ':contenu' => $contenu,
                    ':type' => $type,
                    ':couleur' => $couleur,
                    ':statut' => $statut,
                    ':date_debut' => $date_debut,
                    ':date_fin' => $date_fin,
                    ':id' => $id
                ]);

                if ($success) {
                    $message = "Annonce modifiée avec succès!";
                    $messageType = "success";
                } else {
                    $message = "Erreur lors de la modification";
                    $messageType = "error";
                }
                break;

            case 'supprimer':
                $id = intval($_POST['id']);
                $query = "DELETE FROM annonce_public WHERE id = :id";
                $stmt = $conn->prepare($query);
                $success = $stmt->execute([':id' => $id]);

                if ($success) {
                    $message = "Annonce supprimée avec succès!";
                    $messageType = "success";
                } else {
                    $message = "Erreur lors de la suppression";
                    $messageType = "error";
                }
                break;
        }
    }
}

/**
 * Désactive automatiquement les annonces expirées
 */
function desactiverAnnoncesExpirees() {
    global $conn;
    $date_aujourd_hui = date('Y-m-d');
    $sql = "UPDATE annonce_public
            SET statut = 'inactive'
            WHERE date_fin IS NOT NULL
            AND date_fin < :date
            AND statut = 'active'";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':date' => $date_aujourd_hui]);
    return $stmt->rowCount();
}

/**
 * Obtient les statistiques des annonces
 */
function getStatistiquesAnnonces() {
    global $conn;
    $stats = [];

    $stmt = $conn->query("SELECT COUNT(*) as total FROM annonce_public");
    $stats['total'] = $stmt->fetchColumn();

    $stmt = $conn->query("SELECT COUNT(*) as actives FROM annonce_public WHERE statut = 'active'");
    $stats['actives'] = $stmt->fetchColumn();

    $stmt = $conn->query("SELECT type_annonce, COUNT(*) as count
                         FROM annonce_public
                         WHERE statut = 'active'
                         GROUP BY type_annonce");
    $stats['par_type'] = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $stats['par_type'][$row['type_annonce']] = $row['count'];
    }

    $date_aujourd_hui = date('Y-m-d');
    $stmt = $conn->prepare("SELECT COUNT(*) as expirees
                           FROM annonce_public
                           WHERE date_fin = :date");
    $stmt->execute([':date' => $date_aujourd_hui]);
    $stats['expire_aujourdhui'] = $stmt->fetchColumn();

    return $stats;
}

$query = "SELECT * FROM annonce_public ORDER BY date_creation DESC";
$stmt = $conn->query($query);
$annonces = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stats = getStatistiquesAnnonces();
desactiverAnnoncesExpirees();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Annonces Publiques - Restaurant Jungle</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'primary': '#10b981',
                        'primary-dark': '#059669',
                        'primary-light': '#34d399',
                    }
                }
            }
        }
    </script>
    <style>
        .glass-morphism {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
        }
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
        .animate-float {
            animation: float 3s ease-in-out infinite;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 via-gray-100 to-gray-200">

    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <?php include '../includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Header -->
            <header class="glass-morphism shadow-lg border-b border-gray-200">
                <div class="px-4 sm:px-6 lg:px-8 py-6">
                    <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
                        <div class="flex-1">
                            <div class="flex items-center space-x-4">
                                <div class="w-14 h-14 bg-gradient-to-br from-primary via-primary-light to-yellow-500 rounded-2xl flex items-center justify-center shadow-lg animate-float">
                                    <i class="fas fa-bullhorn text-white text-2xl"></i>
                                </div>
                                <div>
                                    <h1 class="text-3xl lg:text-4xl font-bold bg-gradient-to-r from-primary to-yellow-500 bg-clip-text text-transparent">
                                        Annonces Publiques
                                    </h1>
                                    <p class="text-gray-600 text-sm font-medium mt-1">Gérez vos annonces en temps réel</p>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center space-x-4">
                            <div class="hidden sm:flex items-center space-x-3 bg-white rounded-2xl px-6 py-4 shadow-md">
                                <div class="w-10 h-10 bg-primary/20 rounded-xl flex items-center justify-center">
                                    <i class="fas fa-calendar text-primary"></i>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 font-medium uppercase">Aujourd'hui</p>
                                    <p class="text-sm font-bold text-gray-800"><?= date('d/m/Y H:i') ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main content -->
            <main class="flex-1 overflow-y-auto">
                <div class="container mx-auto px-4 sm:px-6 lg:px-8 py-8" x-data="{ modalOpen: false, currentAnnonce: null }">

                    <!-- Messages -->
                    <?php if ($message): ?>
                        <div class="mb-6 p-4 rounded-lg <?= $messageType === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?> flex items-center">
                            <i class="fas <?= $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-3"></i>
                            <?= htmlspecialchars($message) ?>
                        </div>
                    <?php endif; ?>

                    <!-- Stats Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                        <div class="bg-white rounded-2xl shadow-lg p-6 border-t-4 border-purple-500 hover:shadow-xl transition-all duration-300">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-xs font-semibold text-gray-500 uppercase">Total</p>
                                    <h3 class="text-4xl font-bold text-gray-800 mt-2"><?= $stats['total'] ?></h3>
                                    <p class="text-sm text-gray-500 mt-2"><i class="fas fa-chart-line mr-1"></i>Toutes catégories</p>
                                </div>
                                <div class="w-14 h-14 bg-purple-100 rounded-xl flex items-center justify-center">
                                    <i class="fas fa-bullhorn text-2xl text-purple-500"></i>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-2xl shadow-lg p-6 border-t-4 border-green-500 hover:shadow-xl transition-all duration-300">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-xs font-semibold text-gray-500 uppercase">Actives</p>
                                    <h3 class="text-4xl font-bold text-gray-800 mt-2"><?= $stats['actives'] ?></h3>
                                    <p class="text-sm text-gray-500 mt-2"><i class="fas fa-eye mr-1"></i>Visibles</p>
                                </div>
                                <div class="w-14 h-14 bg-green-100 rounded-xl flex items-center justify-center">
                                    <i class="fas fa-check-circle text-2xl text-green-500"></i>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-2xl shadow-lg p-6 border-t-4 border-blue-500 hover:shadow-xl transition-all duration-300">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-xs font-semibold text-gray-500 uppercase">Menu</p>
                                    <h3 class="text-4xl font-bold text-gray-800 mt-2"><?= $stats['par_type']['menu'] ?? 0 ?></h3>
                                    <p class="text-sm text-gray-500 mt-2"><i class="fas fa-utensils mr-1"></i>Restaurant</p>
                                </div>
                                <div class="w-14 h-14 bg-blue-100 rounded-xl flex items-center justify-center">
                                    <i class="fas fa-utensils text-2xl text-blue-500"></i>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-2xl shadow-lg p-6 border-t-4 border-yellow-500 hover:shadow-xl transition-all duration-300">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-xs font-semibold text-gray-500 uppercase">Expirent</p>
                                    <h3 class="text-4xl font-bold text-gray-800 mt-2"><?= $stats['expire_aujourdhui'] ?></h3>
                                    <p class="text-sm text-gray-500 mt-2"><i class="fas fa-clock mr-1"></i>Aujourd'hui</p>
                                </div>
                                <div class="w-14 h-14 bg-yellow-100 rounded-xl flex items-center justify-center">
                                    <i class="fas fa-clock text-2xl text-yellow-500"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Form Card -->
                    <div class="bg-white rounded-2xl shadow-lg mb-8 overflow-hidden">
                        <div class="bg-gradient-to-r from-primary to-primary-dark px-6 py-4">
                            <h2 class="text-xl font-bold text-white flex items-center">
                                <i class="fas fa-plus mr-3"></i>Créer une Nouvelle Annonce
                            </h2>
                        </div>
                        <div class="p-6">
                            <form method="POST" class="space-y-6">
                                <input type="hidden" name="action" value="ajouter">
                                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                                            <i class="fas fa-heading mr-2"></i>Titre *
                                        </label>
                                        <input type="text" name="titre" required
                                               class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-primary focus:ring focus:ring-primary/20 transition-all">
                                    </div>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                                <i class="fas fa-tag mr-2"></i>Type *
                                            </label>
                                            <select name="type_annonce" required
                                                    class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-primary focus:ring focus:ring-primary/20 transition-all">
                                                <option value="site">🌐 Site</option>
                                                <option value="menu">🍽️ Menu</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                                <i class="fas fa-palette mr-2"></i>Couleur
                                            </label>
                                            <input type="color" name="couleur" value="#10b981"
                                                   class="w-full h-12 rounded-xl border-2 border-gray-300 cursor-pointer">
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                                        <i class="fas fa-align-left mr-2"></i>Contenu *
                                    </label>
                                    <textarea name="contenu" required rows="4"
                                              class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-primary focus:ring focus:ring-primary/20 transition-all"></textarea>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                                            <i class="fas fa-calendar-plus mr-2"></i>Date début
                                        </label>
                                        <input type="date" name="date_debut"
                                               class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-primary focus:ring focus:ring-primary/20 transition-all">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                                            <i class="fas fa-calendar-minus mr-2"></i>Date fin
                                        </label>
                                        <input type="date" name="date_fin"
                                               class="w-full px-4 py-3 rounded-xl border-2 border-gray-300 focus:border-primary focus:ring focus:ring-primary/20 transition-all">
                                    </div>
                                </div>
                                <div class="text-center">
                                    <button type="submit" class="bg-gradient-to-r from-primary to-primary-dark text-white px-8 py-4 rounded-xl font-semibold hover:shadow-2xl transform hover:scale-105 transition-all duration-300">
                                        <i class="fas fa-rocket mr-2"></i>Publier l'Annonce
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Table Card -->
                    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                        <div class="bg-gradient-to-r from-primary to-primary-dark px-6 py-4">
                            <h2 class="text-xl font-bold text-white flex items-center justify-between">
                                <span><i class="fas fa-list mr-3"></i>Annonces Existantes (<?= count($annonces) ?>)</span>
                                <span class="text-sm font-normal bg-white/20 px-4 py-2 rounded-full"><?= $stats['actives'] ?> actives</span>
                            </h2>
                        </div>
                        <div class="overflow-x-auto">
                            <?php if (empty($annonces)): ?>
                                <div class="text-center py-12">
                                    <i class="fas fa-inbox fa-4x text-gray-300 mb-4"></i>
                                    <h4 class="text-xl text-gray-500">Aucune annonce trouvée</h4>
                                    <p class="text-gray-400">Créez votre première annonce ci-dessus</p>
                                </div>
                            <?php else: ?>
                                <table class="w-full">
                                    <thead class="bg-gray-100">
                                        <tr>
                                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase">ID</th>
                                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase">Détails</th>
                                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase">Type</th>
                                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase">Statut</th>
                                            <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase">Période</th>
                                            <th class="px-6 py-4 text-center text-xs font-bold text-gray-700 uppercase">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        <?php foreach ($annonces as $annonce): ?>
                                        <tr class="hover:bg-gray-50 transition-colors">
                                            <td class="px-6 py-4">
                                                <span class="bg-gray-200 text-gray-700 px-3 py-1 rounded-full text-sm font-semibold">#<?= $annonce['id'] ?></span>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="font-bold text-gray-800 mb-1 flex items-center" style="color: <?= $annonce['couleur'] ?>;">
                                                    <i class="fas fa-circle text-xs mr-2"></i>
                                                    <?= htmlspecialchars($annonce['titre']) ?>
                                                </div>
                                                <div class="text-sm text-gray-600 bg-gray-50 px-3 py-2 rounded-lg">
                                                    <?= htmlspecialchars(substr($annonce['contenu'], 0, 80)) ?><?= strlen($annonce['contenu']) > 80 ? '...' : '' ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <span class="<?= $annonce['type_annonce'] == 'menu' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800' ?> px-4 py-2 rounded-full text-sm font-semibold">
                                                    <?= $annonce['type_annonce'] == 'menu' ? '🍽️ Menu' : '🌐 Site' ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4">
                                                <span class="<?= $annonce['statut'] == 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?> px-4 py-2 rounded-full text-sm font-semibold">
                                                    <i class="fas <?= $annonce['statut'] == 'active' ? 'fa-check' : 'fa-times' ?> mr-1"></i>
                                                    <?= ucfirst($annonce['statut']) ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 text-sm">
                                                <div class="text-gray-600">
                                                    <i class="fas fa-play text-green-500 mr-1"></i><?= $annonce['date_debut'] ?: 'Immédiat' ?>
                                                </div>
                                                <div class="text-gray-600">
                                                    <i class="fas fa-stop text-red-500 mr-1"></i><?= $annonce['date_fin'] ?: 'Illimité' ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="flex items-center justify-center space-x-2">
                                                    <button @click="currentAnnonce = <?= htmlspecialchars(json_encode($annonce)) ?>; modalOpen = true"
                                                            class="bg-blue-500 hover:bg-blue-600 text-white w-10 h-10 rounded-lg transition-all">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <form method="POST" onsubmit="return confirm('Confirmer la suppression ?')" class="inline">
                                                        <input type="hidden" name="action" value="supprimer">
                                                        <input type="hidden" name="id" value="<?= $annonce['id'] ?>">
                                                        <button type="submit" class="bg-red-500 hover:bg-red-600 text-white w-10 h-10 rounded-lg transition-all">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Modal Edit -->
                    <div x-show="modalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" x-cloak style="display: none;">
                        <div @click.away="modalOpen = false" class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
                            <div class="bg-gradient-to-r from-primary to-primary-dark px-6 py-4 flex justify-between items-center">
                                <h3 class="text-xl font-bold text-white"><i class="fas fa-edit mr-2"></i>Modifier l'Annonce</h3>
                                <button @click="modalOpen = false" class="text-white hover:text-gray-200">
                                    <i class="fas fa-times text-2xl"></i>
                                </button>
                            </div>
                            <form method="POST" class="p-6 space-y-4">
                                <input type="hidden" name="action" value="modifier">
                                <input type="hidden" name="id" x-model="currentAnnonce?.id">

                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Titre *</label>
                                    <input type="text" name="titre" x-model="currentAnnonce?.titre" required
                                           class="w-full px-4 py-2 rounded-lg border-2 border-gray-300 focus:border-primary">
                                </div>

                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Contenu *</label>
                                    <textarea name="contenu" x-model="currentAnnonce?.contenu" required rows="4"
                                              class="w-full px-4 py-2 rounded-lg border-2 border-gray-300 focus:border-primary"></textarea>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Type *</label>
                                        <select name="type_annonce" x-model="currentAnnonce?.type_annonce" required
                                                class="w-full px-4 py-2 rounded-lg border-2 border-gray-300 focus:border-primary">
                                            <option value="site">🌐 Site</option>
                                            <option value="menu">🍽️ Menu</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Statut</label>
                                        <select name="statut" x-model="currentAnnonce?.statut"
                                                class="w-full px-4 py-2 rounded-lg border-2 border-gray-300 focus:border-primary">
                                            <option value="active">✅ Active</option>
                                            <option value="inactive">❌ Inactive</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="grid grid-cols-3 gap-4">
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Couleur</label>
                                        <input type="color" name="couleur" x-model="currentAnnonce?.couleur"
                                               class="w-full h-10 rounded-lg border-2 border-gray-300">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Date début</label>
                                        <input type="date" name="date_debut" x-model="currentAnnonce?.date_debut"
                                               class="w-full px-4 py-2 rounded-lg border-2 border-gray-300 focus:border-primary">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Date fin</label>
                                        <input type="date" name="date_fin" x-model="currentAnnonce?.date_fin"
                                               class="w-full px-4 py-2 rounded-lg border-2 border-gray-300 focus:border-primary">
                                    </div>
                                </div>

                                <div class="flex justify-end space-x-3 pt-4">
                                    <button type="button" @click="modalOpen = false"
                                            class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-all">
                                        Annuler
                                    </button>
                                    <button type="submit"
                                            class="px-6 py-2 bg-gradient-to-r from-primary to-primary-dark text-white rounded-lg hover:shadow-lg transition-all">
                                        <i class="fas fa-save mr-2"></i>Enregistrer
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                </div>
            </main>
        </div>
    </div>

    <!-- Floating Button -->
    <button onclick="window.scrollTo({top: 0, behavior: 'smooth'})"
            class="fixed bottom-8 right-8 bg-gradient-to-r from-primary to-primary-dark text-white w-14 h-14 rounded-full shadow-2xl hover:shadow-3xl transition-all duration-300 hover:scale-110 z-40">
        <i class="fas fa-arrow-up"></i>
    </button>

    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <style>
        [x-cloak] { display: none !important; }
    </style>
</body>
</html>
