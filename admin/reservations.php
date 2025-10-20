<?php
    session_start();
    require_once __DIR__ . '/../vendor/autoload.php';
    require_once '../config.php';
    require_once './permissions.php';
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    // Rediriger si l'admin n'est pas connecté
    if (! isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header('Location: login.php');
        exit;
    }

    // Initialisation CSRF
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

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

    requireAccess($conn, $_SESSION['admin_id'], 'reservations');

    // Récupérer les infos de l'admin
    $stmt_admin = $conn->prepare("SELECT username, email FROM admin WHERE id = ?");
    $stmt_admin->execute([$_SESSION['admin_id']]);
    $admin_info = $stmt_admin->fetch(PDO::FETCH_ASSOC);
    $admin_name = $admin_info['username'] ?? $_SESSION['admin_username'] ?? 'Admin';
    $admin_email = $admin_info['email'] ?? 'admin@restaurant.com';
    $admin_photo = null; // Photo non disponible dans la base de données

    // Pagination & filtres
    $search           = $_GET['search'] ?? '';
    $date_filter      = $_GET['date_filter'] ?? '';
    $personnes_filter = $_GET['personnes_filter'] ?? '';
    $active_tab       = $_GET['tab'] ?? 'actives'; // 'actives' ou 'historique'

    // DÉSACTIVÉ : Ne pas marquer automatiquement comme lu pour garder les notifications
    // Si vous voulez que les réservations soient marquées comme lues automatiquement, décommentez la ligne ci-dessous
    // $conn->query("UPDATE reservations SET statut = 'lu' WHERE statut = 'non_lu'");

    // Suppression via AJAX
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'supprimer') {
        header('Content-Type: application/json');
        $id = (int) $_POST['id'];

        try {
            $stmt = $conn->prepare("DELETE FROM reservations WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Réservation supprimée avec succès']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression']);
        }
        exit;
    }

    // Récupération du total global (sans filtre)
    $stmt_total_global = $conn->query("SELECT COUNT(*) AS total_global FROM reservations");
    $total_global      = $stmt_total_global->fetch()['total_global'] ?? 0;

    // Construction de la requête
    // Dans la requête principale, ajouter le message :
    $query = "SELECT id, nom, email, telephone, personnes, date_reservation,
          heure_reservation, message, date_envoi, statut
          FROM reservations WHERE 1=1";
    $params = [];

    // Filtre selon l'onglet actif
    if ($active_tab === 'actives') {
        // Réservations actives : réservations non annulées (futures ou en attente de traitement)
        // On inclut aussi les réservations passées non traitées pour ne rien perdre
        $query .= " AND (statut = 'non_lu' OR (statut != 'annule' AND date_reservation >= CURDATE()))";
    }
    // Pour 'historique', on affiche tout

    // Recherche
    if (! empty($search)) {
        $query .= " AND (nom LIKE ? OR email LIKE ? OR telephone LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    // Filtre date
    if (! empty($date_filter)) {
        $query .= " AND date_reservation = ?";
        $params[] = $date_filter;
    }

    // Filtre nombre de personnes
    if (! empty($personnes_filter)) {
        if ($personnes_filter === '1-2') {
            $query .= " AND personnes BETWEEN 1 AND 2";
        } elseif ($personnes_filter === '3-4') {
            $query .= " AND personnes BETWEEN 3 AND 4";
        } elseif ($personnes_filter === '5+') {
            $query .= " AND personnes >= 5";
        }
    }

    // Pagination
    $items_per_page = 10;
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $offset = ($page - 1) * $items_per_page;

    // Compter le total (pour la pagination)
    $queryCount = str_replace(
        "SELECT id, nom, email, telephone, personnes, date_reservation, heure_reservation, message, date_envoi, statut FROM reservations",
        "SELECT COUNT(*) as total FROM reservations",
        $query
    );
    $stmtCount = $conn->prepare($queryCount);
    $stmtCount->execute($params);
    $total_items = $stmtCount->fetch()['total'] ?? 0;
    $total_pages = ceil($total_items / $items_per_page);

    // Récupération paginée - MODIFIÉ : Tri par date_envoi DESC pour avoir les plus récentes en haut
    $queryWithLimit = $query . " ORDER BY date_envoi DESC, id DESC LIMIT :limit OFFSET :offset";
    $stmt           = $conn->prepare($queryWithLimit);

    // Bind des paramètres de pagination
    $stmt->bindValue(':limit', $items_per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

    // Bind des autres paramètres
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    $stmt->execute();
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // DEBUG: Afficher le nombre total
    echo "<!-- DEBUG: Total items = $total_items, Total pages = $total_pages, Onglet = $active_tab -->";
    echo "<!-- DEBUG: Query = " . htmlspecialchars($queryWithLimit) . " -->";
    echo "<!-- DEBUG: Nombre de résultats = " . count($reservations) . " -->";

    // Pour les statistiques de l'historique, récupérer toutes les réservations (sans pagination)
    if ($active_tab === 'historique') {
        $queryAllHist = "SELECT * FROM reservations WHERE 1=1";
        $stmtAllHist = $conn->prepare($queryAllHist);
        $stmtAllHist->execute();
        $all_reservations_hist = $stmtAllHist->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $all_reservations_hist = $reservations;
    }

    // Nouvelles réservations
    $stmt_nouvelles   = $conn->query("SELECT COUNT(*) AS total FROM reservations WHERE statut = 'non_lu'");
    $data_nouvelles   = $stmt_nouvelles->fetch();
    $nombre_nouvelles = $data_nouvelles['total'] ?? 0;

    // Insertion manuelle (depuis admin ?)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $nom               = trim($_POST['nom'] ?? '');
        $email             = trim($_POST['email'] ?? '');
        $telephone         = trim($_POST['telephone'] ?? '');
        $date_reservation  = trim($_POST['date_reservation'] ?? '');
        $heure_reservation = trim($_POST['heure_reservation'] ?? '');
        $personnes         = (int) ($_POST['personnes'] ?? 1);
        $message           = trim($_POST['message'] ?? ''); // Ajouté

        if (! empty($nom) && ! empty($email) && ! empty($telephone) &&
            ! empty($date_reservation) && ! empty($heure_reservation)) {

            // Requête complète avec tous les champs
            $stmt = $conn->prepare("INSERT INTO reservations
            (nom, email, telephone, date_reservation, heure_reservation, personnes, message, statut, date_envoi)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'non_lu', NOW())");

            if ($stmt->execute([$nom, $email, $telephone, $date_reservation, $heure_reservation, $personnes, $message])) {
                header("Location: reservations.php?success=1");
                exit;
            }
        }
    }
    // Réservations aujourd'hui
    $aujourdhui = date('Y-m-d');
    $stmt_auj   = $conn->prepare("SELECT COUNT(*) AS total FROM reservations WHERE date_reservation = ?");
    $stmt_auj->execute([$aujourdhui]);
    $reservations_aujourdhui = $stmt_auj->fetch()['total'] ?? 0;

    // Moyenne des personnes
    $stmt_moy          = $conn->query("SELECT AVG(personnes) AS moyenne FROM reservations");
    $moyenne_personnes = round($stmt_moy->fetch()['moyenne'] ?? 0, 1);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gestion des Réservations</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
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
  <link rel="stylesheet" href="assets/css/cards-design.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }
    @keyframes slideUp {
      from { transform: translateY(20px); opacity: 0; }
      to { transform: translateY(0); opacity: 1; }
    }

    /* Fix pour le sidebar */
#sidebar {
    background: rgba(15, 23, 42, 0.95) !important;
    z-index: 50;
}

/* Style pour les lignes cliquables du tableau */
tbody tr.cursor-pointer {
    position: relative;
}

tbody tr.cursor-pointer:hover {
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.15);
    transform: translateY(-1px);
}

tbody tr.cursor-pointer:active {
    transform: translateY(0);
}

/* Icône "voir" au survol de la ligne */
tbody tr.cursor-pointer::before {
    content: '\f06e'; /* FontAwesome eye icon */
    font-family: 'Font Awesome 6 Free';
    font-weight: 900;
    position: absolute;
    left: -30px;
    top: 50%;
    transform: translateY(-50%);
    color: #3b82f6;
    font-size: 16px;
    opacity: 0;
    transition: all 0.3s ease;
    pointer-events: none;
}

tbody tr.cursor-pointer:hover::before {
    opacity: 1;
    left: -25px;
}

/* Style pour l'info-bulle */
#info-bulle-ligne {
    transition: all 0.3s ease;
}

/* Styles pour un tableau plus compact */
table {
    font-size: 0.875rem;
}

tbody td {
    vertical-align: middle;
}

/* Hauteur de ligne fixe pour uniformité */
tbody tr {
    height: 65px;
}

/* Amélioration du hover sur les boutons */
button[title] {
    position: relative;
}

button[title]:hover::after {
    content: attr(title);
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    background: rgba(0, 0, 0, 0.8);
    color: white;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 11px;
    white-space: nowrap;
    z-index: 100;
    margin-bottom: 5px;
}

/* Masquer l'icône œil sur les petits écrans */
@media (max-width: 1400px) {
    tbody tr.cursor-pointer::before {
        display: none;
    }
}


  </style>
</head>
<body class="bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50 min-h-screen">

  <div class="flex h-screen overflow-hidden">

    <?php include 'sidebar.php'; ?>


    <div class="flex-1 overflow-y-auto">

      <!-- Header Professionnel -->
      <header class="bg-slate-900 shadow-lg sticky top-0 z-40">
        <div class="px-4 sm:px-6 lg:px-8 py-4">
          <div class="flex justify-between items-center">
            <!-- Section Titre -->
            <div class="flex items-center space-x-4">
              <div class="w-12 h-12 bg-teal-600 rounded-xl flex items-center justify-center">
                <i class="fas fa-chart-line text-white text-lg"></i>
              </div>
              <div>
                <h1 class="text-2xl font-bold text-white">
                  Tableau de Bord
                </h1>
                <p class="text-gray-400 text-sm">
                  Bienvenue, <?= htmlspecialchars($_SESSION['admin_username'] ?? 'Admin') ?> ✨
                </p>
              </div>
            </div>

            <!-- Contrôles -->
            <div class="flex items-center space-x-4" x-data="{ profileOpen: false, notificationsOpen: false }">
              <!-- Badge de Notifications -->
              <div class="relative">
                <button
                  @click="notificationsOpen = !notificationsOpen"
                  id="notification-button"
                  class="relative w-12 h-12 bg-slate-800 rounded-xl flex items-center justify-center hover:bg-slate-700 transition-all focus:outline-none"
                  type="button"
                >
                  <i class="fas fa-bell text-white text-lg"></i>
                  <span id="notification-badge" class="absolute -top-1 -right-1 w-6 h-6 bg-red-500 text-white text-xs font-bold rounded-full flex items-center justify-center animate-pulse hidden">
                    0
                  </span>
                </button>

                <!-- Panneau de Notifications -->
                <div
                  x-show="notificationsOpen"
                  @click.away="notificationsOpen = false"
                  x-transition:enter="transition ease-out duration-200"
                  x-transition:enter-start="transform opacity-0 scale-95 translate-y-2"
                  x-transition:enter-end="transform opacity-100 scale-100 translate-y-0"
                  x-transition:leave="transition ease-in duration-150"
                  x-transition:leave-start="transform opacity-100 scale-100 translate-y-0"
                  x-transition:leave-end="transform opacity-0 scale-95 translate-y-2"
                  class="absolute right-0 mt-2 w-96 bg-slate-800 rounded-xl shadow-2xl overflow-hidden z-50 max-h-[500px] overflow-y-auto"
                  style="display: none;"
                >
                  <!-- En-tête Notifications -->
                  <div class="px-5 py-4 border-b border-slate-700 bg-gradient-to-r from-blue-600 to-indigo-600">
                    <div class="flex items-center justify-between">
                      <h3 class="text-white font-bold text-base">Notifications</h3>
                      <span id="notification-count" class="bg-white text-blue-600 px-2 py-1 rounded-full text-xs font-bold">0</span>
                    </div>
                  </div>

                  <!-- Réservations du jour -->
                  <div class="px-5 py-3 border-b border-slate-700 bg-slate-750">
                    <div class="flex items-center justify-between mb-2">
                      <h4 class="text-teal-400 font-semibold text-sm flex items-center">
                        <i class="fas fa-calendar-day mr-2"></i>
                        Aujourd'hui (<span id="today-count">0</span>)
                      </h4>
                    </div>
                    <div id="today-reservations" class="space-y-2 max-h-48 overflow-y-auto">
                      <!-- Chargées dynamiquement -->
                    </div>
                  </div>

                  <!-- Nouvelles Réservations -->
                  <div class="px-5 py-3">
                    <h4 class="text-amber-400 font-semibold text-sm mb-2 flex items-center">
                      <i class="fas fa-bell mr-2 animate-pulse"></i>
                      Nouvelles réservations
                    </h4>
                    <div id="new-reservations" class="space-y-2">
                      <!-- Chargées dynamiquement -->
                    </div>
                  </div>

                  <!-- Footer -->
                  <div class="px-5 py-3 border-t border-slate-700 bg-slate-900">
                    <button onclick="window.location.reload()" class="w-full text-center text-sm text-blue-400 hover:text-blue-300 font-medium">
                      <i class="fas fa-sync-alt mr-2"></i>Actualiser
                    </button>
                  </div>
                </div>
              </div>

              <!-- Widget Date/Heure -->
              <div class="hidden sm:flex items-center space-x-5 bg-slate-800 rounded-xl px-5 py-3">
                <div class="flex items-center space-x-3">
                  <div class="w-10 h-10 bg-slate-700 rounded-lg flex items-center justify-center">
                    <i class="fas fa-calendar text-blue-400 text-sm"></i>
                  </div>
                  <div>
                    <p class="text-xs text-gray-400 uppercase">Aujourd'hui</p>
                    <p class="text-sm font-bold text-white"><?= date('d M Y') ?></p>
                  </div>
                </div>
                <div class="flex items-center space-x-3">
                  <div class="w-10 h-10 bg-slate-700 rounded-lg flex items-center justify-center">
                    <i class="fas fa-clock text-teal-400 text-sm"></i>
                  </div>
                  <div>
                    <p class="text-xs text-gray-400 uppercase">Heure</p>
                    <p class="text-sm font-bold text-white font-mono" id="live-clock"><?= date('H:i:s') ?></p>
                  </div>
                </div>
              </div>

              <!-- Menu Profil -->
              <div class="relative">
                <button
                  @click="profileOpen = !profileOpen"
                  class="relative w-12 h-12 rounded-xl flex items-center justify-center hover:opacity-90 transition-opacity focus:outline-none overflow-hidden"
                  type="button"
                >
                  <?php if (!empty($admin_photo) && file_exists(__DIR__ . '/' . $admin_photo)): ?>
                    <img src="<?= htmlspecialchars($admin_photo) ?>"
                         alt="Photo de profil"
                         class="w-full h-full object-cover rounded-xl">
                  <?php else: ?>
                    <div class="w-full h-full bg-gradient-to-r from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center">
                      <span class="text-white font-bold text-base">
                        <?= strtoupper(substr($admin_name, 0, 1)) ?>
                      </span>
                    </div>
                  <?php endif; ?>
                  <div class="absolute -bottom-0.5 -right-0.5 w-3.5 h-3.5 bg-green-400 border-2 border-slate-900 rounded-full"></div>
                </button>

                <!-- Dropdown Profil -->
                <div
                  x-show="profileOpen"
                  @click.away="profileOpen = false"
                  x-transition:enter="transition ease-out duration-200"
                  x-transition:enter-start="transform opacity-0 scale-95"
                  x-transition:enter-end="transform opacity-100 scale-100"
                  x-transition:leave="transition ease-in duration-150"
                  x-transition:leave-start="transform opacity-100 scale-100"
                  x-transition:leave-end="transform opacity-0 scale-95"
                  class="absolute right-0 mt-2 w-72 bg-slate-800 rounded-xl shadow-xl overflow-hidden z-50"
                  style="display: none;"
                >
                  <!-- En-tête -->
                  <div class="px-5 py-4 border-b border-slate-700">
                    <div class="flex items-center space-x-3">
                      <?php if (!empty($admin_photo) && file_exists(__DIR__ . '/' . $admin_photo)): ?>
                        <img src="<?= htmlspecialchars($admin_photo) ?>"
                             alt="Photo de profil"
                             class="w-12 h-12 object-cover rounded-lg">
                      <?php else: ?>
                        <div class="w-12 h-12 bg-gradient-to-r from-blue-500 to-indigo-600 rounded-lg flex items-center justify-center">
                          <span class="text-white font-bold text-base"><?= strtoupper(substr($admin_name, 0, 1)) ?></span>
                        </div>
                      <?php endif; ?>
                      <div>
                        <p class="text-white font-semibold text-base"><?= htmlspecialchars($admin_name) ?></p>
                        <p class="text-gray-400 text-sm"><?= htmlspecialchars($admin_email) ?></p>
                      </div>
                    </div>
                    <div class="mt-3 flex items-center">
                      <span class="w-2 h-2 bg-green-400 rounded-full mr-2"></span>
                      <span class="text-green-400 text-sm">En ligne</span>
                    </div>
                  </div>

                  <!-- Menu -->
                  <div class="py-2">
                    <a href="profile.php" class="flex items-center px-5 py-3 hover:bg-slate-700 transition-colors">
                      <i class="fas fa-user text-blue-400 w-5"></i>
                      <span class="ml-3 text-white text-sm">Mon profil</span>
                      <span class="ml-auto text-gray-400">›</span>
                    </a>
                    <a href="settings.php" class="flex items-center px-5 py-3 hover:bg-slate-700 transition-colors">
                      <i class="fas fa-envelope text-purple-400 w-5"></i>
                      <span class="ml-3 text-white text-sm">Changer email</span>
                      <span class="ml-auto text-gray-400">›</span>
                    </a>
                    <div class="border-t border-slate-700 my-2"></div>
                    <a href="logout.php" class="flex items-center px-5 py-3 hover:bg-red-900/20 transition-colors">
                      <i class="fas fa-sign-out-alt text-red-400 w-5"></i>
                      <span class="ml-3 text-red-400 text-sm font-medium">Déconnexion</span>
                    </a>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </header>

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

<!-- Section Cartes KPI avec design moderne de gestion_plats.php -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
    <!-- Carte 1: Total réservations -->
    <div class="dashboard-card card-blue animate-fade-in">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-600 text-sm font-medium mb-1">Total réservations</p>
                <p class="text-3xl font-bold text-gray-900"><?php echo $total_global?></p>
                <p class="text-sm text-green-600 flex items-center mt-2">
                    <i class="fas fa-arrow-up mr-1"></i>
                    +8% ce mois
                </p>
            </div>
            <div class="icon-wrapper icon-blue">
                <i class="fas fa-calendar-check"></i>
            </div>
        </div>
    </div>

    <!-- Carte 2: Nouvelles réservations -->
    <div class="dashboard-card card-green animate-fade-in" style="animation-delay: 0.1s;">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-600 text-sm font-medium mb-1">Nouvelles</p>
                <p class="text-3xl font-bold text-gray-900"><?php echo $nombre_nouvelles?></p>
                <p class="text-sm text-amber-600 flex items-center mt-2">
                    <i class="fas fa-bell mr-1 animate-pulse"></i>
                    À traiter
                </p>
            </div>
            <div class="icon-wrapper icon-green">
                <i class="fas fa-bell"></i>
            </div>
        </div>
    </div>

    <!-- Carte 3: Aujourd'hui -->
    <div class="dashboard-card card-orange animate-fade-in" style="animation-delay: 0.2s;">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-600 text-sm font-medium mb-1">Aujourd'hui</p>
                <p class="text-3xl font-bold text-gray-900"><?php echo $reservations_aujourdhui?></p>
                <p class="text-sm text-gray-600 mt-1"><?php echo date('d M Y')?></p>
            </div>
            <div class="icon-wrapper icon-orange">
                <i class="fas fa-calendar-day"></i>
            </div>
        </div>
    </div>

    <!-- Carte 4: Moyenne personnes -->
    <div class="dashboard-card card-purple animate-fade-in" style="animation-delay: 0.3s;">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-600 text-sm font-medium mb-1">Moyenne</p>
                <p class="text-3xl font-bold text-gray-900"><?php echo $moyenne_personnes?></p>
                <p class="text-sm text-gray-600 mt-1">Personnes/résa</p>
            </div>
            <div class="icon-wrapper icon-purple">
                <i class="fas fa-users"></i>
            </div>
        </div>
    </div>
</div>
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
                <input type="text" name="search" value="<?php echo htmlspecialchars($search)?>"
                       placeholder="Nom, email ou téléphone..."
                       class="pl-10 pr-4 py-3 border border-gray-300 rounded-xl w-full focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 hover:border-gray-400">
              </div>
            </div>

            <!-- Filtre date -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Date de réservation</label>
              <input type="date" name="date_filter" value="<?php echo $_GET['date_filter'] ?? ''?>"
                     class="px-4 py-3 border border-gray-300 rounded-xl w-full focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 hover:border-gray-400">
            </div>

            <!-- Filtre personnes -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Nombre de personnes</label>
              <select name="personnes_filter" class="px-4 py-3 border border-gray-300 rounded-xl w-full focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 hover:border-gray-400">
                <option value="">Toutes</option>
                <option value="1-2" <?php echo ($_GET['personnes_filter'] ?? '') === '1-2' ? 'selected' : ''?>>1-2 personnes</option>
                <option value="3-4" <?php echo ($_GET['personnes_filter'] ?? '') === '3-4' ? 'selected' : ''?>>3-4 personnes</option>
                <option value="5+" <?php echo ($_GET['personnes_filter'] ?? '') === '5+' ? 'selected' : ''?>>5+ personnes</option>
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

              <button type="button" onclick="marquerToutCommeLu()" class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-700 hover:to-pink-700 text-white font-medium rounded-xl transition-all duration-200 transform hover:scale-105 shadow-lg hover:shadow-xl">
                <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                Marquer tout comme lu
              </button>
            </div>
          </form>
        </div>

        <!-- Onglets -->
        <div class="mb-6 border-b border-gray-200">
            <nav class="-mb-px flex space-x-8">
                <a href="?tab=actives" id="tab-actives" class="tab-button border-b-2 <?= $active_tab === 'actives' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' ?> py-4 px-1 text-sm font-medium">
                    <i class="fas fa-calendar-check mr-2"></i>Réservations Actives
                </a>
                <a href="?tab=historique" id="tab-historique" class="tab-button border-b-2 <?= $active_tab === 'historique' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' ?> py-4 px-1 text-sm font-medium">
                    <i class="fas fa-history mr-2"></i>Historique
                </a>
            </nav>
        </div>

        <!-- Content Actives -->
        <div id="content-actives" <?= $active_tab !== 'actives' ? 'style="display:none;"' : '' ?>>
        <!-- Info-bulle cliquable -->
        <div id="info-bulle-ligne" class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-4 rounded-lg animate-fade-in">
          <div class="flex items-center justify-between">
            <div class="flex items-center">
              <i class="fas fa-info-circle text-blue-500 text-xl mr-3"></i>
              <p class="text-sm text-blue-800">
                <strong>Astuce :</strong> Cliquez directement sur une ligne du tableau pour voir les détails de la réservation.
                <span class="text-blue-600">✨</span>
              </p>
            </div>
            <button onclick="fermerInfoBulle()" class="text-blue-400 hover:text-blue-600 transition-colors ml-4">
              <i class="fas fa-times"></i>
            </button>
          </div>
        </div>

        <!-- Tableau des réservations avec bordures visibles -->
        <div class="bg-white rounded-2xl shadow-xl border-2 border-gray-200 overflow-hidden animate-slide-up">
          <div class="overflow-x-auto" style="min-height: 400px;">
            <table class="w-full border-collapse" style="min-width: 1200px;">
             <thead>
  <tr class="bg-gradient-to-r from-gray-50 to-gray-100 border-b-2 border-gray-300">
    <th class="px-3 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider border-r border-gray-300" style="width: 60px;">
      <div class="flex items-center">
        <span class="w-2 h-2 bg-blue-500 rounded-full mr-1"></span>
        N°
      </div>
    </th>
    <th class="px-3 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider border-r border-gray-300" style="width: 150px;">Client</th>
    <th class="px-3 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider border-r border-gray-300" style="width: 180px;">Contact</th>
    <th class="px-3 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider border-r border-gray-300" style="width: 140px;">Réservation</th>
    <th class="px-3 py-3 text-center text-xs font-bold text-gray-700 uppercase tracking-wider border-r border-gray-300" style="width: 80px;">Pers.</th>
    <th class="px-3 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider border-r border-gray-300" style="width: 200px;">Message</th>
    <th class="px-3 py-3 text-center text-xs font-bold text-gray-700 uppercase tracking-wider border-r border-gray-300" style="width: 100px;">Statut</th>
    <th class="px-3 py-3 text-center text-xs font-bold text-gray-700 uppercase tracking-wider" style="width: 220px;">Actions</th>
  </tr>
</thead>

              <tbody class="divide-y-2 divide-gray-200">
  <?php if (! empty($reservations) && is_array($reservations)): ?>
<?php
    // Calculer le numéro de départ basé sur le nombre total de réservations
    $total_count = count($reservations);
    foreach ($reservations as $index => $res):
        // Le numéro commence par le total et décrémente pour chaque ligne
        $numero = $total_count - $index;
    ?>
	  <tr class="hover:bg-gradient-to-r hover:from-blue-50 hover:to-indigo-50 transition-all duration-200 group border-b border-gray-200 cursor-pointer"
	      data-reservation-id="<?php echo $res['id']?>"
	      onclick="ouvrirModalDepuisLigne(event, <?php echo $res['id']?>)"
	      title="Cliquez pour voir les détails">
	    <td class="px-3 py-3 whitespace-nowrap border-r border-gray-200">
	      <div class="flex items-center justify-center">
	        <div class="w-7 h-7 bg-gradient-to-r from-blue-500 to-indigo-600 rounded-lg flex items-center justify-center">
	          <span class="text-white text-xs font-bold"><?php echo $numero?></span>
	        </div>
	      </div>
	    </td>
	        <td class="px-3 py-3 border-r border-gray-200">
	          <div class="flex items-center">
	            <div class="w-8 h-8 bg-gradient-to-r from-emerald-400 to-teal-500 rounded-full flex items-center justify-center mr-2 flex-shrink-0">
	              <span class="text-white font-bold text-xs"><?php echo strtoupper(substr(htmlspecialchars($res['nom'] ?? ''), 0, 1))?></span>
	            </div>
	            <div class="text-sm font-semibold text-gray-900 truncate"><?php echo htmlspecialchars($res['nom'] ?? '', ENT_QUOTES, 'UTF-8')?></div>
	          </div>
	        </td>
	        <td class="px-3 py-3 border-r border-gray-200">
	          <div class="text-xs text-gray-900 font-medium truncate"><?php echo htmlspecialchars($res['email'] ?? '', ENT_QUOTES, 'UTF-8')?></div>
	          <div class="text-xs text-gray-500 flex items-center mt-0.5">
	            <i class="fas fa-phone text-xs mr-1"></i>
	            <?php echo htmlspecialchars($res['telephone'] ?? '', ENT_QUOTES, 'UTF-8')?>
	          </div>
	        </td>
	        <td class="px-3 py-3 border-r border-gray-200">
	          <div class="flex items-center">
	            <i class="fas fa-calendar text-blue-500 mr-1.5 text-xs"></i>
	            <div>
	              <div class="text-xs font-semibold text-gray-900"><?php echo date('d/m/Y', strtotime($res['date_reservation'] ?? ''))?></div>
	              <div class="text-xs text-gray-500"><?php echo substr($res['heure_reservation'] ?? '', 0, 5)?></div>
	            </div>
	          </div>
	        </td>
	        <td class="px-3 py-3 border-r border-gray-200 text-center">
	          <div class="flex items-center justify-center">
	            <div class="w-7 h-7 bg-gradient-to-r from-orange-400 to-red-500 rounded-full flex items-center justify-center">
	              <i class="fas fa-users text-white text-xs"></i>
	            </div>
	            <span class="text-sm font-bold text-gray-900 ml-1"><?php echo htmlspecialchars($res['personnes'] ?? '', ENT_QUOTES, 'UTF-8')?></span>
	          </div>
	        </td>
	        <!-- NOUVELLE COLONNE MESSAGE -->
	        <td class="px-3 py-3 border-r border-gray-200">
	          <div class="max-w-[200px]">
	            <?php if (! empty($res['message'])): ?>
	              <div class="flex items-start">
	                <i class="fas fa-comment text-blue-500 text-xs mr-1 mt-0.5 flex-shrink-0"></i>
	                <div class="text-xs text-gray-700 truncate">
	                  <?php echo htmlspecialchars(substr($res['message'], 0, 40) . (strlen($res['message']) > 40 ? '...' : ''), ENT_QUOTES, 'UTF-8')?>
	                </div>
	              </div>
<?php else: ?>
              <span class="text-xs text-gray-400 italic flex items-center">
                <i class="fas fa-minus-circle text-xs mr-1"></i>
                Aucun
              </span>
            <?php endif; ?>
          </div>
        </td>
        <td class="px-3 py-3 border-r border-gray-200 text-center">
          <?php if ($res['statut'] === 'non_lu'): ?>
            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 border border-amber-200">
              <i class="fas fa-circle text-xs mr-1 animate-pulse"></i>
              Nouveau
            </span>
          <?php else: ?>
            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 border border-emerald-200">
              <i class="fas fa-check-circle text-xs mr-1"></i>
              Lu
            </span>
          <?php endif; ?>
        </td>
        <td class="px-3 py-3 text-center">
          <div class="flex items-center justify-center gap-1">
            <button onclick="openViewModal(<?php echo $res['id']?>)" class="inline-flex items-center px-2 py-1.5 text-xs font-medium text-green-700 bg-green-50 hover:bg-green-100 rounded-lg transition-all duration-200 border border-green-200" title="Voir les détails">
              <i class="fas fa-eye text-sm"></i>
            </button>
            <button onclick="openEditModal(<?php echo $res['id']?>)" class="inline-flex items-center px-2 py-1.5 text-xs font-medium text-blue-700 bg-blue-50 hover:bg-blue-100 rounded-lg transition-all duration-200 border border-blue-200" title="Modifier">
              <i class="fas fa-edit text-sm"></i>
            </button>
            <button onclick="confirmDelete(<?php echo $res['id']?>, '<?php echo htmlspecialchars($res['nom'], ENT_QUOTES)?>', '<?php echo date('d/m/Y', strtotime($res['date_reservation']))?>')" class="inline-flex items-center px-2 py-1.5 text-xs font-medium text-red-700 bg-red-50 hover:bg-red-100 rounded-lg transition-all duration-200 border border-red-200" title="Supprimer">
              <i class="fas fa-trash text-sm"></i>
            </button>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
<?php else: ?>
    <tr>
      <td colspan="8" class="text-center py-12">
        <div class="flex flex-col items-center">
          <div class="bg-blue-100 p-4 rounded-full mb-4">
            <i class="fas fa-calendar-times text-blue-500 text-4xl"></i>
          </div>
          <p class="text-gray-700 text-xl font-semibold mb-2">Aucune réservation active</p>
          <p class="text-gray-500 text-sm mb-4">
            <?php if ($active_tab === 'actives'): ?>
              Il n'y a pas de réservations futures ou en attente.
            <?php else: ?>
              Aucune réservation ne correspond à vos critères de recherche.
            <?php endif; ?>
          </p>
          <div class="flex gap-3 mt-4">
            <?php if (!empty($search) || !empty($date_filter) || !empty($personnes_filter)): ?>
              <a href="?tab=<?= $active_tab ?>" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                <i class="fas fa-redo mr-2"></i>Réinitialiser les filtres
              </a>
            <?php endif; ?>
            <a href="?tab=historique" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
              <i class="fas fa-history mr-2"></i>Voir l'historique
            </a>
          </div>
        </div>
      </td>
    </tr>
  <?php endif; ?>
</tbody>
            </table>
          </div>

          <!-- Pagination -->
          <?php if ($total_pages > 1): ?>
          <div class="px-6 py-4 border-t-2 border-gray-200 bg-gray-50">
              <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
                  <!-- Info pagination -->
                  <div class="text-sm text-gray-600">
                      Affichage de <span class="font-semibold"><?= min($offset + 1, $total_items) ?></span> à
                      <span class="font-semibold"><?= min($offset + $items_per_page, $total_items) ?></span> sur
                      <span class="font-semibold"><?= $total_items ?></span> réservations
                  </div>

                  <!-- Boutons pagination -->
                  <div class="flex items-center gap-2">
                      <?php
                      // Construire les paramètres GET pour conserver les filtres
                      $query_params = [];
                      if (!empty($search)) $query_params[] = 'search=' . urlencode($search);
                      if (!empty($date_filter)) $query_params[] = 'date_filter=' . urlencode($date_filter);
                      if (!empty($personnes_filter)) $query_params[] = 'personnes_filter=' . urlencode($personnes_filter);
                      if (!empty($active_tab)) $query_params[] = 'tab=' . urlencode($active_tab);
                      $query_string = !empty($query_params) ? '&' . implode('&', $query_params) : '';
                      ?>

                      <!-- Première page -->
                      <?php if ($page > 1): ?>
                          <a href="?page=1<?= $query_string ?>"
                             class="px-3 py-2 bg-white border-2 border-gray-300 rounded-lg hover:bg-gray-100 transition-colors">
                              <i class="fas fa-angle-double-left"></i>
                          </a>
                      <?php endif; ?>

                      <!-- Page précédente -->
                      <?php if ($page > 1): ?>
                          <a href="?page=<?= $page - 1 ?><?= $query_string ?>"
                             class="px-3 py-2 bg-white border-2 border-gray-300 rounded-lg hover:bg-gray-100 transition-colors">
                              <i class="fas fa-angle-left"></i>
                          </a>
                      <?php endif; ?>

                      <!-- Numéros de pages -->
                      <?php
                      $start_page = max(1, $page - 2);
                      $end_page = min($total_pages, $page + 2);

                      for ($i = $start_page; $i <= $end_page; $i++):
                      ?>
                          <a href="?page=<?= $i ?><?= $query_string ?>"
                             class="px-4 py-2 <?= $i === $page ? 'bg-blue-600 text-white border-2 border-blue-600' : 'bg-white text-gray-700 border-2 border-gray-300 hover:bg-gray-100' ?> rounded-lg font-semibold transition-colors">
                              <?= $i ?>
                          </a>
                      <?php endfor; ?>

                      <!-- Page suivante -->
                      <?php if ($page < $total_pages): ?>
                          <a href="?page=<?= $page + 1 ?><?= $query_string ?>"
                             class="px-3 py-2 bg-white border-2 border-gray-300 rounded-lg hover:bg-gray-100 transition-colors">
                              <i class="fas fa-angle-right"></i>
                          </a>
                      <?php endif; ?>

                      <!-- Dernière page -->
                      <?php if ($page < $total_pages): ?>
                          <a href="?page=<?= $total_pages ?><?= $query_string ?>"
                             class="px-3 py-2 bg-white border-2 border-gray-300 rounded-lg hover:bg-gray-100 transition-colors">
                              <i class="fas fa-angle-double-right"></i>
                          </a>
                      <?php endif; ?>
                  </div>
              </div>
          </div>
          <?php endif; ?>
        </div>
        </div>

        <!-- Content Historique -->
        <div id="content-historique" <?= $active_tab !== 'historique' ? 'style="display:none;"' : '' ?>>
            <!-- Filtres Historique -->
            <div class="bg-white rounded-2xl shadow-xl border border-gray-200 p-6 mb-6">
                <h3 class="text-lg font-semibold mb-4">Filtres Historique</h3>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Date début</label>
                        <input type="date" id="hist-date-debut" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Date fin</label>
                        <input type="date" id="hist-date-fin" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Statut</label>
                        <select id="hist-statut" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                            <option value="">Tous les statuts</option>
                            <option value="lu">Confirmé</option>
                            <option value="annule">Annulé</option>
                            <option value="modifie">Modifié</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Recherche</label>
                        <input type="text" id="hist-search" placeholder="Nom, email..." class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    </div>
                </div>
                <div class="mt-4 flex justify-end space-x-3">
                    <button onclick="resetHistFilters()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                        <i class="fas fa-redo mr-2"></i>Réinitialiser
                    </button>
                    <button onclick="applyHistFilters()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        <i class="fas fa-filter mr-2"></i>Filtrer
                    </button>
                </div>
            </div>

            <!-- Statistiques Historique -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                <div class="dashboard-card card-blue">
                    <div class="icon-wrapper icon-blue">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="card-content">
                        <h3 class="card-title">Total Réservations</h3>
                        <p class="card-value" id="hist-total"><?= count($all_reservations_hist) ?></p>
                    </div>
                </div>
                <div class="dashboard-card card-green">
                    <div class="icon-wrapper icon-green">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="card-content">
                        <h3 class="card-title">Total Personnes</h3>
                        <p class="card-value" id="hist-personnes"><?= array_sum(array_column($all_reservations_hist, 'personnes')) ?></p>
                    </div>
                </div>
                <div class="dashboard-card card-purple">
                    <div class="icon-wrapper icon-purple">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="card-content">
                        <h3 class="card-title">Moyenne/Résa</h3>
                        <p class="card-value" id="hist-moyenne"><?= count($all_reservations_hist) > 0 ? round(array_sum(array_column($all_reservations_hist, 'personnes')) / count($all_reservations_hist), 1) : 0 ?></p>
                    </div>
                </div>
                <div class="dashboard-card card-orange">
                    <div class="icon-wrapper icon-orange">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <div class="card-content">
                        <h3 class="card-title">Plus Grande Résa</h3>
                        <p class="card-value" id="hist-max"><?= !empty($all_reservations_hist) ? max(array_column($all_reservations_hist, 'personnes')) : 0 ?></p>
                    </div>
                </div>
            </div>

            <!-- Tableau Historique -->
            <div class="bg-white rounded-2xl shadow-xl border-2 border-gray-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse" id="table-historique">
                        <thead>
                            <tr class="bg-gradient-to-r from-gray-50 to-gray-100 border-b-2 border-gray-300">
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase">N°</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase">Client</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase">Contact</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase">Date</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase">Heure</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase">Personnes</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase">Statut</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase">Message</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reservations as $index => $resa):
                                $statut_badge = [
                                    'lu' => 'bg-green-100 text-green-800',
                                    'annule' => 'bg-red-100 text-red-800',
                                    'modifie' => 'bg-orange-100 text-orange-800',
                                    'non_lu' => 'bg-blue-100 text-blue-800'
                                ];
                                $badge_color = $statut_badge[$resa['statut']] ?? 'bg-gray-100 text-gray-800';
                            ?>
                            <tr class="border-b border-gray-200 hover:bg-gray-50"
                                data-nom="<?= strtolower($resa['nom']) ?>"
                                data-email="<?= strtolower($resa['email']) ?>"
                                data-date="<?= $resa['date_reservation'] ?>"
                                data-statut="<?= $resa['statut'] ?>">
                                <td class="px-6 py-4 text-sm font-medium text-gray-900">#<?= $resa['id'] ?></td>
                                <td class="px-6 py-4">
                                    <div class="font-semibold text-gray-900"><?= htmlspecialchars($resa['nom']) ?></div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-700">
                                    <div><?= htmlspecialchars($resa['email']) ?></div>
                                    <div><?= htmlspecialchars($resa['telephone']) ?></div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-700">
                                    <?= date('d/m/Y', strtotime($resa['date_reservation'])) ?>
                                </td>
                                <td class="px-6 py-4 text-sm font-semibold text-blue-600">
                                    <?= date('H:i', strtotime($resa['heure_reservation'])) ?>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-3 py-1 bg-purple-100 text-purple-800 rounded-full text-sm font-semibold">
                                        <?= $resa['personnes'] ?> pers.
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-3 py-1 rounded-full text-xs font-semibold <?= $badge_color ?>">
                                        <?php
                                            $statut_text = [
                                                'lu' => 'Confirmé',
                                                'annule' => 'Annulé',
                                                'modifie' => 'Modifié',
                                                'non_lu' => 'Nouveau'
                                            ];
                                            echo $statut_text[$resa['statut']] ?? ucfirst($resa['statut']);
                                        ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600">
                                    <?php if (!empty($resa['message'])): ?>
                                        <button onclick="viewMessage('<?= htmlspecialchars($resa['message'], ENT_QUOTES) ?>')" class="text-blue-600 hover:text-blue-800">
                                            <i class="fas fa-envelope mr-1"></i>Voir
                                        </button>
                                    <?php else: ?>
                                        <span class="text-gray-400">Aucun</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <button onclick='viewHistDetails(<?= json_encode($resa) ?>)' class="text-blue-600 hover:text-blue-800 mr-2">
                                        <i class="fas fa-eye"></i> Détails
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Historique -->
                <?php if ($total_pages > 1): ?>
                <div class="px-6 py-4 border-t-2 border-gray-200 bg-gray-50">
                    <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
                        <!-- Info pagination -->
                        <div class="text-sm text-gray-600">
                            Affichage de <span class="font-semibold"><?= min($offset + 1, $total_items) ?></span> à
                            <span class="font-semibold"><?= min($offset + $items_per_page, $total_items) ?></span> sur
                            <span class="font-semibold"><?= $total_items ?></span> réservations
                        </div>

                        <!-- Boutons pagination -->
                        <div class="flex items-center gap-2">
                            <?php
                            // Utilise le même query_string que pour les réservations actives
                            ?>

                            <!-- Première page -->
                            <?php if ($page > 1): ?>
                                <a href="?page=1<?= $query_string ?>"
                                   class="px-3 py-2 bg-white border-2 border-gray-300 rounded-lg hover:bg-gray-100 transition-colors">
                                    <i class="fas fa-angle-double-left"></i>
                                </a>
                            <?php endif; ?>

                            <!-- Page précédente -->
                            <?php if ($page > 1): ?>
                                <a href="?page=<?= $page - 1 ?><?= $query_string ?>"
                                   class="px-3 py-2 bg-white border-2 border-gray-300 rounded-lg hover:bg-gray-100 transition-colors">
                                    <i class="fas fa-angle-left"></i>
                                </a>
                            <?php endif; ?>

                            <!-- Numéros de pages -->
                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);

                            for ($i = $start_page; $i <= $end_page; $i++):
                            ?>
                                <a href="?page=<?= $i ?><?= $query_string ?>"
                                   class="px-4 py-2 <?= $i === $page ? 'bg-blue-600 text-white border-2 border-blue-600' : 'bg-white text-gray-700 border-2 border-gray-300 hover:bg-gray-100' ?> rounded-lg font-semibold transition-colors">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>

                            <!-- Page suivante -->
                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?= $page + 1 ?><?= $query_string ?>"
                                   class="px-3 py-2 bg-white border-2 border-gray-300 rounded-lg hover:bg-gray-100 transition-colors">
                                    <i class="fas fa-angle-right"></i>
                                </a>
                            <?php endif; ?>

                            <!-- Dernière page -->
                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?= $total_pages ?><?= $query_string ?>"
                                   class="px-3 py-2 bg-white border-2 border-gray-300 rounded-lg hover:bg-gray-100 transition-colors">
                                    <i class="fas fa-angle-double-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
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
          <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']?>">

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label for="edit_nom" class="block text-sm font-semibold text-gray-700 mb-2">
                <span class="flex items-center">
                  <svg class="w-4 h-4 mr-2 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                  </svg>
                  Nom complet *
                  <svg class="w-4 h-4 ml-2 text-gray-400" fill="currentColor" viewBox="0 0 20 20" title="Non modifiable">
                    <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                  </svg>
                </span>
              </label>
              <input type="text" id="edit_nom" name="nom" required readonly
                     class="w-full px-4 py-3 border border-gray-200 rounded-xl bg-gray-50 text-gray-600 cursor-not-allowed"
                     title="Ce champ ne peut pas être modifié">
            </div>

            <div>
              <label for="edit_email" class="block text-sm font-semibold text-gray-700 mb-2">
                <span class="flex items-center">
                  <svg class="w-4 h-4 mr-2 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/>
                    <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/>
                  </svg>
                  Email *
                  <svg class="w-4 h-4 ml-2 text-gray-400" fill="currentColor" viewBox="0 0 20 20" title="Non modifiable">
                    <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                  </svg>
                </span>
              </label>
              <input type="email" id="edit_email" name="email" required readonly
                     class="w-full px-4 py-3 border border-gray-200 rounded-xl bg-gray-50 text-gray-600 cursor-not-allowed"
                     title="Ce champ ne peut pas être modifié">
            </div>

            <div>
              <label for="edit_telephone" class="block text-sm font-semibold text-gray-700 mb-2">
                <span class="flex items-center">
                  <svg class="w-4 h-4 mr-2 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"/>
                  </svg>
                  Téléphone *
                  <svg class="w-4 h-4 ml-2 text-gray-400" fill="currentColor" viewBox="0 0 20 20" title="Non modifiable">
                    <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                  </svg>
                </span>
              </label>
              <input type="tel" id="edit_telephone" name="telephone" required readonly
                     class="w-full px-4 py-3 border border-gray-200 rounded-xl bg-gray-50 text-gray-600 cursor-not-allowed"
                     title="Ce champ ne peut pas être modifié">
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
            <div class="md:col-span-2">
  <label for="edit_message" class="block text-sm font-semibold text-gray-700 mb-2">
    <span class="flex items-center">
      <svg class="w-4 h-4 mr-2 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7zM7 9H5v2h2V9zm8 0h-2v2h2V9zM9 9h2v2H9V9z" clip-rule="evenodd"/>
      </svg>
      Message du client
    </span>
  </label>
  <textarea id="edit_message" name="message" rows="4"
           class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 hover:border-gray-400"
           placeholder="Message ou demandes spéciales du client..."></textarea>
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
          <!-- Dans le modal d'ajout -->
<div class="col-md-12">
  <label for="message" class="block text-sm font-semibold text-gray-700 mb-2">
    Message
  </label>
  <textarea id="message" name="message" class="px-4 py-3 border border-gray-300 rounded-xl w-full focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-all duration-200 hover:border-gray-400"></textarea>
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
<!-- MODAL DE VISUALISATION MODIFIÉ AVEC LE MESSAGE -->
<div id="viewReservationModal" class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm flex items-center justify-center p-4 z-50 hidden">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-5xl max-h-[90vh] overflow-y-auto animate-slide-up">
    <div class="p-8">
      <div class="flex justify-between items-center mb-6">
        <div>
          <h3 class="text-2xl font-bold bg-gradient-to-r from-gray-900 to-gray-600 bg-clip-text text-transparent">
            Détails de la réservation
          </h3>
          <p class="text-gray-600 mt-1">Informations complètes de la réservation</p>
        </div>
        <button onclick="closeViewModal()" class="p-2 text-gray-400 hover:text-gray-500 hover:bg-gray-100 rounded-full transition-all duration-200">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Informations client -->
        <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-xl p-6 border border-blue-100">
          <div class="flex items-center mb-4">
            <div class="p-2 bg-gradient-to-r from-blue-500 to-indigo-600 rounded-lg">
              <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
              </svg>
            </div>
            <h4 class="text-lg font-semibold text-gray-800 ml-3">Informations Client</h4>
          </div>

          <div class="space-y-4">
            <div class="flex items-center p-3 bg-white rounded-lg shadow-sm">
              <div class="w-10 h-10 bg-gradient-to-r from-emerald-400 to-teal-500 rounded-full flex items-center justify-center mr-3">
                <span id="view_nom_initial" class="text-white font-bold text-sm"></span>
              </div>
              <div>
                <p class="text-xs text-gray-500 uppercase tracking-wide">Nom complet</p>
                <p id="view_nom" class="text-sm font-semibold text-gray-900"></p>
              </div>
            </div>

            <div class="flex items-center p-3 bg-white rounded-lg shadow-sm">
              <div class="w-10 h-10 bg-gradient-to-r from-blue-400 to-purple-500 rounded-full flex items-center justify-center mr-3">
                <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                  <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/>
                  <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/>
                </svg>
              </div>
              <div>
                <p class="text-xs text-gray-500 uppercase tracking-wide">Adresse email</p>
                <p id="view_email" class="text-sm font-semibold text-gray-900"></p>
              </div>
            </div>

            <div class="flex items-center p-3 bg-white rounded-lg shadow-sm">
              <div class="w-10 h-10 bg-gradient-to-r from-green-400 to-blue-500 rounded-full flex items-center justify-center mr-3">
                <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                  <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"/>
                </svg>
              </div>
              <div>
                <p class="text-xs text-gray-500 uppercase tracking-wide">Téléphone</p>
                <p id="view_telephone" class="text-sm font-semibold text-gray-900"></p>
              </div>
            </div>
          </div>
        </div>

        <!-- Informations réservation -->
        <div class="bg-gradient-to-br from-emerald-50 to-teal-50 rounded-xl p-6 border border-emerald-100">
          <div class="flex items-center mb-4">
            <div class="p-2 bg-gradient-to-r from-emerald-500 to-teal-600 rounded-lg">
              <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/>
              </svg>
            </div>
            <h4 class="text-lg font-semibold text-gray-800 ml-3">Détails Réservation</h4>
          </div>

          <div class="space-y-4">
            <div class="flex items-center p-3 bg-white rounded-lg shadow-sm">
              <div class="w-10 h-10 bg-gradient-to-r from-purple-400 to-pink-500 rounded-full flex items-center justify-center mr-3">
                <span id="view_id_display" class="text-white font-bold text-sm"></span>
              </div>
              <div>
                  <p class="text-xs text-gray-500 uppercase tracking-wide">N° Réservation</p>
                  <p id="view_id" class="text-sm font-semibold text-gray-900"></p>
              </div>
              </div>

            <div class="flex items-center p-3 bg-white rounded-lg shadow-sm">
              <div class="w-10 h-10 bg-gradient-to-r from-blue-400 to-indigo-500 rounded-full flex items-center justify-center mr-3">
                <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/>
                </svg>
              </div>
              <div>
                <p class="text-xs text-gray-500 uppercase tracking-wide">Date de réservation</p>
                <p id="view_date_reservation" class="text-sm font-semibold text-gray-900"></p>
              </div>
            </div>

            <div class="flex items-center p-3 bg-white rounded-lg shadow-sm">
              <div class="w-10 h-10 bg-gradient-to-r from-orange-400 to-red-500 rounded-full flex items-center justify-center mr-3">
                <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
                </svg>
              </div>
              <div>
                <p class="text-xs text-gray-500 uppercase tracking-wide">Heure de réservation</p>
                <p id="view_heure_reservation" class="text-sm font-semibold text-gray-900"></p>
              </div>
            </div>

            <div class="flex items-center p-3 bg-white rounded-lg shadow-sm">
              <div class="w-10 h-10 bg-gradient-to-r from-teal-400 to-cyan-500 rounded-full flex items-center justify-center mr-3">
                <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                  <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/>
                </svg>
              </div>
              <div>
                <p class="text-xs text-gray-500 uppercase tracking-wide">Nombre de personnes</p>
                <p id="view_personnes" class="text-sm font-semibold text-gray-900"></p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- NOUVELLE SECTION MESSAGE -->
      <div class="mt-8 bg-gradient-to-br from-amber-50 to-orange-50 rounded-xl p-6 border border-amber-100">
        <div class="flex items-center mb-4">
          <div class="p-2 bg-gradient-to-r from-amber-500 to-orange-600 rounded-lg">
            <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7zM7 9H5v2h2V9zm8 0h-2v2h2V9zM9 9h2v2H9V9z" clip-rule="evenodd"/>
            </svg>
          </div>
          <h4 class="text-lg font-semibold text-gray-800 ml-3">Message du client</h4>
        </div>

        <div class="bg-white rounded-lg p-4 shadow-sm min-h-[100px]">
          <div id="view_message_content">
            <!-- Le contenu du message sera injecté ici -->
          </div>
        </div>
      </div>

      <!-- Informations système -->
      <div class="mt-8 bg-gradient-to-br from-gray-50 to-slate-50 rounded-xl p-6 border border-gray-100">
        <div class="flex items-center mb-4">
          <div class="p-2 bg-gradient-to-r from-gray-500 to-slate-600 rounded-lg">
            <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M6 2a2 2 0 00-2 2v12a2 2 0 002 2h8a2 2 0 002-2V4a2 2 0 00-2-2H6zm1 2a1 1 0 000 2h6a1 1 0 100-2H7zm6 7a1 1 0 01-1 1H8a1 1 0 110-2h4a1 1 0 011 1zm-1 4a1 1 0 100-2H8a1 1 0 100 2h4z" clip-rule="evenodd"/>
            </svg>
          </div>
          <h4 class="text-lg font-semibold text-gray-800 ml-3">Informations Système</h4>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div class="flex items-center p-3 bg-white rounded-lg shadow-sm">
            <div class="w-8 h-8 bg-gradient-to-r from-yellow-400 to-orange-500 rounded-full flex items-center justify-center mr-3">
              <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"/>
              </svg>
            </div>
            <div>
              <p class="text-xs text-gray-500 uppercase tracking-wide">Statut</p>
              <p id="view_statut" class="text-sm font-semibold text-gray-900"></p>
            </div>
          </div>

          <div class="flex items-center p-3 bg-white rounded-lg shadow-sm">
            <div class="w-8 h-8 bg-gradient-to-r from-indigo-400 to-purple-500 rounded-full flex items-center justify-center mr-3">
              <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
              </svg>
            </div>
            <div>
              <p class="text-xs text-gray-500 uppercase tracking-wide">Date d'envoi</p>
              <p id="view_date_envoi" class="text-sm font-semibold text-gray-900"></p>
            </div>
          </div>

          <div class="flex items-center p-3 bg-white rounded-lg shadow-sm">
            <div class="w-8 h-8 bg-gradient-to-r from-pink-400 to-red-500 rounded-full flex items-center justify-center mr-3">
              <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 2L3 7v11a1 1 0 001 1h3a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1h3a1 1 0 001-1V7l-7-5z" clip-rule="evenodd"/>
              </svg>
            </div>
            <div>
              <p class="text-xs text-gray-500 uppercase tracking-wide">Source</p>
              <p class="text-sm font-semibold text-gray-900">Site Web</p>
            </div>
          </div>
        </div>
      </div>

      <div class="flex justify-end pt-6 border-t border-gray-100 mt-6">
        <button onclick="closeViewModal()"
                class="px-6 py-3 bg-gradient-to-r from-gray-600 to-gray-700 hover:from-gray-700 hover:to-gray-800 text-white font-medium rounded-xl transition-all duration-200 transform hover:scale-105 shadow-lg hover:shadow-xl">
          Fermer
        </button>
      </div>
    </div>
  </div>
</div>

    </div>
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
        <p class="text-gray-600 mb-2">Vous êtes sur le point de supprimer définitivement la réservation :</p>
        <div class="bg-gray-50 rounded-lg p-3 mb-4 border border-gray-200">
          <p class="font-medium text-gray-800" id="deleteReservationInfo"></p>
        </div>
        <p class="text-red-600 text-sm font-medium mb-6">Cette action est irréversible !</p>
        <div class="flex space-x-3">
          <button onclick="closeDeleteModal()"
                  class="flex-1 px-4 py-2 bg-gray-200 text-gray-800 rounded-lg font-medium hover:bg-gray-300 transition-colors">
            Annuler
          </button>
          <button onclick="deleteReservation()"
                  id="confirmDeleteBtn"
                  class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg font-medium hover:bg-red-700 transition-colors">
            Supprimer
          </button>
        </div>
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

      // AJOUT : Récupération du message
      document.getElementById('edit_message').value = data.message || '';

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


// Gestion du modal de visualisation
function openViewModal(reservationId) {
  fetch('get_reservation.php?id=' + reservationId)
    .then(response => response.json())
    .then(data => {
      // Remplir les informations client
    // Calculer le numéro de la réservation basé sur sa position dans le tableau
const reservationRow = document.querySelector(`tr[data-reservation-id="${reservationId}"]`);
const numeroElement = reservationRow.querySelector('td:first-child .text-white');
const numeroReservation = numeroElement.textContent;

      document.getElementById('view_id').textContent = numeroReservation;
      document.getElementById('view_id_display').textContent = numeroReservation;
      document.getElementById('view_nom').textContent = data.nom;
      document.getElementById('view_nom_initial').textContent = data.nom.charAt(0).toUpperCase();
      document.getElementById('view_email').textContent = data.email;
      document.getElementById('view_telephone').textContent = data.telephone;
      document.getElementById('view_personnes').textContent = data.personnes;
      document.getElementById('view_date_reservation').textContent = formatDate(data.date_reservation);
      document.getElementById('view_heure_reservation').textContent = data.heure_reservation || 'Non spécifiée';

      // GESTION DU MESSAGE
      const messageContent = document.getElementById('view_message_content');
      if (data.message && data.message.trim() !== '') {
        messageContent.innerHTML = `
          <div class="flex items-start">
            <svg class="w-5 h-5 text-amber-500 mr-3 mt-1 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7zM7 9H5v2h2V9zm8 0h-2v2h2V9zM9 9h2v2H9V9z" clip-rule="evenodd"/>
            </svg>
            <div class="text-gray-700 leading-relaxed whitespace-pre-wrap">${escapeHtml(data.message)}</div>
          </div>
        `;
      } else {
        messageContent.innerHTML = `
          <div class="flex items-center justify-center h-16 text-gray-400">
            <svg class="w-8 h-8 mr-2" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
            </svg>
            <span class="text-lg font-medium italic">Aucun message laissé par le client</span>
          </div>
        `;
      }

      // Statut avec formatage
      const statutElement = document.getElementById('view_statut');
      if (data.statut === 'non_lu') {
        statutElement.innerHTML = '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-800">🔔 Non lu</span>';
      } else {
        statutElement.innerHTML = '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">✅ Lu</span>';
      }

      // Date d'envoi formatée
      document.getElementById('view_date_envoi').textContent = formatDateTime(data.date_envoi);

      document.getElementById('viewReservationModal').classList.remove('hidden');
    })
    .catch(error => {
      console.error('Error:', error);
      alert('Une erreur est survenue lors du chargement des données');
    });
}

function closeViewModal() {
  document.getElementById('viewReservationModal').classList.add('hidden');
}

document.getElementById('viewReservationModal').addEventListener('click', function(e) {
  if (e.target === this) {
    closeViewModal();
  }
});

// Fonction utilitaire pour échapper le HTML
function escapeHtml(text) {
  const map = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;'
  };
  return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}

// Fonction utilitaire pour formater la date
function formatDate(dateString) {
  const date = new Date(dateString);
  return date.toLocaleDateString('fr-FR', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  });
}

// Fonction utilitaire pour formater la date et l'heure
function formatDateTime(dateString) {
  const date = new Date(dateString);
  return date.toLocaleDateString('fr-FR', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
}
    // Vérifier toutes les 2 minutes
    setInterval(checkNewReservations, 120000);
    document.addEventListener('DOMContentLoaded', checkNewReservations);
// Version alternative plus robuste de l'export PDF
async function exportToPDFAsync() {
    try {
        showLoadingIndicator();

        // Construire l'URL avec les paramètres
        const urlParams = new URLSearchParams(window.location.search);
        urlParams.set('format', 'pdf'); // S'assurer que format=pdf est présent

        const exportUrl = 'export_reservations.php?' + urlParams.toString();
        console.log('URL d\'export:', exportUrl); // Debug

        // Méthode iframe (plus fiable pour les téléchargements)
        const iframe = document.createElement('iframe');
        iframe.style.display = 'none';
        iframe.src = exportUrl;

        document.body.appendChild(iframe);

        // Nettoyer après délai
        setTimeout(() => {
            if (iframe.parentNode) {
                document.body.removeChild(iframe);
            }
            hideLoadingIndicator();
            showSuccessMessage('Export PDF lancé...');
        }, 3000);

    } catch (error) {
        console.error('Erreur export:', error);
        hideLoadingIndicator();
        showErrorMessage('Erreur lors de l\'export: ' + error.message);
    }
}

// Méthode de fallback
function fallbackDownload(url) {
    const link = document.createElement('a');
    link.href = url;
    link.download = `reservations_${new Date().toISOString().split('T')[0]}.pdf`;
    link.target = '_blank';
    link.style.display = 'none';

    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}



// Fonctions utilitaires (à ajouter si manquantes)
function showLoadingIndicator() {
    if (document.getElementById('loadingIndicator')) return;

    const indicator = document.createElement('div');
    indicator.id = 'loadingIndicator';
    indicator.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
    indicator.innerHTML = `
        <div class="bg-white rounded-xl p-6 shadow-2xl">
            <div class="flex items-center">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mr-4"></div>
                <span class="text-lg font-medium text-gray-700">Export en cours...</span>
            </div>
        </div>
    `;
    document.body.appendChild(indicator);
}

function hideLoadingIndicator() {
    const indicator = document.getElementById('loadingIndicator');
    if (indicator) {
        indicator.remove();
    }
}

function showSuccessMessage(message) {
    showMessage(message, 'success');
}

function showErrorMessage(message) {
    showMessage(message, 'error');
}

function showMessage(message, type) {
    const toast = document.createElement('div');
    const bgColor = type === 'success' ? 'from-green-600 to-emerald-600' : 'from-red-600 to-rose-600';
    const icon = type === 'success' ?
        `<svg class="w-5 h-5 mr-3" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
        </svg>` :
        `<svg class="w-5 h-5 mr-3" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
        </svg>`;

    toast.className = `fixed bottom-4 right-4 bg-gradient-to-r ${bgColor} text-white px-6 py-4 rounded-xl shadow-2xl transform transition-all duration-300 animate-slide-up z-50`;
    toast.innerHTML = `
        <div class="flex items-center">
            ${icon}
            <span class="font-medium">${message}</span>
        </div>
    `;

    document.body.appendChild(toast);

    setTimeout(() => {
        toast.style.transform = 'translateX(100%)';
        setTimeout(() => {
            if (toast.parentNode) {
                toast.remove();
            }
        }, 300);
    }, 5000);
}
// Animation au chargement des cartes dashboard
document.addEventListener('DOMContentLoaded', function() {
    const dashboardCards = document.querySelectorAll('.dashboard-card');
    dashboardCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        setTimeout(() => {
            card.style.transition = 'all 0.6s cubic-bezier(0.4, 0, 0.2, 1)';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });

    // Effet hover sur les icônes
    dashboardCards.forEach(card => {
        const icon = card.querySelector('.icon-wrapper');
        if (icon) {
            card.addEventListener('mouseenter', () => {
                icon.style.transform = 'scale(1.15) rotate(10deg)';
                icon.style.transition = 'transform 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55)';
            });
            card.addEventListener('mouseleave', () => {
                icon.style.transform = 'scale(1) rotate(0deg)';
            });
        }
    });

    // Animation des chiffres (compteur)
    const numberElements = document.querySelectorAll('.dashboard-card .text-3xl');
    numberElements.forEach(element => {
        const text = element.textContent.trim();
        const finalNumber = parseFloat(text);
        
        if (!isNaN(finalNumber) && finalNumber > 0) {
            const duration = 1500;
            const steps = 50;
            const increment = finalNumber / steps;
            let current = 0;
            let step = 0;

            const timer = setInterval(() => {
                current += increment;
                step++;
                
                if (step >= steps) {
                    current = finalNumber;
                    clearInterval(timer);
                }
                
                // Formater selon le nombre (entier ou décimal)
                const formatted = finalNumber % 1 === 0 ? 
                    Math.floor(current) : 
                    current.toFixed(1);
                    
                element.textContent = formatted;
            }, duration / steps);
        }
    });
});

// Onglets
function switchTab(tab) {
    document.querySelectorAll('.tab-button').forEach(btn => {
        btn.classList.remove('border-blue-500', 'text-blue-600');
        btn.classList.add('border-transparent', 'text-gray-500');
    });
    document.getElementById(`tab-${tab}`).classList.remove('border-transparent', 'text-gray-500');
    document.getElementById(`tab-${tab}`).classList.add('border-blue-500', 'text-blue-600');

    document.getElementById('content-actives').classList.toggle('hidden', tab !== 'actives');
    document.getElementById('content-historique').classList.toggle('hidden', tab !== 'historique');
}

// Filtres historique
function applyHistFilters() {
    const dateDebut = document.getElementById('hist-date-debut').value;
    const dateFin = document.getElementById('hist-date-fin').value;
    const statut = document.getElementById('hist-statut').value;
    const search = document.getElementById('hist-search').value.toLowerCase();

    const rows = document.querySelectorAll('#table-historique tbody tr');
    let total = 0;
    let totalPersonnes = 0;
    let max = 0;

    rows.forEach(row => {
        let show = true;

        if (dateDebut && row.dataset.date < dateDebut) show = false;
        if (dateFin && row.dataset.date > dateFin) show = false;
        if (statut && row.dataset.statut !== statut) show = false;
        if (search && !row.dataset.nom.includes(search) && !row.dataset.email.includes(search)) show = false;

        if (show) {
            row.style.display = '';
            total++;
            const personnes = parseInt(row.cells[5].textContent);
            totalPersonnes += personnes;
            if (personnes > max) max = personnes;
        } else {
            row.style.display = 'none';
        }
    });

    document.getElementById('hist-total').textContent = total;
    document.getElementById('hist-personnes').textContent = totalPersonnes;
    document.getElementById('hist-moyenne').textContent = total > 0 ? (totalPersonnes / total).toFixed(1) : 0;
    document.getElementById('hist-max').textContent = max;
}

function resetHistFilters() {
    document.getElementById('hist-date-debut').value = '';
    document.getElementById('hist-date-fin').value = '';
    document.getElementById('hist-statut').value = '';
    document.getElementById('hist-search').value = '';
    applyHistFilters();
}

function viewHistDetails(resa) {
    const modalHTML = `
        <div class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center" onclick="this.remove()">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl mx-4 p-8" onclick="event.stopPropagation()">
                <div class="flex justify-between items-center mb-6 border-b pb-4">
                    <h3 class="text-2xl font-bold text-gray-800">Détails Réservation #${resa.id}</h3>
                    <button onclick="this.closest('.fixed').remove()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-500 font-semibold">Client</p>
                            <p class="text-lg font-medium">${resa.nom}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 font-semibold">Personnes</p>
                            <p class="text-lg font-semibold text-purple-600">${resa.personnes}</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-500 font-semibold">Email</p>
                            <p class="text-lg">${resa.email}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 font-semibold">Téléphone</p>
                            <p class="text-lg">${resa.telephone}</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-500 font-semibold">Date</p>
                            <p class="text-lg">${new Date(resa.date_reservation).toLocaleDateString('fr-FR')}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 font-semibold">Heure</p>
                            <p class="text-lg font-semibold text-blue-600">${resa.heure_reservation}</p>
                        </div>
                    </div>
                    ${resa.message ? `
                    <div class="border-t pt-4">
                        <p class="text-sm text-gray-500 font-semibold mb-2">Message</p>
                        <p class="text-gray-700 bg-gray-50 p-3 rounded">${resa.message}</p>
                    </div>
                    ` : ''}
                </div>
                <div class="mt-6 flex justify-end">
                    <button onclick="this.closest('.fixed').remove()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                        Fermer
                    </button>
                </div>
            </div>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', modalHTML);
}

// Variables pour la suppression
let reservationToDelete = null;

// Fonction pour afficher le modal de confirmation de suppression
function confirmDelete(id, nomClient, dateReservation) {
    reservationToDelete = id;

    const modal = document.getElementById('deleteModal');
    document.getElementById('deleteReservationInfo').textContent = `${nomClient} - ${dateReservation}`;

    // S'assurer que le bouton est dans son état normal
    const confirmBtn = document.getElementById('confirmDeleteBtn');
    confirmBtn.innerHTML = 'Supprimer';
    confirmBtn.disabled = false;

    modal.classList.remove('hidden');
}

// Fonction pour fermer le modal de suppression
function closeDeleteModal() {
    const modal = document.getElementById('deleteModal');
    modal.classList.add('hidden');

    // Réinitialiser
    reservationToDelete = null;
    document.getElementById('deleteReservationInfo').textContent = '';

    const confirmBtn = document.getElementById('confirmDeleteBtn');
    confirmBtn.innerHTML = 'Supprimer';
    confirmBtn.disabled = false;
}

// Fonction pour supprimer la réservation via AJAX
function deleteReservation() {
    if (!reservationToDelete) return;

    const confirmBtn = document.getElementById('confirmDeleteBtn');
    const originalText = confirmBtn.innerHTML;

    // Animation de chargement
    confirmBtn.innerHTML = `
        <i class="fas fa-spinner fa-spin mr-2"></i>
        Suppression...
    `;
    confirmBtn.disabled = true;

    const reservationId = reservationToDelete;

    // Requête AJAX
    fetch('reservations.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=supprimer&id=${reservationId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Trouver et supprimer la ligne du tableau
            const rows = document.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                if (cells.length > 0) {
                    // Chercher le bouton de suppression qui contient l'ID
                    const deleteBtn = row.querySelector(`button[onclick*="confirmDelete(${reservationId}"]`);
                    if (deleteBtn) {
                        row.style.opacity = '0';
                        row.style.transform = 'translateX(-100%)';
                        row.style.transition = 'all 0.3s ease';

                        setTimeout(() => {
                            row.remove();

                            // Vérifier s'il reste des réservations
                            const remainingRows = document.querySelectorAll('tbody tr');
                            if (remainingRows.length === 0) {
                                location.reload();
                            }
                        }, 300);
                    }
                }
            });

            closeDeleteModal();

            // Message de succès (optionnel - vous pouvez ajouter un toast)
            console.log('Réservation supprimée avec succès');
        } else {
            alert('Erreur: ' + data.message);
            confirmBtn.innerHTML = originalText;
            confirmBtn.disabled = false;
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        alert('Erreur de connexion');
        confirmBtn.innerHTML = originalText;
        confirmBtn.disabled = false;
    });
}

// Live clock update
setInterval(() => {
    const now = new Date();
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    const seconds = String(now.getSeconds()).padStart(2, '0');
    const clockElement = document.getElementById('live-clock');
    if (clockElement) {
        clockElement.textContent = `${hours}:${minutes}:${seconds}`;
    }
}, 1000);

// ========== SYSTÈME DE NOTIFICATIONS ==========

let dernierNombreReservations = <?php echo $nombre_nouvelles; ?>;
let notificationSoundPlayed = false;

// Créer un son de notification (beep)
function playNotificationSound() {
    // Utiliser l'API Web Audio pour créer un son
    try {
        const audioContext = new (window.AudioContext || window.webkitAudioContext)();
        const oscillator = audioContext.createOscillator();
        const gainNode = audioContext.createGain();

        oscillator.connect(gainNode);
        gainNode.connect(audioContext.destination);

        oscillator.frequency.value = 800;
        oscillator.type = 'sine';

        gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
        gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.5);

        oscillator.start(audioContext.currentTime);
        oscillator.stop(audioContext.currentTime + 0.5);
    } catch (e) {
        console.log('Notification sonore non disponible');
    }
}

// Formater la date en français
function formatDateFr(dateString) {
    const date = new Date(dateString);
    const options = { day: '2-digit', month: 'short', year: 'numeric' };
    return date.toLocaleDateString('fr-FR', options);
}

// Formater l'heure
function formatHeure(heureString) {
    return heureString.substring(0, 5);
}

// Mettre à jour les notifications
function updateNotifications() {
    fetch('get_nouvelles_reservations.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const nombreNouvelles = data.nombre_nouvelles;
                const reservationsAujourdhui = data.reservations_aujourdhui;
                const dernieresReservations = data.dernieres_reservations;

                // Mettre à jour les badges
                const badge = document.getElementById('notification-badge');
                const countSpan = document.getElementById('notification-count');
                const todayCount = document.getElementById('today-count');

                if (nombreNouvelles > 0) {
                    badge.textContent = nombreNouvelles;
                    badge.classList.remove('hidden');
                    countSpan.textContent = nombreNouvelles;

                    // Si le nombre a augmenté, jouer le son
                    if (nombreNouvelles > dernierNombreReservations) {
                        playNotificationSound();
                        showToast('Nouvelle réservation reçue !', 'success');
                    }
                } else {
                    badge.classList.add('hidden');
                    countSpan.textContent = '0';
                }

                dernierNombreReservations = nombreNouvelles;

                // Mettre à jour le compteur des réservations du jour
                todayCount.textContent = reservationsAujourdhui.length;

                // Afficher les réservations du jour
                const todayContainer = document.getElementById('today-reservations');
                if (reservationsAujourdhui.length > 0) {
                    todayContainer.innerHTML = reservationsAujourdhui.map(res => `
                        <div class="bg-slate-700 rounded-lg p-3 hover:bg-slate-600 transition-colors border border-teal-500/30">
                            <div class="flex items-center justify-between mb-2">
                                <span class="font-semibold text-white text-sm">${res.nom}</span>
                                <span class="text-teal-400 font-bold text-sm">${formatHeure(res.heure_reservation)}</span>
                            </div>
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-gray-400">${res.personnes} pers.</span>
                                <span class="text-gray-400">${res.telephone}</span>
                            </div>
                            ${res.message ? `<div class="mt-2 text-xs text-gray-300 italic">"${res.message.substring(0, 50)}${res.message.length > 50 ? '...' : ''}"</div>` : ''}
                        </div>
                    `).join('');
                } else {
                    todayContainer.innerHTML = `
                        <div class="text-center py-4 text-gray-400 text-sm">
                            <i class="fas fa-calendar-times mb-2"></i>
                            <p>Aucune réservation aujourd'hui</p>
                        </div>
                    `;
                }

                // Afficher les nouvelles réservations
                const newContainer = document.getElementById('new-reservations');
                if (dernieresReservations.length > 0) {
                    newContainer.innerHTML = dernieresReservations.map(res => `
                        <div class="bg-slate-700 rounded-lg p-3 hover:bg-slate-600 transition-colors border-l-4 border-amber-500">
                            <div class="flex items-center justify-between mb-2">
                                <span class="font-semibold text-white text-sm">${res.nom}</span>
                                <span class="bg-amber-500 text-white px-2 py-0.5 rounded text-xs font-bold">NEW</span>
                            </div>
                            <div class="text-xs text-gray-400 mb-1">
                                <i class="fas fa-calendar mr-1"></i>${formatDateFr(res.date_reservation)} à ${formatHeure(res.heure_reservation)}
                            </div>
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-gray-400"><i class="fas fa-users mr-1"></i>${res.personnes} personnes</span>
                                <span class="text-gray-400">${res.telephone}</span>
                            </div>
                        </div>
                    `).join('');
                } else {
                    newContainer.innerHTML = `
                        <div class="text-center py-4 text-gray-400 text-sm">
                            <i class="fas fa-check-circle mb-2"></i>
                            <p>Tout est à jour !</p>
                        </div>
                    `;
                }

                // Afficher un rappel si des réservations sont prévues aujourd'hui
                if (reservationsAujourdhui.length > 0 && !notificationSoundPlayed) {
                    showToast(`📅 ${reservationsAujourdhui.length} réservation(s) prévue(s) aujourd'hui`, 'info');
                    notificationSoundPlayed = true;
                }
            }
        })
        .catch(error => {
            console.error('Erreur lors de la récupération des notifications:', error);
        });
}

// Afficher un toast de notification
function showToast(message, type = 'info') {
    // Créer le toast s'il n'existe pas
    let toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toast-container';
        toastContainer.className = 'fixed top-20 right-4 z-50 space-y-2';
        document.body.appendChild(toastContainer);
    }

    const colors = {
        success: 'from-green-500 to-emerald-600',
        info: 'from-blue-500 to-indigo-600',
        warning: 'from-amber-500 to-orange-600',
        error: 'from-red-500 to-rose-600'
    };

    const icons = {
        success: 'fa-check-circle',
        info: 'fa-info-circle',
        warning: 'fa-exclamation-triangle',
        error: 'fa-times-circle'
    };

    const toast = document.createElement('div');
    toast.className = `bg-gradient-to-r ${colors[type]} text-white px-6 py-4 rounded-xl shadow-2xl transform transition-all duration-300 flex items-center space-x-3 min-w-[300px] animate-slide-in`;
    toast.innerHTML = `
        <i class="fas ${icons[type]} text-2xl"></i>
        <span class="font-medium">${message}</span>
    `;

    toastContainer.appendChild(toast);

    // Animer l'entrée
    setTimeout(() => {
        toast.style.transform = 'translateX(0)';
        toast.style.opacity = '1';
    }, 10);

    // Supprimer après 5 secondes
    setTimeout(() => {
        toast.style.transform = 'translateX(400px)';
        toast.style.opacity = '0';
        setTimeout(() => {
            toast.remove();
        }, 300);
    }, 5000);
}

// Ouvrir le modal de visualisation en cliquant sur la ligne
function ouvrirModalDepuisLigne(event, reservationId) {
    // Ne pas ouvrir le modal si on clique sur un bouton
    if (event.target.closest('button')) {
        return;
    }

    // Ouvrir le modal de visualisation
    openViewModal(reservationId);
}

// Fermer l'info-bulle et sauvegarder la préférence
function fermerInfoBulle() {
    const infoBulle = document.getElementById('info-bulle-ligne');
    if (infoBulle) {
        infoBulle.style.opacity = '0';
        infoBulle.style.transform = 'translateY(-20px)';
        setTimeout(() => {
            infoBulle.remove();
        }, 300);

        // Sauvegarder la préférence
        localStorage.setItem('infoBulleLigneFermee', 'true');
    }
}

// Vérifier au chargement si l'info-bulle doit être cachée
document.addEventListener('DOMContentLoaded', function() {
    const infoBulleFermee = localStorage.getItem('infoBulleLigneFermee');
    if (infoBulleFermee === 'true') {
        const infoBulle = document.getElementById('info-bulle-ligne');
        if (infoBulle) {
            infoBulle.style.display = 'none';
        }
    }
});

// Marquer toutes les réservations comme lues
function marquerToutCommeLu() {
    if (!confirm('Voulez-vous marquer toutes les réservations comme lues ?')) {
        return;
    }

    fetch('marquer_lu.php', {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            // Mettre à jour les notifications
            updateNotifications();
            // Rafraîchir la page après 1 seconde
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showToast('Erreur : ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        showToast('Erreur de connexion', 'error');
    });
}

// Mettre à jour les notifications au chargement de la page
document.addEventListener('DOMContentLoaded', function() {
    updateNotifications();

    // Mettre à jour toutes les 30 secondes
    setInterval(updateNotifications, 30000);
});

// Ajouter les styles pour l'animation du toast
const style = document.createElement('style');
style.textContent = `
    @keyframes slide-in {
        from {
            transform: translateX(400px);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    .animate-slide-in {
        animation: slide-in 0.3s ease-out;
    }
`;
document.head.appendChild(style);

  </script>

  <!-- Footer -->
  <?php include 'footer.php'; ?>
</body>
</html>