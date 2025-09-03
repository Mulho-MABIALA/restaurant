<?php
    session_start();
    require_once __DIR__ . '/../vendor/autoload.php';
    require_once '../config.php';

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

    // Pagination & filtres
    $search           = $_GET['search'] ?? '';
    $date_filter      = $_GET['date_filter'] ?? '';
    $personnes_filter = $_GET['personnes_filter'] ?? '';

    // Marquer toutes les réservations comme lues (optionnel)
    $conn->query("UPDATE reservations SET statut = 'lu' WHERE statut = 'non_lu'");

    // Suppression
    if (isset($_GET['delete'])) {
        $id   = (int) $_GET['delete'];
        $stmt = $conn->prepare("DELETE FROM reservations WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: reservations.php");
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

    // Récupération paginée - MODIFIÉ : Tri par date_envoi DESC pour avoir les plus récentes en haut
    $queryWithLimit = $query . " ORDER BY date_envoi DESC, id DESC";
    $stmt           = $conn->prepare($queryWithLimit);
    $stmt->execute($params);
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <?php echo $total_global?> réservations au total
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

<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
    <!-- Carte 1: Total réservations -->
    <div class="bg-gradient-to-br from-blue-50 to-white rounded-2xl shadow-[0_10px_30px_-15px_rgba(0,0,0,0.1)] overflow-hidden border-2 border-blue-200 transition-all duration-300 hover:shadow-[0_15px_40px_-10px_rgba(59,130,246,0.3)] hover:-translate-y-1 group">
        <div class="p-6 relative z-10">
            <div class="flex justify-between items-start mb-4">
                <div class="bg-blue-500/10 p-2.5 rounded-xl backdrop-blur-sm border border-blue-200">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                    </svg>
                </div>
                <div class="text-xs font-semibold bg-blue-600/10 text-blue-700 px-2 py-1 rounded-full">
                    TOTAL
                </div>
            </div>

            <div class="text-3xl font-bold text-blue-800 mb-1"><?php echo $total_global?></div>
            <div class="text-sm text-blue-600/80">Toutes les réservations</div>

            <div class="absolute bottom-4 right-4 opacity-10 group-hover:opacity-20 transition-opacity">
                <svg class="w-16 h-16 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                </svg>
            </div>
        </div>
        <div class="w-full h-1.5 bg-gradient-to-r from-blue-400 to-blue-600"></div>
    </div>

    <!-- Carte 2: Nouvelles réservations -->
    <div class="bg-gradient-to-br from-emerald-50 to-white rounded-2xl shadow-[0_10px_30px_-15px_rgba(0,0,0,0.1)] overflow-hidden border-2 border-emerald-200 transition-all duration-300 hover:shadow-[0_15px_40px_-10px_rgba(16,185,129,0.3)] hover:-translate-y-1 group">
        <div class="p-6 relative z-10">
            <div class="flex justify-between items-start mb-4">
                <div class="bg-emerald-500/10 p-2.5 rounded-xl backdrop-blur-sm border border-emerald-200">
                    <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                    </svg>
                </div>
                <div class="text-xs font-semibold bg-emerald-600/10 text-emerald-700 px-2 py-1 rounded-full">
                    NOUVELLES
                </div>
            </div>

            <div class="text-3xl font-bold text-emerald-800 mb-1"><?php echo $nombre_nouvelles?></div>
            <div class="text-sm text-emerald-600/80">À traiter</div>

            <div class="absolute bottom-4 right-4 opacity-10 group-hover:opacity-20 transition-opacity">
                <svg class="w-16 h-16 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                </svg>
            </div>
        </div>
        <div class="w-full h-1.5 bg-gradient-to-r from-emerald-400 to-emerald-600"></div>
    </div>

    <!-- Carte 3: Aujourd'hui -->
    <div class="bg-gradient-to-br from-amber-50 to-white rounded-2xl shadow-[0_10px_30px_-15px_rgba(0,0,0,0.1)] overflow-hidden border-2 border-amber-200 transition-all duration-300 hover:shadow-[0_15px_40px_-10px_rgba(245,158,11,0.3)] hover:-translate-y-1 group">
        <div class="p-6 relative z-10">
            <div class="flex justify-between items-start mb-4">
                <div class="bg-amber-500/10 p-2.5 rounded-xl backdrop-blur-sm border border-amber-200">
                    <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                </div>
                <div class="text-xs font-semibold bg-amber-600/10 text-amber-700 px-2 py-1 rounded-full">
                    AUJOURD'HUI
                </div>
            </div>

            <div class="text-3xl font-bold text-amber-800 mb-1"><?php echo $reservations_aujourdhui?></div>
            <div class="text-sm text-amber-600/80"><?php echo date('d M Y')?></div>

            <div class="absolute bottom-4 right-4 opacity-10 group-hover:opacity-20 transition-opacity">
                <svg class="w-16 h-16 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
            </div>
        </div>
        <div class="w-full h-1.5 bg-gradient-to-r from-amber-400 to-amber-600"></div>
    </div>

    <!-- Carte 4: Moyenne personnes -->
    <div class="bg-gradient-to-br from-violet-50 to-white rounded-2xl shadow-[0_10px_30px_-15px_rgba(0,0,0,0.1)] overflow-hidden border-2 border-violet-200 transition-all duration-300 hover:shadow-[0_15px_40px_-10px_rgba(139,92,246,0.3)] hover:-translate-y-1 group">
        <div class="p-6 relative z-10">
            <div class="flex justify-between items-start mb-4">
                <div class="bg-violet-500/10 p-2.5 rounded-xl backdrop-blur-sm border border-violet-200">
                    <svg class="w-6 h-6 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                </div>
                <div class="text-xs font-semibold bg-violet-600/10 text-violet-700 px-2 py-1 rounded-full">
                    MOYENNE
                </div>
            </div>

            <div class="text-3xl font-bold text-violet-800 mb-1"><?php echo $moyenne_personnes?></div>
            <div class="text-sm text-violet-600/80">Personnes par réservation</div>

            <div class="absolute bottom-4 right-4 opacity-10 group-hover:opacity-20 transition-opacity">
                <svg class="w-16 h-16 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
            </div>
        </div>
        <div class="w-full h-1.5 bg-gradient-to-r from-violet-400 to-violet-600"></div>
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
            </div>
          </form>
        </div>

        <!-- Tableau des réservations avec bordures visibles -->
        <div class="bg-white rounded-2xl shadow-xl border-2 border-gray-200 overflow-hidden animate-slide-up">
          <div class="overflow-x-auto">
            <table class="w-full border-collapse">
             <thead>
  <tr class="bg-gradient-to-r from-gray-50 to-gray-100 border-b-2 border-gray-300">
    <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider border-r border-gray-300">
      <div class="flex items-center">
        <span class="w-2 h-2 bg-blue-500 rounded-full mr-2"></span>
        N°
      </div>
    </th>
    <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider border-r border-gray-300">Client</th>
    <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider border-r border-gray-300">Contact</th>
    <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider border-r border-gray-300">Réservation</th>
    <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider border-r border-gray-300">Personnes</th>
    <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider border-r border-gray-300">Message</th>
    <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider border-r border-gray-300">Statut</th>
    <th class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Actions</th>
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
	  <tr class="hover:bg-gradient-to-r hover:from-blue-50 hover:to-indigo-50 transition-all duration-200 group border-b border-gray-200"
	      data-reservation-id="<?php echo $res['id']?>">
	    <td class="px-6 py-4 whitespace-nowrap border-r border-gray-200">
	      <div class="flex items-center">
	        <div class="w-8 h-8 bg-gradient-to-r from-blue-500 to-indigo-600 rounded-lg flex items-center justify-center">
	          <span class="text-white text-xs font-bold"><?php echo $numero?></span>
	        </div>
	      </div>
	    </td>
	        <td class="px-6 py-4 whitespace-nowrap border-r border-gray-200">
	          <div class="flex items-center">
	            <div class="w-10 h-10 bg-gradient-to-r from-emerald-400 to-teal-500 rounded-full flex items-center justify-center mr-3">
	              <span class="text-white font-bold text-sm"><?php echo strtoupper(substr(htmlspecialchars($res['nom'] ?? ''), 0, 1))?></span>
	            </div>
	            <div>
	              <div class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($res['nom'] ?? '', ENT_QUOTES, 'UTF-8')?></div>
	            </div>
	          </div>
	        </td>
	        <td class="px-6 py-4 whitespace-nowrap border-r border-gray-200">
	          <div class="text-sm text-gray-900 font-medium"><?php echo htmlspecialchars($res['email'] ?? '', ENT_QUOTES, 'UTF-8')?></div>
	          <div class="text-sm text-gray-500 flex items-center mt-1">
	            <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
	              <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"/>
	            </svg>
	            <?php echo htmlspecialchars($res['telephone'] ?? '', ENT_QUOTES, 'UTF-8')?>
	          </div>
	        </td>
	        <td class="px-6 py-4 whitespace-nowrap border-r border-gray-200">
	          <div class="flex items-center">
	            <svg class="w-4 h-4 text-blue-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
	              <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/>
	            </svg>
	            <div>
	              <div class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($res['date_reservation'] ?? '', ENT_QUOTES, 'UTF-8')?></div>
	              <div class="text-sm text-gray-500"><?php echo htmlspecialchars($res['heure_reservation'] ?? '', ENT_QUOTES, 'UTF-8')?></div>
	            </div>
	          </div>
	        </td>
	        <td class="px-6 py-4 whitespace-nowrap border-r border-gray-200">
	          <div class="flex items-center">
	            <div class="w-8 h-8 bg-gradient-to-r from-orange-400 to-red-500 rounded-full flex items-center justify-center mr-2">
	              <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
	                <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/>
	              </svg>
	            </div>
	            <span class="text-sm font-bold text-gray-900"><?php echo htmlspecialchars($res['personnes'] ?? '', ENT_QUOTES, 'UTF-8')?></span>
	          </div>
	        </td>
	        <!-- NOUVELLE COLONNE MESSAGE -->
	        <td class="px-6 py-4 border-r border-gray-200">
	          <div class="max-w-xs">
	            <?php if (! empty($res['message'])): ?>
	              <div class="flex items-start">
	                <svg class="w-4 h-4 text-blue-500 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
	                  <path fill-rule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7zM7 9H5v2h2V9zm8 0h-2v2h2V9zM9 9h2v2H9V9z" clip-rule="evenodd"/>
	                </svg>
	                <div class="text-sm text-gray-700 line-clamp-2">
	                  <?php echo htmlspecialchars(substr($res['message'], 0, 100) . (strlen($res['message']) > 100 ? '...' : ''), ENT_QUOTES, 'UTF-8')?>
	                </div>
	              </div>
	              <?php if (strlen($res['message']) > 100): ?>
	                <button onclick="showFullMessage('<?php echo htmlspecialchars(addslashes($res['message']), ENT_QUOTES, 'UTF-8')?>')"
	                        class="text-xs text-blue-600 hover:text-blue-800 mt-1 font-medium">
	                  Voir plus
	                </button>
	              <?php endif; ?>
<?php else: ?>
              <span class="text-xs text-gray-400 italic flex items-center">
                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                </svg>
                Aucun message
              </span>
            <?php endif; ?>
          </div>
        </td>
        <td class="px-6 py-4 whitespace-nowrap border-r border-gray-200">
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
            <button onclick="openViewModal(<?php echo $res['id']?>)" class="inline-flex items-center px-3 py-2 text-sm font-medium text-green-700 bg-green-50 hover:bg-green-100 rounded-lg transition-all duration-200 hover:scale-105 border border-green-200">
              <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                <path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/>
                <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/>
              </svg>
              Voir
            </button>
            <button onclick="openEditModal(<?php echo $res['id']?>)" class="inline-flex items-center px-3 py-2 text-sm font-medium text-blue-700 bg-blue-50 hover:bg-blue-100 rounded-lg transition-all duration-200 hover:scale-105 border border-blue-200">
              <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"/>
              </svg>
              Modifier
            </button>
            <a href="?delete=<?php echo $res['id']?>" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette réservation ?')" class="inline-flex items-center px-3 py-2 text-sm font-medium text-red-700 bg-red-50 hover:bg-red-100 rounded-lg transition-all duration-200 hover:scale-105 border border-red-200">
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
      <td colspan="8" class="text-center py-12">
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

  </script>

</body>
</html>