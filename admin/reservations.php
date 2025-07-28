<?php
session_start();
require_once __DIR__ . '/../vendor/autoload.php';
require_once '../config.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Rediriger si l'admin n'est pas connecté
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Initialisation CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Pagination & filtres
$search = $_GET['search'] ?? '';
$perPage = 10;
$currentPage = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($currentPage - 1) * $perPage;
$date_filter = $_GET['date_filter'] ?? '';
$personnes_filter = $_GET['personnes_filter'] ?? '';

// Marquer toutes les réservations comme lues (optionnel)
$conn->query("UPDATE reservations SET statut = 'lu' WHERE statut = 'non_lu'");

// Suppression
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM reservations WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: reservations.php");
    exit;
}

// Construction de la requête
$query = "SELECT * FROM reservations WHERE 1=1";
$params = [];

// Recherche
if (!empty($search)) {
    $query .= " AND (nom LIKE ? OR email LIKE ? OR telephone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Filtre date
if (!empty($date_filter)) {
    $query .= " AND date_reservation = ?";
    $params[] = $date_filter;
}

// Filtre nombre de personnes
if (!empty($personnes_filter)) {
    if ($personnes_filter === '1-2') {
        $query .= " AND personnes BETWEEN 1 AND 2";
    } elseif ($personnes_filter === '3-4') {
        $query .= " AND personnes BETWEEN 3 AND 4";
    } elseif ($personnes_filter === '5+') {
        $query .= " AND personnes >= 5";
    }
}

// Récupération paginée
$queryWithLimit = $query . " ORDER BY date_reservation DESC LIMIT $perPage OFFSET $offset";
$stmt = $conn->prepare($queryWithLimit);
$stmt->execute($params);
$reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Nombre total pour la pagination
$countQuery = preg_replace('/SELECT \* FROM/', 'SELECT COUNT(*) as total FROM', $query, 1);
$countStmt = $conn->prepare($countQuery);
$countStmt->execute($params);
$totalReservations = $countStmt->fetch()['total'] ?? 0;
$totalPages = ceil($totalReservations / $perPage);

// Nouvelles réservations
$stmt_nouvelles = $conn->query("SELECT COUNT(*) AS total FROM reservations WHERE statut = 'non_lu'");
$data_nouvelles = $stmt_nouvelles->fetch();
$nombre_nouvelles = $data_nouvelles['total'] ?? 0;

// Insertion manuelle (depuis admin ?)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $date_reservation = trim($_POST['date_reservation'] ?? '');

    if (!empty($nom) && !empty($email) && !empty($telephone) && !empty($date_reservation)) {
        $stmt = $conn->prepare("INSERT INTO reservations (nom, email, telephone, date_reservation, statut, date_envoi)
                                VALUES (?, ?, ?, ?, 'non_lu', NOW())");

        if ($stmt->execute([$nom, $email, $telephone, $date_reservation])) {
            header("Location: reservations.php?success=1");
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gestion des Réservations</title>
  <script src="https://cdn.tailwindcss.com"></script>
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
          },
          animation: {
            'fade-in': 'fadeIn 0.5s ease-in-out',
            'slide-up': 'slideUp 0.3s ease-out'
          }
        }
      }
    }
  </script>
  <style>
    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }
    @keyframes slideUp {
      from { transform: translateY(20px); opacity: 0; }
      to { transform: translateY(0); opacity: 1; }
    }
  </style>
</head>
<body class="bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50 min-h-screen">

  <div class="flex h-screen overflow-hidden">
    
    <?php include 'sidebar.php'; ?>

    <div class="flex-1 overflow-y-auto">
      
      <!-- Header Section -->
      <div class="bg-white shadow-sm border-b border-gray-200">
        <div class="px-8 py-6">
          <div class="flex items-center justify-between">
            <div>
              <h1 class="text-3xl font-bold bg-gradient-to-r from-gray-900 to-gray-600 bg-clip-text text-transparent">
                Gestion des Réservations
              </h1>
              <p class="text-gray-600 mt-1">Gérez et suivez toutes vos réservations en temps réel</p>
            </div>
            <div class="flex items-center space-x-4">
              <div class="bg-gradient-to-r from-blue-500 to-indigo-600 text-white px-4 py-2 rounded-full text-sm font-medium shadow-lg">
                <?= $totalReservations ?> réservations au total
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="p-8">
        
        <!-- Message de succès -->
        <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
          <div class="bg-gradient-to-r from-emerald-50 to-teal-50 border border-emerald-200 rounded-xl p-4 mb-6 animate-fade-in">
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <svg class="w-5 h-5 text-emerald-600" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
              </div>
              <div class="ml-3">
                <p class="text-emerald-800 font-medium">Réservation modifiée avec succès !</p>
              </div>
            </div>
          </div>
        <?php endif; ?>

        <!-- Filtres Section -->
        <div class="bg-white rounded-2xl shadow-xl border border-gray-100 p-6 mb-8 animate-slide-up">
          <div class="flex items-center mb-4">
            <div class="p-2 bg-gradient-to-r from-blue-500 to-indigo-600 rounded-lg">
              <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M3 3a1 1 0 011-1h12a1 1 0 011 1v3a1 1 0 01-.293.707L12 11.414V15a1 1 0 01-.293.707l-2 2A1 1 0 018 17v-5.586L3.293 6.707A1 1 0 013 6V3z" clip-rule="evenodd"/>
              </svg>
            </div>
            <h2 class="text-xl font-semibold text-gray-800 ml-3">Filtres de recherche</h2>
          </div>
          
          <form method="get" class="grid grid-cols-1 lg:grid-cols-4 gap-6 items-end">
            <!-- Recherche -->
            <div class="lg:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-2">Recherche générale</label>
              <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <svg class="w-5 h-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"/>
                  </svg>
                </div>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                       placeholder="Nom, email ou téléphone..."
                       class="pl-10 pr-4 py-3 border border-gray-300 rounded-xl w-full focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 hover:border-gray-400">
              </div>
            </div>

            <!-- Filtre date -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Date de réservation</label>
              <input type="date" name="date_filter" value="<?= $_GET['date_filter'] ?? '' ?>" 
                     class="px-4 py-3 border border-gray-300 rounded-xl w-full focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 hover:border-gray-400">
            </div>
            
            <!-- Filtre personnes -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Nombre de personnes</label>
              <select name="personnes_filter" class="px-4 py-3 border border-gray-300 rounded-xl w-full focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 hover:border-gray-400">
                <option value="">Toutes</option>
                <option value="1-2" <?= ($_GET['personnes_filter'] ?? '') === '1-2' ? 'selected' : '' ?>>1-2 personnes</option>
                <option value="3-4" <?= ($_GET['personnes_filter'] ?? '') === '3-4' ? 'selected' : '' ?>>3-4 personnes</option>
                <option value="5+" <?= ($_GET['personnes_filter'] ?? '') === '5+' ? 'selected' : '' ?>>5+ personnes</option>
              </select>
            </div>

            <!-- Boutons d'action -->
            <div class="lg:col-span-4 flex flex-wrap gap-3 pt-4 border-t border-gray-100">
              <button type="submit" class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-medium rounded-xl transition-all duration-200 transform hover:scale-105 shadow-lg hover:shadow-xl">
                <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M3 3a1 1 0 011-1h12a1 1 0 011 1v3a1 1 0 01-.293.707L12 11.414V15a1 1 0 01-.293.707l-2 2A1 1 0 018 17v-5.586L3.293 6.707A1 1 0 013 6V3z" clip-rule="evenodd"/>
                </svg>
                Appliquer les filtres
              </button>
              
              <button type="button" onclick="openModal()" class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white font-medium rounded-xl transition-all duration-200 transform hover:scale-105 shadow-lg hover:shadow-xl">
                <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/>
                </svg>
                Nouvelle réservation
              </button>
              
              <a href="export_reservations.php?format=pdf" class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-gray-600 to-gray-700 hover:from-gray-700 hover:to-gray-800 text-white font-medium rounded-xl transition-all duration-200 transform hover:scale-105 shadow-lg hover:shadow-xl">
                <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M6 2a2 2 0 00-2 2v12a2 2 0 002 2h8a2 2 0 002-2V7.414A2 2 0 0015.414 6L12 2.586A2 2 0 0010.586 2H6zm5 6a1 1 0 10-2 0v3.586l-1.293-1.293a1 1 0 10-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L11 11.586V8z" clip-rule="evenodd"/>
                </svg>
                Exporter PDF
              </a>
            </div>
          </form>
        </div>

        <!-- Tableau des réservations -->
        <div class="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden animate-slide-up">
          <div class="overflow-x-auto">
            <table class="w-full">
              <thead>
                <tr class="bg-gradient-to-r from-gray-50 to-gray-100 border-b border-gray-200">
                  <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                    <div class="flex items-center">
                      <span class="w-2 h-2 bg-blue-500 rounded-full mr-2"></span>
                      ID
                    </div>
                  </th>
                  <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Client</th>
                  <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Contact</th>
                  <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Réservation</th>
                  <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Personnes</th>
                  <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Statut</th>
                  <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <?php if (!empty($reservations) && is_array($reservations)): ?>
                  <?php foreach ($reservations as $index => $res): ?>
                    <tr class="hover:bg-gradient-to-r hover:from-blue-50 hover:to-indigo-50 transition-all duration-200 group">
                      <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                          <div class="w-8 h-8 bg-gradient-to-r from-blue-500 to-indigo-600 rounded-lg flex items-center justify-center">
                            <span class="text-white text-xs font-bold"><?= $res['id'] ?></span>
                          </div>
                        </div>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                          <div class="w-10 h-10 bg-gradient-to-r from-emerald-400 to-teal-500 rounded-full flex items-center justify-center mr-3">
                            <span class="text-white font-bold text-sm"><?= strtoupper(substr(htmlspecialchars($res['nom'] ?? ''), 0, 1)) ?></span>
                          </div>
                          <div>
                            <div class="text-sm font-semibold text-gray-900"><?= htmlspecialchars($res['nom'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                          </div>
                        </div>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-900 font-medium"><?= htmlspecialchars($res['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="text-sm text-gray-500 flex items-center mt-1">
                          <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"/>
                          </svg>
                          <?= htmlspecialchars($res['telephone'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        </div>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                          <svg class="w-4 h-4 text-blue-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/>
                          </svg>
                          <div>
                            <div class="text-sm font-semibold text-gray-900"><?= htmlspecialchars($res['date_reservation'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="text-sm text-gray-500"><?= htmlspecialchars($res['heure_reservation'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                          </div>
                        </div>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                          <div class="w-8 h-8 bg-gradient-to-r from-orange-400 to-red-500 rounded-full flex items-center justify-center mr-2">
                            <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                              <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/>
                            </svg>
                          </div>
                          <span class="text-sm font-bold text-gray-900"><?= htmlspecialchars($res['personnes'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap">
                        <?php if ($res['statut'] === 'non_lu'): ?>
                          <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-gradient-to-r from-amber-100 to-orange-100 text-amber-800 border border-amber-200">
                            <svg class="w-3 h-3 mr-1 animate-pulse" fill="currentColor" viewBox="0 0 20 20">
                              <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                            </svg>
                            Non lu
                          </span>
                        <?php else: ?>
                          <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-gradient-to-r from-emerald-100 to-teal-100 text-emerald-800 border border-emerald-200">
                            <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            Lu
                          </span>
                        <?php endif; ?>
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex space-x-2">
                          <button onclick="openEditModal(<?= $res['id'] ?>)" class="inline-flex items-center px-3 py-2 text-sm font-medium text-blue-700 bg-blue-50 hover:bg-blue-100 rounded-lg transition-all duration-200 hover:scale-105 border border-blue-200">
                            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                              <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"/>
                            </svg>
                            Modifier
                          </button>
                          <a href="?delete=<?= $res['id'] ?>" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette réservation ?')" class="inline-flex items-center px-3 py-2 text-sm font-medium text-red-700 bg-red-50 hover:bg-red-100 rounded-lg transition-all duration-200 hover:scale-105 border border-red-200">
                            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                              <path fill-rule="evenodd" d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z" clip-rule="evenodd"/>
                              <path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3l1.5 1.5a1 1 0 01-1.414 1.414L10 10.414V6a1 1 0 011-1z" clip-rule="evenodd"/>
                              <path fill-rule="evenodd" d="M3 5a2 2 0 012-2h1a1 1 0 000 2H5v11a2 2 0 002 2h6a2 2 0 002-2V5h-1a1 1 0 100-2h1a2 2 0 012 2v11a4 4 0 01-4 4H7a4 4 0 01-4-4V5z" clip-rule="evenodd"/>
                            </svg>
                            Supprimer
                          </a>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="7" class="text-center py-12">
                      <div class="flex flex-col items-center">
                        <svg class="w-16 h-16 text-gray-300 mb-4" fill="currentColor" viewBox="0 0 20 20">
                          <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"/>
                        </svg>
                        <p class="text-gray-500 text-lg font-medium">Aucune réservation trouvée</p>
                        <p class="text-gray-400 text-sm mt-1">Essayez de modifier vos critères de recherche</p>
                      </div>
                    </td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

          <!-- Pagination modernisée -->
          <div class="bg-gradient-to-r from-gray-50 to-gray-100 px-6 py-4 border-t border-gray-200">
            <div class="flex items-center justify-between">
              <div class="text-sm text-gray-600 font-medium">
                Affichage de <span class="font-bold text-gray-900"><?= ($currentPage - 1) * $perPage + 1 ?></span> à 
                <span class="font-bold text-gray-900"><?= min($currentPage * $perPage, $totalReservations) ?></span> 
                sur <span class="font-bold text-gray-900"><?= $totalReservations ?></span> réservations
              </div>
              
              <div class="flex items-center space-x-2">
                <?php if ($currentPage > 1): ?>
                  <a href="?<?= http_build_query(array_merge($_GET, ['page' => $currentPage - 1])) ?>" 
                     class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 hover:border-gray-400 transition-all duration-200">
                    <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"/>
                    </svg>
                    Précédent
                  </a>
                <?php endif; ?>
                
                <div class="flex space-x-1">
                  <?php 
                  $start = max(1, $currentPage - 2);
                  $end = min($totalPages, $currentPage + 2);
                  
                  if ($start > 1): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>" 
                       class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">1</a>
                    <?php if ($start > 2): ?>
                      <span class="px-3 py-2 text-sm text-gray-500">...</span>
                    <?php endif; ?>
                  <?php endif; ?>
                  
                  <?php for ($i = $start; $i <= $end; $i++): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" 
                       class="px-3 py-2 text-sm font-medium rounded-lg transition-all duration-200 <?= $i == $currentPage ? 'bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow-lg' : 'text-gray-700 bg-white border border-gray-300 hover:bg-gray-50' ?>">
                      <?= $i ?>
                    </a>
                  <?php endfor; ?>
                  
                  <?php if ($end < $totalPages): ?>
                    <?php if ($end < $totalPages - 1): ?>
                      <span class="px-3 py-2 text-sm text-gray-500">...</span>
                    <?php endif; ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $totalPages])) ?>" 
                       class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"><?= $totalPages ?></a>
                  <?php endif; ?>
                </div>
                
                <?php if ($currentPage < $totalPages): ?>
                  <a href="?<?= http_build_query(array_merge($_GET, ['page' => $currentPage + 1])) ?>" 
                     class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 hover:border-gray-400 transition-all duration-200">
                    Suivant
                    <svg class="w-4 h-4 ml-2" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                    </svg>
                  </a>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal d'édition de réservation -->
  <div id="editReservationModal" class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm flex items-center justify-center p-4 z-50 hidden">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl max-h-[90vh] overflow-y-auto animate-slide-up">
      <div class="p-8">
        <div class="flex justify-between items-center mb-6">
          <div>
            <h3 class="text-2xl font-bold bg-gradient-to-r from-gray-900 to-gray-600 bg-clip-text text-transparent">
              Modifier la réservation
            </h3>
            <p class="text-gray-600 mt-1">Modifiez les informations de la réservation</p>
          </div>
          <button onclick="closeEditModal()" class="p-2 text-gray-400 hover:text-gray-500 hover:bg-gray-100 rounded-full transition-all duration-200">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>
        
        <form method="POST" action="update_reservation.php" class="space-y-6">
          <input type="hidden" id="edit_id" name="id">
          <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label for="edit_nom" class="block text-sm font-semibold text-gray-700 mb-2">
                <span class="flex items-center">
                  <svg class="w-4 h-4 mr-2 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                  </svg>
                  Nom complet *
                </span>
              </label>
              <input type="text" id="edit_nom" name="nom" required 
                     class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 hover:border-gray-400">
            </div>
            
            <div>
              <label for="edit_email" class="block text-sm font-semibold text-gray-700 mb-2">
                <span class="flex items-center">
                  <svg class="w-4 h-4 mr-2 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/>
                    <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/>
                  </svg>
                  Email *
                </span>
              </label>
              <input type="email" id="edit_email" name="email" required 
                     class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 hover:border-gray-400">
            </div>
            
            <div>
              <label for="edit_telephone" class="block text-sm font-semibold text-gray-700 mb-2">
                <span class="flex items-center">
                  <svg class="w-4 h-4 mr-2 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"/>
                  </svg>
                  Téléphone *
                </span>
              </label>
              <input type="tel" id="edit_telephone" name="telephone" required 
                     class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 hover:border-gray-400">
            </div>
            
            <div>
              <label for="edit_personnes" class="block text-sm font-semibold text-gray-700 mb-2">
                <span class="flex items-center">
                  <svg class="w-4 h-4 mr-2 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/>
                  </svg>
                  Nombre de personnes *
                </span>
              </label>
              <input type="number" id="edit_personnes" name="personnes" min="1" required 
                     class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 hover:border-gray-400">
            </div>
            
            <div>
              <label for="edit_date_reservation" class="block text-sm font-semibold text-gray-700 mb-2">
                <span class="flex items-center">
                  <svg class="w-4 h-4 mr-2 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/>
                  </svg>
                  Date *
                </span>
              </label>
              <input type="date" id="edit_date_reservation" name="date_reservation" required 
                     class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 hover:border-gray-400">
            </div>
            
            <div>
              <label for="edit_heure_reservation" class="block text-sm font-semibold text-gray-700 mb-2">
                <span class="flex items-center">
                  <svg class="w-4 h-4 mr-2 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
                  </svg>
                  Heure *
                </span>
              </label>
              <input type="time" id="edit_heure_reservation" name="heure_reservation" required 
                     class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 hover:border-gray-400">
            </div>
          </div>
          
          <div class="flex justify-end space-x-4 pt-6 border-t border-gray-100">
            <button type="button" onclick="closeEditModal()" 
                    class="px-6 py-3 border border-gray-300 rounded-xl text-gray-700 font-medium hover:bg-gray-50 transition-all duration-200">
              Annuler
            </button>
            <button type="submit" 
                    class="px-6 py-3 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-medium rounded-xl transition-all duration-200 transform hover:scale-105 shadow-lg hover:shadow-xl">
              Mettre à jour
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal d'ajout de réservation -->
  <div id="reservationModal" class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm flex items-center justify-center p-4 z-50 hidden">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl max-h-[90vh] overflow-y-auto animate-slide-up">
      <div class="p-8">
        <div class="flex justify-between items-center mb-6">
          <div>
            <h3 class="text-2xl font-bold bg-gradient-to-r from-gray-900 to-gray-600 bg-clip-text text-transparent">
              Nouvelle réservation
            </h3>
            <p class="text-gray-600 mt-1">Ajoutez une nouvelle réservation manuellement</p>
          </div>
          <button onclick="closeModal()" class="p-2 text-gray-400 hover:text-gray-500 hover:bg-gray-100 rounded-full transition-all duration-200">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>
        
        <form method="POST" action="reservations.php" class="space-y-6">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label for="nom" class="block text-sm font-semibold text-gray-700 mb-2">
                <span class="flex items-center">
                  <svg class="w-4 h-4 mr-2 text-emerald-500" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                  </svg>
                  Nom complet *
                </span>
              </label>
              <input type="text" id="nom" name="nom" required 
                     class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-all duration-200 hover:border-gray-400">
            </div>
            
            <div>
              <label for="email" class="block text-sm font-semibold text-gray-700 mb-2">
                <span class="flex items-center">
                  <svg class="w-4 h-4 mr-2 text-emerald-500" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/>
                    <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/>
                  </svg>
                  Email *
                </span>
              </label>
              <input type="email" id="email" name="email" required 
                     class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-all duration-200 hover:border-gray-400">
            </div>
            
            <div>
              <label for="telephone" class="block text-sm font-semibold text-gray-700 mb-2">
                <span class="flex items-center">
                  <svg class="w-4 h-4 mr-2 text-emerald-500" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"/>
                  </svg>
                  Téléphone *
                </span>
              </label>
              <input type="tel" id="telephone" name="telephone" required 
                     class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-all duration-200 hover:border-gray-400">
            </div>
            
            <div>
              <label for="personnes" class="block text-sm font-semibold text-gray-700 mb-2">
                <span class="flex items-center">
                  <svg class="w-4 h-4 mr-2 text-emerald-500" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/>
                  </svg>
                  Nombre de personnes *
                </span>
              </label>
              <input type="number" id="personnes" name="personnes" min="1" required 
                     class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-all duration-200 hover:border-gray-400">
            </div>
            
            <div>
              <label for="date_reservation" class="block text-sm font-semibold text-gray-700 mb-2">
                <span class="flex items-center">
                  <svg class="w-4 h-4 mr-2 text-emerald-500" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/>
                  </svg>
                  Date *
                </span>
              </label>
              <input type="date" id="date_reservation" name="date_reservation" required 
                     class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-all duration-200 hover:border-gray-400">
            </div>
            
            <div>
              <label for="heure_reservation" class="block text-sm font-semibold text-gray-700 mb-2">
                <span class="flex items-center">
                  <svg class="w-4 h-4 mr-2 text-emerald-500" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
                  </svg>
                  Heure *
                </span>
              </label>
              <input type="time" id="heure_reservation" name="heure_reservation" required 
                     class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-all duration-200 hover:border-gray-400">
            </div>
          </div>
          
          <div class="flex justify-end space-x-4 pt-6 border-t border-gray-100">
            <button type="button" onclick="closeModal()" 
                    class="px-6 py-3 border border-gray-300 rounded-xl text-gray-700 font-medium hover:bg-gray-50 transition-all duration-200">
              Annuler
            </button>
            <button type="submit" 
                    class="px-6 py-3 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white font-medium rounded-xl transition-all duration-200 transform hover:scale-105 shadow-lg hover:shadow-xl">
              Enregistrer
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
    // Gestion du modal d'ajout
    function openModal() {
      document.getElementById('reservationModal').classList.remove('hidden');
      const today = new Date().toISOString().split('T')[0];
      document.getElementById('date_reservation').value = today;
    }
    
    function closeModal() {
      document.getElementById('reservationModal').classList.add('hidden');
    }
    
    document.getElementById('reservationModal').addEventListener('click', function(e) {
      if (e.target === this) {
        closeModal();
      }
    });

    // Gestion du modal d'édition
    function openEditModal(reservationId) {
      fetch('get_reservation.php?id=' + reservationId)
        .then(response => response.json())
        .then(data => {
          document.getElementById('edit_id').value = data.id;
          document.getElementById('edit_nom').value = data.nom;
          document.getElementById('edit_email').value = data.email;
          document.getElementById('edit_telephone').value = data.telephone;
          document.getElementById('edit_personnes').value = data.personnes;
          document.getElementById('edit_date_reservation').value = data.date_reservation;
          document.getElementById('edit_heure_reservation').value = data.heure_reservation;
          
          document.getElementById('editReservationModal').classList.remove('hidden');
        })
        .catch(error => {
          console.error('Error:', error);
          alert('Une erreur est survenue lors du chargement des données');
        });
    }

    function closeEditModal() {
      document.getElementById('editReservationModal').classList.add('hidden');
    }

    document.getElementById('editReservationModal').addEventListener('click', function(e) {
      if (e.target === this) {
        closeEditModal();
      }
    });

    // Vérification des nouvelles réservations
    function checkNewReservations() {
      fetch('check_new_reservations.php')
        .then(response => response.json())
        .then(data => {
          if(data.count > 0) {
            showNotification(`${data.count} nouvelle(s) réservation(s)`);
          }
        });
    }

    function showNotification(message) {
      const toast = document.createElement('div');
      toast.className = 'fixed bottom-4 right-4 bg-gradient-to-r from-blue-600 to-indigo-600 text-white px-6 py-4 rounded-xl shadow-2xl transform transition-all duration-300 animate-slide-up';
      toast.innerHTML = `
        <div class="flex items-center">
          <svg class="w-5 h-5 mr-3" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
          </svg>
          <span class="font-medium">${message}</span>
        </div>
      `;
      document.body.appendChild(toast);
      
      setTimeout(() => {
        toast.style.transform = 'translateX(100%)';
        setTimeout(() => {
          toast.remove();
        }, 300);
      }, 5000);
    }

    // Vérifier toutes les 2 minutes
    setInterval(checkNewReservations, 120000);
    document.addEventListener('DOMContentLoaded', checkNewReservations);
  </script>

</body>
</html>