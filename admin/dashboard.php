<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

require_once '../config.php'; // Ce fichier doit définir $conn

// Infos admin
$admin_name = $_SESSION['admin_name'] ?? 'Admin';
$admin_email = $_SESSION['admin_email'] ?? '';

// 🔴 Nombre de réservations non lues
try {
    $req = $conn->query("SELECT COUNT(*) AS total FROM reservations WHERE statut = 'non_lu'");
    $nb_nouvelles = $req->fetchColumn();
} catch (PDOException $e) {
    error_log("Erreur lors du comptage des réservations : " . $e->getMessage());
    $nb_nouvelles = 0;
}

// 🔵 Nombre total de réservations
$reservations = $conn->query("SELECT COUNT(*) FROM reservations")->fetchColumn();

// 🟣 Dernières réservations
$last_reservations = $conn->query("
    SELECT nom, date_reservation, statut
    FROM reservations
    ORDER BY date_reservation DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// 🟢 Nombre de plats
$plats = $conn->query("SELECT COUNT(*) FROM plats")->fetchColumn();

// ⚡ Total des ventes
$revenus = $conn->query("
    SELECT SUM(ci.prix_unitaire * ci.quantite) AS total_ventes
    FROM commande_items ci
    JOIN plats p ON ci.plat_id = p.id
")->fetchColumn();
$revenus = $revenus ?: 0;

// 🥇 Plats les plus commandés
$platsPopulaires = $conn->query("
    SELECT p.nom, SUM(ci.quantite) AS total
    FROM commande_items ci
    JOIN plats p ON ci.plat_id = p.id
    GROUP BY ci.plat_id
    ORDER BY total DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// 📊 Revenus mensuels pour graphique
$revenus_par_mois_stmt = $conn->query("
    SELECT DATE_FORMAT(c.date_commande, '%Y-%m') AS mois,
           SUM(ci.prix_unitaire * ci.quantite) AS total
    FROM commandes c
    JOIN commande_items ci ON ci.commande_id = c.id
    JOIN plats p ON ci.plat_id = p.id
    GROUP BY mois
    ORDER BY mois ASC
");
$revenus_par_mois = $revenus_par_mois_stmt->fetchAll(PDO::FETCH_ASSOC);

$labels = [];
$data = [];
foreach($revenus_par_mois as $row) {
    $labels[] = $row['mois'];
    $data[] = $row['total'];
}

// 📅 Revenus par jour (filtrable)
$interval = '30 DAY'; // Par défaut
if (isset($_GET['periode'])) {
    if ($_GET['periode'] === '7') {
        $interval = '7 DAY';
    } elseif ($_GET['periode'] === '30') {
        $interval = '30 DAY';
    } else {
        $interval = null; // Pas de filtre
    }
}

$where = $interval ? "WHERE date_commande >= NOW() - INTERVAL $interval" : "";

$query = "
    SELECT DATE_FORMAT(c.date_commande, '%Y-%m-%d') AS jour, 
           SUM(ci.prix_unitaire * ci.quantite) AS total
    FROM commandes c
    JOIN commande_items ci ON ci.commande_id = c.id
    JOIN plats p ON ci.plat_id = p.id
    $where
    GROUP BY jour
    ORDER BY jour ASC
";
$revenus_par_jour = $conn->query($query)->fetchAll(PDO::FETCH_ASSOC);

// ✅ Taux de confirmation des réservations
$total_reservations = $reservations; // déjà récupéré plus haut
$confirmées = $conn->query("SELECT COUNT(*) FROM reservations WHERE statut = 'confirmée'")->fetchColumn();
$taux_confirmation = $total_reservations > 0 ? round(($confirmées / $total_reservations) * 100, 1) : 0;

// ⭐ Derniers avis
$avis = $conn->query("
    SELECT nom, note, message, date_creation
    FROM avis
    ORDER BY date_creation DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);


// 🔔 Système de notifications amélioré
$query_notifications = "
    SELECT id, message, type, date, vue
    FROM notifications
    ORDER BY date DESC
    LIMIT 10
";

$notifications = $conn->query($query_notifications)->fetchAll(PDO::FETCH_ASSOC);

$nb_nouvelles_notifications = $conn->query("
    SELECT COUNT(*) AS total
    FROM notifications
    WHERE vue = 0
")->fetchColumn();

// Marquer les notifications comme vues si demandé
if (isset($_POST['mark_read']) && isset($_POST['notification_id'])) {
    $notification_id = intval($_POST['notification_id']);
    $conn->prepare("UPDATE notifications SET vue = 1 WHERE id = ?")->execute([$notification_id]);
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Marquer toutes les notifications comme vues
if (isset($_POST['mark_all_read'])) {
    $conn->query("UPDATE notifications SET vue = 1");
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Récupérer les notifications non lues
$stmt = $conn->query("SELECT * FROM notifications WHERE vue = 0 ORDER BY date DESC");
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Infos admin avec photo de profil
$admin_name = $_SESSION['admin_name'] ?? 'Admin';
$admin_email = $_SESSION['admin_email'] ?? '';
$admin_id = $_SESSION['admin_id'] ?? 0;

// 👤 Récupérer la photo de profil
$admin_photo = null;
if ($admin_id > 0) {
    try {
        $stmt = $conn->prepare("SELECT profile_photo FROM admin WHERE id = ?");
        $stmt->execute([$admin_id]);
        $admin_data = $stmt->fetch(PDO::FETCH_ASSOC);
        $admin_photo = $admin_data['profile_photo'] ?? null;
        
        // Vérifier si le fichier existe réellement
        if ($admin_photo && !file_exists($admin_photo)) {
            $admin_photo = null;
        }
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération de la photo de profil : " . $e->getMessage());
        $admin_photo = null;
    }
}
?>


<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>restaurant Mulho</title>
    <link rel="icon" type="image/x-icon" href="../assets/img/logo.jpg">
    
    <!-- CSS Frameworks -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    
    <!-- Tailwind Configuration Professionnelle -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        // Palette professionnelle corporate
                        'primary': '#1e293b',      // Bleu-gris foncé
                        'secondary': '#334155',    // Bleu-gris moyen
                        'accent': '#0f172a',       // Bleu-gris très foncé
                        'surface': '#475569',      // Surface grise
                        'text-primary': '#f8fafc', // Blanc cassé
                        'text-secondary': '#cbd5e1', // Gris clair
                        
                        // Couleurs d'accent professionnelles
                        'corporate': {
                            'blue': '#0369a1',      // Bleu corporate
                            'teal': '#0f766e',      // Teal professionnel
                            'emerald': '#059669',   // Vert corporate
                            'amber': '#d97706',     // Orange professionnel
                            'rose': '#e11d48',      // Rouge discret
                            'violet': '#7c3aed',    // Violet corporate
                        },
                        
                        // États
                        'success': '#10b981',
                        'warning': '#f59e0b',
                        'error': '#ef4444',
                        'info': '#3b82f6',
                        
                        'glass': 'rgba(255, 255, 255, 0.08)',
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.8s ease-in-out',
                        'slide-up': 'slideUp 0.6s ease-out',
                        'float': 'float 4s ease-in-out infinite',
                        'pulse-slow': 'pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                        'bounce-slow': 'bounce 2s infinite',
                        'spin-slow': 'spin 3s linear infinite',
                        'wiggle': 'wiggle 1s ease-in-out infinite',
                        'glow': 'glow 2s ease-in-out infinite alternate',
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': { opacity: '0', transform: 'translateY(20px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' }
                        },
                        slideUp: {
                            '0%': { transform: 'translateY(100px)', opacity: '0' },
                            '100%': { transform: 'translateY(0)', opacity: '1' }
                        },
                        float: {
                            '0%, 100%': { transform: 'translateY(0px)' },
                            '50%': { transform: 'translateY(-15px)' }
                        },
                        wiggle: {
                            '0%, 100%': { transform: 'rotate(-3deg)' },
                            '50%': { transform: 'rotate(3deg)' }
                        },
                        glow: {
                            '0%': { boxShadow: '0 0 5px rgba(3, 105, 161, 0.3)' },
                            '100%': { boxShadow: '0 0 20px rgba(3, 105, 161, 0.6), 0 0 30px rgba(15, 118, 110, 0.4)' }
                        }
                    },
                    backdropBlur: {
                        'xs': '2px',
                        '4xl': '72px',
                    }
                }
            }
        }
    </script>

    <!-- Styles Professionnels -->
    <style>
        [x-cloak] { display: none !important; }
        
        .glass-morphism {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.12);
        }
        
        .glass-card {
            background: rgba(255, 255, 255, 0.06);
            backdrop-filter: blur(25px);
            border: 1px solid rgba(255, 255, 255, 0.10);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
        }
        
        .gradient-corporate {
            background: linear-gradient(135deg, 
                #1e293b 0%, 
                #334155 25%, 
                #475569 50%, 
                #64748b 75%, 
                #94a3b8 100%);
            background-size: 400% 400%;
            animation: gradientShift 20s ease infinite;
        }
        
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        .card-hover {
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        
        .card-hover:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.2),
                        0 0 40px rgba(3, 105, 161, 0.1);
        }
        
        .text-corporate {
            background: linear-gradient(135deg, #f8fafc, #e2e8f0);
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .bg-corporate-gradient {
            background: linear-gradient(135deg, #0369a1, #0f766e);
        }
        
        .bg-success-gradient {
            background: linear-gradient(135deg, #059669, #10b981);
        }
        
        .bg-warning-gradient {
            background: linear-gradient(135deg, #d97706, #f59e0b);
        }
        
        .bg-error-gradient {
            background: linear-gradient(135deg, #dc2626, #ef4444);
        }
        
        .notification-badge {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        
        .blob {
            border-radius: 30% 70% 70% 30% / 30% 30% 70% 70%;
            background: linear-gradient(45deg, rgba(3, 105, 161, 0.1), rgba(15, 118, 110, 0.1));
            animation: blob 7s infinite;
        }
        
        @keyframes blob {
            0% {
                border-radius: 30% 70% 70% 30% / 30% 30% 70% 70%;
            }
            25% {
                border-radius: 58% 42% 75% 25% / 76% 46% 54% 24%;
            }
            50% {
                border-radius: 50% 50% 33% 67% / 55% 27% 73% 45%;
            }
            75% {
                border-radius: 33% 67% 58% 42% / 63% 68% 32% 37%;
            }
            100% {
                border-radius: 30% 70% 70% 30% / 30% 30% 70% 70%;
            }
        }
        
        .pattern-grid {
            background-image: 
                linear-gradient(rgba(255, 255, 255, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, 0.03) 1px, transparent 1px);
            background-size: 30px 30px;
        }
        
        .scrollbar-hidden {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        
        .scrollbar-hidden::-webkit-scrollbar {
            display: none;
        }
        
        /* Scrollbar personnalisée professionnelle */
        ::-webkit-scrollbar {
            width: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: rgba(3, 105, 161, 0.4);
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: rgba(3, 105, 161, 0.6);
        }

        /* Styles des cartes modernes de gestion_plats.php */
.dashboard-card {
    background: rgba(30, 41, 59, 0.8);
    backdrop-filter: blur(20px);
    border-radius: 16px;
    padding: 24px;
    border: 2px solid rgba(255, 255, 255, 0.1);
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1), 0 1px 3px rgba(0, 0, 0, 0.08);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.dashboard-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--card-accent, #3b82f6);
    border-radius: 16px 16px 0 0;
}

.dashboard-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 24px rgba(0, 0, 0, 0.2), 0 4px 8px rgba(0, 0, 0, 0.1);
    border-color: rgba(255, 255, 255, 0.2);
}

.card-purple { --card-accent: #8b5cf6; }
.card-red { --card-accent: #ef4444; }
.card-blue { --card-accent: #3b82f6; }
.card-green { --card-accent: #10b981; }
.card-orange { --card-accent: #f59e0b; }
.card-cyan { --card-accent: #06b6d4; }

.icon-wrapper {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    transition: transform 0.3s ease;
}

.dashboard-card:hover .icon-wrapper {
    transform: scale(1.1) rotate(5deg);
}

.icon-purple { background: rgba(139, 92, 246, 0.2); color: #a78bfa; }
.icon-red { background: rgba(239, 68, 68, 0.2); color: #f87171; }
.icon-blue { background: rgba(59, 130, 246, 0.2); color: #60a5fa; }
.icon-green { background: rgba(16, 185, 129, 0.2); color: #34d399; }
.icon-orange { background: rgba(245, 158, 11, 0.2); color: #fbbf24; }
.icon-cyan { background: rgba(6, 182, 212, 0.2); color: #22d3ee; }

/* Animation des cartes au chargement */
@keyframes fadeIn {
    0% { opacity: 0; transform: translateY(20px); }
    100% { opacity: 1; transform: translateY(0); }
}

.animate-fade-in {
    animation: fadeIn 0.5s ease-in-out forwards;
    opacity: 0;
}
    </style>
</head>

<body class="min-h-screen bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 pattern-grid overflow-x-hidden">
    <!-- Éléments de fond animés corporates -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none z-0">
        <div class="absolute -top-40 -right-40 w-96 h-96 blob opacity-20"></div>
        <div class="absolute -bottom-40 -left-40 w-80 h-80 blob opacity-15 animation-delay-2000"></div>
        <div class="absolute top-1/2 left-1/2 w-64 h-64 blob opacity-10 animation-delay-4000"></div>
    </div>

    <div class="flex h-screen overflow-hidden relative z-10">
        <!-- Sidebar (préservé tel quel) -->
        <?php include 'sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Header Professionnel -->
            <header class="glass-morphism shadow-2xl border-b border-white/10 sticky top-0 z-40">
                <div class="px-4 sm:px-6 lg:px-8 py-4">
                    <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
                        <!-- Section Titre & Bienvenue -->
                        <div class="flex-1 animate-fade-in">
                            <div class="flex items-center space-x-4 mb-3">
                                <div class="w-14 h-14 bg-corporate-gradient rounded-2xl flex items-center justify-center shadow-2xl animate-float">
                                    <i class="fas fa-chart-line text-white text-xl"></i>
                                </div>
                                <div>
                                    <h1 class="text-3xl lg:text-4xl font-bold text-corporate">
                                        Tableau de Bord
                                    </h1>
                                    <p class="text-text-secondary text-sm font-medium">Bienvenue, <?= htmlspecialchars($admin_name) ?> ✨</p>
                                </div>
                            </div>
                            
                            <!-- Alertes -->
                            <?php if ($nb_nouvelles > 0): ?>
                                <div class="inline-flex items-center px-6 py-3 rounded-2xl bg-error-gradient text-white text-sm font-semibold shadow-2xl animate-bounce-slow">
                                    <div class="w-3 h-3 bg-white rounded-full mr-3 animate-ping"></div>
                                    <span><?= htmlspecialchars($nb_nouvelles) ?> nouvelle<?= $nb_nouvelles > 1 ? 's' : '' ?> alerte<?= $nb_nouvelles > 1 ? 's' : '' ?></span>
                                    <a href="reservations.php" class="ml-4 px-4 py-2 bg-white/20 rounded-xl hover:bg-white/30 transition-all duration-300 hover:scale-105">
                                        Traiter <i class="fas fa-arrow-right ml-1"></i>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Contrôles Professionnels -->
                        <div class="flex items-center space-x-4" x-data="{ profileOpen: false, notificationsOpen: false }">
                            <!-- Widget Stats Temps Réel -->
                            <div class="hidden sm:flex items-center space-x-6 glass-card rounded-2xl px-6 py-4 shadow-xl">
                                <div class="flex items-center space-x-3 text-text-primary">
                                    <div class="w-10 h-10 bg-corporate-blue/20 rounded-2xl flex items-center justify-center shadow-lg">
                                        <i class="fas fa-calendar text-corporate-blue text-sm"></i>
                                    </div>
                                    <div>
                                        <p class="text-xs text-text-secondary font-medium uppercase tracking-wide">Aujourd'hui</p>
                                        <p class="text-sm font-bold"><?= date('d M Y') ?></p>
                                    </div>
                                </div>
                                <div class="w-px h-8 bg-white/20"></div>
                                <div class="flex items-center space-x-3 text-text-primary">
                                    <div class="w-10 h-10 bg-corporate-teal/20 rounded-2xl flex items-center justify-center shadow-lg">
                                        <i class="fas fa-clock text-corporate-teal text-sm animate-spin-slow"></i>
                                    </div>
                                    <div>
                                        <p class="text-xs text-text-secondary font-medium uppercase tracking-wide">Heure</p>
                                        <p class="text-sm font-bold font-mono" id="live-clock"><?= date('H:i:s') ?></p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Cloche Notifications -->
                           <div class="relative">
    <button 
        @click="notificationsOpen = !notificationsOpen"
        class="relative w-14 h-14 bg-gradient-to-br from-indigo-500 via-purple-500 to-pink-500 rounded-2xl flex items-center justify-center shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-105 focus:outline-none focus:ring-4 focus:ring-purple-300/50 group transform hover:rotate-3"
        aria-label="Notifications"
        type="button"
    >
        <i class="fas fa-bell text-white text-xl group-hover:animate-swing"></i>
        <?php if (!empty($nb_nouvelles_notifications) && $nb_nouvelles_notifications > 0): ?>
            <div class="absolute -top-3 -right-3 w-7 h-7 bg-gradient-to-r from-red-400 to-pink-500 rounded-full flex items-center justify-center shadow-lg animate-pulse border-2 border-white">
                <span class="text-white text-xs font-bold">
                    <?= min($nb_nouvelles_notifications, 9) ?><?= $nb_nouvelles_notifications > 9 ? '+' : '' ?>
                </span>
            </div>
        <?php endif; ?>
    </button>
    
    <!-- Dropdown Notifications -->
    <div 
        x-show="notificationsOpen"
        @click.away="notificationsOpen = false"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="transform opacity-0 scale-95 translate-y-2"
        x-transition:enter-end="transform opacity-100 scale-100 translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="transform opacity-100 scale-100 translate-y-0"
        x-transition:leave-end="transform opacity-0 scale-95 translate-y-2"
        class="absolute right-0 mt-6 w-[420px] bg-white rounded-3xl shadow-2xl py-0 z-50 border border-gray-100 max-h-[500px] overflow-hidden backdrop-blur-sm"
        style="box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25), 0 0 0 1px rgba(255, 255, 255, 0.1);"
        x-cloak
    >
        <!-- Header Notifications avec gradient -->
        <div class="px-8 py-6 bg-gradient-to-r from-indigo-500 via-purple-500 to-pink-500 rounded-t-3xl">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <div class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-2xl flex items-center justify-center border border-white/30">
                        <i class="fas fa-bell text-white text-lg"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-white text-xl">Notifications</h3>
                        <?php if (!empty($nb_nouvelles_notifications) && $nb_nouvelles_notifications > 0): ?>
                            <span class="inline-flex items-center px-3 py-1 bg-white/20 text-white text-sm font-semibold rounded-full border border-white/30 backdrop-blur-sm mt-1">
                                <?= $nb_nouvelles_notifications ?> nouvelle<?= $nb_nouvelles_notifications > 1 ? 's' : '' ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if (!empty($nb_nouvelles_notifications) && $nb_nouvelles_notifications > 0): ?>
                    <form method="post" class="inline">
                        <button type="submit" name="mark_all_read" class="text-white/90 hover:text-white font-medium px-4 py-2 rounded-xl hover:bg-white/10 transition-all duration-200 text-sm border border-white/20 backdrop-blur-sm">
                            <i class="fas fa-check-double mr-2"></i>
                            Tout lire
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Liste Notifications -->
        <div class="max-h-80 overflow-y-auto scrollbar-thin scrollbar-thumb-gray-300 scrollbar-track-gray-100">
            <?php if (empty($notifications)): ?>
                <div class="p-12 text-center">
                    <div class="w-20 h-20 bg-gradient-to-br from-gray-100 to-gray-200 rounded-full flex items-center justify-center mx-auto mb-6 shadow-inner">
                        <i class="fas fa-bell-slash text-gray-400 text-3xl"></i>
                    </div>
                    <h4 class="font-semibold text-gray-700 text-lg mb-2">Aucune notification</h4>
                    <p class="text-gray-500 text-sm">Vous êtes à jour ! 🎉</p>
                </div>
            <?php else: ?>
                <?php foreach ($notifications as $index => $notification): ?>
                    <div class="group relative overflow-hidden <?= empty($notification['vue']) ? 'bg-gradient-to-r from-blue-50 to-indigo-50' : 'bg-white' ?> hover:bg-gradient-to-r hover:from-gray-50 hover:to-blue-50 transition-all duration-300 border-b border-gray-100 last:border-b-0">
                        <?php if (empty($notification['vue'])): ?>
                            <div class="absolute left-0 top-0 bottom-0 w-1 bg-gradient-to-b from-indigo-400 to-purple-500"></div>
                        <?php endif; ?>
                        
                        <div class="p-6 flex items-start space-x-4">
                            <div class="relative flex-shrink-0">
                                <div class="w-12 h-12 rounded-2xl flex items-center justify-center shadow-lg transform group-hover:scale-110 transition-transform duration-200
                                    <?php 
                                        switch ($notification['type'] ?? '') {
                                            case 'success': echo 'bg-gradient-to-br from-green-400 to-emerald-500'; break;
                                            case 'warning': echo 'bg-gradient-to-br from-amber-400 to-orange-500'; break;
                                            case 'error': echo 'bg-gradient-to-br from-red-400 to-pink-500'; break;
                                            default: echo 'bg-gradient-to-br from-blue-400 to-indigo-500';
                                        }
                                    ?>
                                ">
                                    <i class="fas 
                                        <?php
                                            switch ($notification['type'] ?? '') {
                                                case 'success': echo 'fa-check'; break;
                                                case 'warning': echo 'fa-exclamation-triangle'; break;
                                                case 'error': echo 'fa-times'; break;
                                                default: echo 'fa-info';
                                            }
                                        ?>" 
                                        class="text-white text-lg"
                                    ></i>
                                </div>
                                <?php if (empty($notification['vue'])): ?>
                                    <div class="absolute -top-2 -right-2 w-4 h-4 bg-gradient-to-r from-red-400 to-pink-500 rounded-full border-2 border-white animate-pulse"></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="flex-1 min-w-0">
                                <div class="flex items-start justify-between mb-2">
                                    <h4 class="font-semibold text-gray-800 text-base leading-tight <?= empty($notification['vue']) ? 'text-indigo-700' : '' ?>">
                                        <?= htmlspecialchars($notification['titre'] ?? 'Sans titre') ?>
                                    </h4>
                                    <?php if (empty($notification['vue'])): ?>
                                        <span class="ml-3 inline-flex items-center px-2 py-1 bg-gradient-to-r from-indigo-100 to-purple-100 text-indigo-700 text-xs font-medium rounded-full border border-indigo-200">
                                            Nouveau
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <p class="text-gray-600 text-sm leading-relaxed mb-3"><?= htmlspecialchars($notification['message'] ?? '') ?></p>
                                
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center space-x-2 text-xs text-gray-500">
                                        <i class="fas fa-clock text-gray-400"></i>
                                        <span class="font-medium">
                                            <?= !empty($notification['date_creation']) ? date('d/m/Y à H:i', strtotime($notification['date_creation'])) : 'Date inconnue' ?>
                                        </span>
                                    </div>
                                    
                                    <?php if (empty($notification['vue'])): ?>
                                        <form method="post" class="inline">
                                            <input type="hidden" name="notification_id" value="<?= (int)($notification['id'] ?? 0) ?>">
                                            <button type="submit" name="mark_read" class="inline-flex items-center text-xs text-indigo-600 hover:text-indigo-700 font-medium px-3 py-1 rounded-lg hover:bg-indigo-50 transition-all duration-200 border border-indigo-200 hover:border-indigo-300">
                                                <i class="fas fa-eye mr-1"></i>
                                                Marquer lu
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- Footer avec lien voir tout -->
        <div class="px-8 py-6 bg-gradient-to-r from-gray-50 to-blue-50 rounded-b-3xl border-t border-gray-100">
            <a href="notifications.php" class="group flex items-center justify-center space-x-3 text-indigo-600 hover:text-indigo-700 font-medium text-sm py-3 px-6 rounded-2xl hover:bg-white transition-all duration-300 shadow-sm hover:shadow-md border border-indigo-200 hover:border-indigo-300">
                <span>Voir toutes les notifications</span>
                <i class="fas fa-arrow-right group-hover:translate-x-1 transition-transform duration-200"></i>
            </a>
        </div>
    </div>

    <style>
        @keyframes swing {
            0%, 100% { transform: rotate(0deg); }
            25% { transform: rotate(15deg); }
            75% { transform: rotate(-15deg); }
        }
        
        .group-hover\:animate-swing:hover {
            animation: swing 0.6s ease-in-out;
        }
        
        .scrollbar-thin::-webkit-scrollbar {
            width: 6px;
        }
        
        .scrollbar-thumb-gray-300::-webkit-scrollbar-thumb {
            background-color: #d1d5db;
            border-radius: 3px;
        }
        
        .scrollbar-track-gray-100::-webkit-scrollbar-track {
            background-color: #f3f4f6;
        }
    </style>
</div>
                           <!-- Menu Profil -->
<div class="relative" style="z-index: 1000;">
    <!-- Bouton Profil Principal -->
      <button 
        @click="profileOpen = !profileOpen"
        class="relative w-14 h-14 rounded-2xl flex items-center justify-center shadow-2xl hover:shadow-3xl transition-all duration-300 hover:scale-105 focus:outline-none focus:ring-4 focus:ring-corporate-violet/30 group animate-glow overflow-hidden"
        style="z-index: 1001;"
        type="button"
    >
         <?php if (!empty($admin_photo) && file_exists($admin_photo)): ?>
            <!-- Photo de profil -->
            <img src="<?= htmlspecialchars($admin_photo) ?>" 
                 alt="Photo de profil" 
                 class="w-full h-full object-cover rounded-2xl group-hover:scale-110 transition-transform duration-300">
            <div class="absolute inset-0 bg-gradient-to-t from-black/20 to-transparent rounded-2xl"></div>
        <?php else: ?>
            <!-- Initiale par défaut -->
            <div class="w-full h-full bg-gradient-to-r from-corporate-violet to-corporate-blue rounded-2xl flex items-center justify-center">
                <span class="text-white font-bold text-lg group-hover:scale-110 transition-transform">
                    <?= strtoupper(substr($admin_name ?? 'A', 0, 1)) ?>
                </span>
            </div>
        <?php endif; ?>
        
        <!-- Badge en ligne -->
        <div class="absolute -bottom-1 -right-1 w-5 h-5 bg-green-400 border-2 border-white rounded-full animate-pulse"></div>
    </button>
    
    <!-- Dropdown Profil -->
   <div 
        x-show="profileOpen"
        @click.away="profileOpen = false"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="transform opacity-0 scale-95 translate-y-2"
        x-transition:enter-end="transform opacity-100 scale-100 translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="transform opacity-100 scale-100 translate-y-0"
        x-transition:leave-end="transform opacity-0 scale-95 translate-y-2"
        class="fixed top-20 right-4 w-80 rounded-3xl shadow-2xl overflow-hidden border border-white/30"
        style="background: rgba(15, 23, 42, 0.95); backdrop-filter: blur(20px); box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); z-index: 9999; position: fixed !important;"
        x-cloak
    >
        <!-- En-tête du Profil avec Photo -->
        <div class="px-6 py-6" style="background: rgba(88, 28, 135, 0.1); border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
            <div class="flex items-center space-x-4">
                <!-- Avatar Large -->
                <div class="relative flex-shrink-0">
                    <div class="w-16 h-16 rounded-2xl overflow-hidden shadow-lg">
                        <?php if (!empty($admin_photo) && file_exists($admin_photo)): ?>
                            <img src="<?= htmlspecialchars($admin_photo) ?>" 
                                 alt="Photo de profil" 
                                 class="w-full h-full object-cover">
                        <?php else: ?>
                            <div class="w-full h-full bg-gradient-to-r from-corporate-violet to-corporate-blue flex items-center justify-center">
                                <span class="text-white font-bold text-xl">
                                    <?= strtoupper(substr($admin_name ?? 'A', 0, 1)) ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <!-- Bouton modifier photo -->
                    <button onclick="document.getElementById('photo-upload').click()" 
                            class="absolute -bottom-2 -right-2 w-7 h-7 bg-corporate-violet rounded-full flex items-center justify-center border-2 border-white hover:scale-110 transition-transform">
                        <i class="fas fa-camera text-white text-xs"></i>
                    </button>
                    <!-- Input caché pour upload -->
                    <input type="file" id="photo-upload" accept="image/*" style="display: none;" onchange="uploadProfilePhoto(this)">
                </div>
                
                <!-- Informations Utilisateur -->
                <div class="flex-1 min-w-0">
                    <h3 class="font-bold text-white text-lg truncate">
                        <?= htmlspecialchars($admin_name ?? 'Admin') ?>
                    </h3>
                    
                    <?php if (!empty($admin_email)): ?>
                        <p class="text-sm text-gray-300 truncate mt-1">
                            <?= htmlspecialchars($admin_email) ?>
                        </p>
                    <?php endif; ?>
                    
                    <!-- Statut En Ligne -->
                    <div class="flex items-center mt-2">
                        <div class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></div>
                        <span class="text-xs text-green-400 ml-2 font-medium">En ligne</span>
                    </div>
                </div>
            </div>
        </div>
        <!-- Séparateur -->
        <div class="h-px bg-gradient-to-r from-transparent via-white/20 to-transparent"></div>
        
        <!-- Menu de Navigation -->
        <div class="py-3" style="background: rgba(15, 23, 42, 0.7);">
            <!-- Mon Profil -->
            <a href="profile.php" 
               class="flex items-center px-6 py-4 text-gray-200 hover:text-white transition-all duration-200 group"
               style="background: transparent; border-radius: 0;"
               onmouseover="this.style.background='rgba(255, 255, 255, 0.1)'"
               onmouseout="this.style.background='transparent'">
                <div class="w-11 h-11 bg-corporate-blue/15 rounded-xl flex items-center justify-center mr-4 group-hover:bg-corporate-blue/25 group-hover:scale-105 transition-all duration-200">
                    <i class="fas fa-user text-corporate-blue text-lg"></i>
                </div>
                <div class="flex-1">
                    <span class="font-medium text-base block text-white">Mon profil</span>
                    <span class="text-xs text-gray-400">Gérer mes informations</span>
                </div>
                <i class="fas fa-chevron-right text-xs opacity-40 group-hover:opacity-100 group-hover:translate-x-1 transition-all duration-200"></i>
            </a>
            
            <!-- Changer Email -->
            <a href="changer_email.php" 
               class="flex items-center px-6 py-4 text-gray-200 hover:text-white transition-all duration-200 group"
               onmouseover="this.style.background='rgba(255, 255, 255, 0.1)'"
               onmouseout="this.style.background='transparent'">
                <div class="w-11 h-11 bg-corporate-violet/15 rounded-xl flex items-center justify-center mr-4 group-hover:bg-corporate-violet/25 group-hover:scale-105 transition-all duration-200">
                    <i class="fas fa-envelope text-corporate-violet text-lg"></i>
                </div>
                <div class="flex-1">
                    <span class="font-medium text-base block">Changer email</span>
                    <span class="text-xs text-text-secondary/70">Modifier mon adresse</span>
                </div>
                <i class="fas fa-chevron-right text-xs opacity-40 group-hover:opacity-100 group-hover:translate-x-1 transition-all duration-200"></i>
            </a>
        </div> 
        
        <!-- Séparateur avant Déconnexion -->
        <div class="h-px bg-gradient-to-r from-transparent via-white/20 to-transparent mx-6"></div>
        
        <!-- Section Déconnexion -->
        <div class="py-3" style="background: rgba(15, 23, 42, 0.8); border-top: 1px solid rgba(255, 255, 255, 0.1);">
            <a href="logout.php" 
               class="flex items-center px-6 py-4 text-red-400 hover:text-red-300 transition-all duration-200 group"
               onmouseover="this.style.background='rgba(239, 68, 68, 0.1)'"
               onmouseout="this.style.background='transparent'">
                <div class="w-11 h-11 bg-error/15 rounded-xl flex items-center justify-center mr-4 group-hover:bg-error/25 group-hover:scale-105 transition-all duration-200">
                    <i class="fas fa-sign-out-alt text-error text-lg"></i>
                </div>
                <div class="flex-1">
                    <span class="font-medium text-base block">Déconnexion</span>
                    <span class="text-xs text-error/70">Fermer la session</span>
                </div>
                <i class="fas fa-arrow-right text-xs opacity-40 group-hover:opacity-100 group-hover:translate-x-1 transition-all duration-200"></i>
            </a>
        </div>
    </div>
</div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Contenu Principal -->
            <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8 space-y-8 scrollbar-hidden">
             <!-- Section Cartes KPI Professionnelles - NOUVEAU DESIGN -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 animate-slide-up">
    <!-- KPI Réservations -->
    <div class="dashboard-card card-purple animate-fade-in">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-400 text-sm font-medium mb-1">Total des réservations</p>
                <p class="text-3xl font-bold text-white"><?= htmlspecialchars($reservations) ?></p>
                <p class="text-sm text-green-400 flex items-center mt-2">
                    <i class="fas fa-arrow-up mr-1"></i>
                    +12.5% ce mois
                </p>
            </div>
            <div class="icon-wrapper icon-purple">
                <i class="fas fa-calendar-check"></i>
            </div>
        </div>
    </div>
    
    <!-- KPI Plats -->
    <div class="dashboard-card card-orange animate-fade-in" style="animation-delay: 0.1s;">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-400 text-sm font-medium mb-1">Plats au menu</p>
                <p class="text-3xl font-bold text-white"><?= htmlspecialchars($plats) ?></p>
                <p class="text-sm text-blue-400 flex items-center mt-2">
                    <i class="fas fa-star mr-1"></i>
                    Menu diversifié
                </p>
            </div>
            <div class="icon-wrapper icon-orange">
                <i class="fas fa-utensils"></i>
            </div>
        </div>
    </div>
    
    <!-- KPI Revenus -->
    <div class="dashboard-card card-green animate-fade-in" style="animation-delay: 0.2s;">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-400 text-sm font-medium mb-1">Chiffre d'affaires</p>
                <p class="text-3xl font-bold text-white"><?= number_format($revenus, 0, ',', ' ') ?></p>
                <p class="text-xs text-gray-400 mt-1">FCFA</p>
            </div>
            <div class="icon-wrapper icon-green">
                <i class="fas fa-coins"></i>
            </div>
        </div>
    </div>
    
    <!-- KPI Taux de confirmation -->
    <div class="dashboard-card card-blue animate-fade-in" style="animation-delay: 0.3s;">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-400 text-sm font-medium mb-1">Taux confirmation</p>
                <p class="text-3xl font-bold text-white"><?= $taux_confirmation ?>%</p>
                <div class="w-full bg-gray-700 rounded-full h-2 mt-3">
                    <div class="h-full bg-blue-500 rounded-full transition-all duration-1000" 
                         style="width: <?= $taux_confirmation ?>%"></div>
                </div>
            </div>
            <div class="icon-wrapper icon-blue">
                <i class="fas fa-check-circle"></i>
            </div>
        </div>
    </div>
</div>

                <!-- Section Graphiques & Analytics -->
                <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
                    <!-- Analytics Revenus -->
                    <div class="xl:col-span-2 glass-card p-10 rounded-2xl card-hover animate-fade-in">
                        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-8 gap-4">
                            <div class="flex items-center space-x-5">
                                <div class="w-16 h-16 bg-corporate-gradient rounded-2xl flex items-center justify-center shadow-2xl animate-float">
                                    <i class="fas fa-chart-area text-white text-2xl"></i>
                                </div>
                                <div>
                                    <h2 class="text-3xl font-bold text-corporate">Analyse des Revenus</h2>
                                    <p class="text-text-secondary text-lg">Évolution et tendances des ventes</p>
                                </div>
                            </div>
                            
                            <form method="get" class="flex items-center space-x-4">
                                <select name="periode" id="periode" class="glass-morphism border border-white/20 rounded-2xl px-6 py-3 text-text-primary focus:ring-2 focus:ring-corporate-blue focus:border-transparent bg-white/5">
                                    <option value="30" <?= (isset($_GET['periode']) && $_GET['periode'] === '30') ? 'selected' : '' ?>>30 derniers jours</option>
                                    <option value="90" <?= (isset($_GET['periode']) && $_GET['periode'] === '90') ? 'selected' : '' ?>>3 derniers mois</option>
                                    <option value="365" <?= (isset($_GET['periode']) && $_GET['periode'] === '365') ? 'selected' : '' ?>>12 derniers mois</option>
                                </select>
                                <button type="submit" class="bg-corporate-gradient text-white px-8 py-3 rounded-2xl hover:opacity-90 transition-all duration-300 font-semibold shadow-2xl hover:shadow-3xl hover:scale-105">
                                    <i class="fas fa-filter mr-2"></i>Analyser
                                </button>
                            </form>
                        </div>
                        
                        <div class="relative h-96 mb-8 p-4 bg-white/5 rounded-2xl">
                            <canvas id="revenusChart" class="w-full h-full"></canvas>
                        </div>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                            <div class="glass-morphism p-6 rounded-2xl text-center hover:scale-105 transition-transform duration-300">
                                <div class="w-14 h-14 bg-success-gradient rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-xl">
                                    <i class="fas fa-arrow-up text-white text-xl"></i>
                                </div>
                                <p class="text-3xl font-bold text-success mb-2"><?= number_format($revenus, 0, ',', ' ') ?></p>
                                <p class="text-text-secondary font-medium">Revenus totaux</p>
                            </div>
                            <div class="glass-morphism p-6 rounded-2xl text-center hover:scale-105 transition-transform duration-300">
                                <div class="w-14 h-14 bg-corporate-gradient rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-xl">
                                    <i class="fas fa-chart-line text-white text-xl"></i>
                                </div>
                                <p class="text-3xl font-bold text-info mb-2">+15.7%</p>
                                <p class="text-text-secondary font-medium">Croissance</p>
                            </div>
                            <div class="glass-morphism p-6 rounded-2xl text-center hover:scale-105 transition-transform duration-300">
                                <div class="w-14 h-14 bg-gradient-to-r from-corporate-violet to-corporate-rose rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-xl">
                                    <i class="fas fa-calendar text-white text-xl"></i>
                                </div>
                                <p class="text-3xl font-bold text-corporate-violet mb-2"><?= count($revenus_par_mois) ?></p>
                                <p class="text-text-secondary font-medium">Mois actifs</p>
                            </div>
                        </div>
                    </div>

                    <!-- Hall of Fame Plats -->
                    <div class="glass-card p-10 rounded-2xl card-hover animate-fade-in">
                        <div class="flex items-center justify-between mb-8">
                            <div class="flex items-center space-x-4">
                                <div class="w-16 h-16 bg-warning-gradient rounded-2xl flex items-center justify-center shadow-2xl animate-float">
                                    <i class="fas fa-crown text-white text-2xl"></i>
                                </div>
                                <div>
                                    <h2 class="text-2xl font-bold text-corporate">Hall of Fame</h2>
                                    <p class="text-text-secondary">Plats les plus appréciés</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="space-y-5">
                            <?php foreach($platsPopulaires as $index => $plat): ?>
                            <div class="group relative">
                                <div class="flex items-center space-x-4 p-5 glass-morphism rounded-2xl hover:bg-white/10 transition-all duration-300 hover:scale-102 border border-white/10">
                                    <div class="relative">
                                        <div class="w-14 h-14 rounded-2xl flex items-center justify-center shadow-xl
                                            <?= $index === 0 ? 'bg-warning-gradient' : 
                                               ($index === 1 ? 'bg-gradient-to-br from-slate-400 to-slate-600' : 
                                               ($index === 2 ? 'bg-gradient-to-br from-corporate-amber to-warning' : 'bg-corporate-gradient')) ?>">
                                            <?php if($index < 3): ?>
                                                <i class="fas fa-medal text-white text-xl"></i>
                                            <?php else: ?>
                                                <span class="text-white font-bold text-lg"><?= $index + 1 ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if($index === 0): ?>
                                            <div class="absolute -top-2 -right-2 w-6 h-6 bg-corporate-amber rounded-full flex items-center justify-center animate-bounce-slow">
                                                <i class="fas fa-crown text-yellow-800 text-xs"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="flex-1 min-w-0">
                                        <p class="font-bold text-text-primary text-lg truncate"><?= htmlspecialchars($plat['nom']) ?></p>
                                        <p class="text-text-secondary font-medium"><?= $plat['total'] ?> commandes validées</p>
                                        
                                        <div class="w-full bg-white/10 rounded-full h-3 mt-3 overflow-hidden">
                                            <div class="h-full rounded-full transition-all duration-2000 ease-out shadow-lg
                                                <?= $index === 0 ? 'bg-warning-gradient' : 'bg-corporate-gradient' ?>" 
                                                 style="width: <?= min(100, ($plat['total'] / max(1, $platsPopulaires[0]['total'] ?? 1)) * 100) ?>%"></div>
                                        </div>
                                    </div>
                                    
                                    <div class="text-right">
                                        <div class="w-10 h-10 bg-white/10 rounded-xl flex items-center justify-center group-hover:bg-white/20 transition-colors">
                                            <i class="fas fa-arrow-right text-text-secondary group-hover:translate-x-1 transition-transform"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="mt-8 text-center">
                            <a href="gestion_plats.php" class="inline-flex items-center px-8 py-4 bg-warning-gradient text-white rounded-2xl font-semibold hover:opacity-90 transition-all duration-300 shadow-2xl hover:shadow-3xl hover:scale-105">
                                <i class="fas fa-utensils mr-3"></i>
                                Explorer le menu complet
                                <i class="fas fa-arrow-right ml-3"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Section Activité -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Réservations Récentes -->
                    <div class="glass-card p-10 rounded-2xl card-hover animate-fade-in">
                        <div class="flex items-center justify-between mb-8">
                            <div class="flex items-center space-x-4">
                                <div class="w-16 h-16 bg-corporate-gradient rounded-2xl flex items-center justify-center shadow-2xl animate-float">
                                    <i class="fas fa-calendar-alt text-white text-2xl"></i>
                                </div>
                                <div>
                                    <h2 class="text-2xl font-bold text-corporate">Activité Récente</h2>
                                    <p class="text-text-secondary">Dernières réservations clients</p>
                                </div>
                            </div>
                            <a href="reservations.php" class="text-corporate-blue hover:text-blue-300 font-semibold flex items-center group">
                                Gérer tout <i class="fas fa-arrow-right ml-2 group-hover:translate-x-1 transition-transform"></i>
                            </a>
                        </div>
                        
                        <div class="space-y-4">
                            <?php foreach($last_reservations as $resa): ?>
                            <div class="group relative">
                                <div class="flex items-center space-x-4 p-5 glass-morphism rounded-2xl hover:bg-white/10 transition-all duration-300 border border-white/10">
                                    <div class="w-14 h-14 bg-gradient-to-br from-slate-600 to-slate-800 rounded-2xl flex items-center justify-center shadow-xl flex-shrink-0">
                                        <i class="fas fa-user text-white text-lg"></i>
                                    </div>
                                    
                                    <div class="flex-1 min-w-0">
                                        <p class="font-bold text-text-primary text-lg truncate"><?= htmlspecialchars($resa['nom']) ?></p>
                                        <p class="text-text-secondary font-medium"><?= date('d/m/Y à H:i', strtotime($resa['date_reservation'])) ?></p>
                                    </div>
                                    
                                    <div class="flex items-center space-x-3">
                                        <span class="px-4 py-2 rounded-xl text-sm font-bold border-2
                                            <?= $resa['statut'] === 'confirmée' ? 'bg-success/20 text-success border-success/30' : 
                                               ($resa['statut'] === 'non_lu' ? 'bg-error/20 text-error border-error/30 animate-pulse' : 'bg-warning/20 text-warning border-warning/30') ?>">
                                            <?= htmlspecialchars($resa['statut']) ?>
                                        </span>
                                        <div class="w-8 h-8 bg-white/10 rounded-xl flex items-center justify-center group-hover:bg-white/20 transition-colors">
                                            <i class="fas fa-chevron-right text-text-secondary text-sm"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                   <!-- ... code précédent ... -->

<!-- Avis Clients -->
<div class="glass-card p-10 rounded-2xl card-hover animate-fade-in">
    <div class="flex items-center justify-between mb-8">
        <div class="flex items-center space-x-4">
            <div class="w-16 h-16 bg-warning-gradient rounded-2xl flex items-center justify-center shadow-2xl animate-float">
                <i class="fas fa-star text-white text-2xl animate-spin-slow"></i>
            </div>
            <div>
                <h2 class="text-2xl font-bold text-corporate">Avis Clients</h2>
                <p class="text-text-secondary">Retours et évaluations</p>
            </div>
        </div>
        <a href="avis.php" class="text-corporate-amber hover:text-amber-300 font-semibold flex items-center group">
            Voir tout <i class="fas fa-arrow-right ml-2 group-hover:translate-x-1 transition-transform"></i>
        </a>
    </div>
    
    <div class="space-y-6">
        <?php foreach($avis as $avis_item): 
            // Vérification et valeurs par défaut pour éviter les erreurs
            $client_nom = $avis_item['nom'] ?? 'Client inconnu';
            $date_envoi = $avis_item['date_creation'] ?? date('Y-m-d H:i:s');
            $commentaire = $avis_item['message'] ?? 'Aucun commentaire';
            $note = $avis_item['note'] ?? 0;
        ?>
        <div class="group relative">
            <div class="p-6 glass-morphism rounded-2xl hover:bg-white/10 transition-all duration-300 border border-white/10">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center space-x-4">
                        <div class="w-12 h-12 bg-warning-gradient rounded-2xl flex items-center justify-center shadow-lg">
                            <span class="text-white font-bold"><?= strtoupper(substr($client_nom, 0, 1)) ?></span>
                        </div>
                        <div>
                            <p class="font-bold text-text-primary"><?= htmlspecialchars($client_nom) ?></p>
                            <p class="text-text-secondary text-sm"><?= date('d/m/Y', strtotime($date_envoi)) ?></p>
                        </div>
                    </div>
                    
                    <div class="flex items-center space-x-1 bg-corporate-amber/20 px-3 py-1 rounded-xl">
                        <?php for($i = 1; $i <= 5; $i++): ?>
                            <i class="fas fa-star text-sm <?= $i <= $note ? 'text-corporate-amber' : 'text-white/30' ?>"></i>
                        <?php endfor; ?>
                    </div>
                </div>
                
                <blockquote class="text-text-secondary leading-relaxed italic font-medium">
                    "<?= htmlspecialchars(substr($commentaire, 0, 150)) ?><?= strlen($commentaire) > 150 ? '...' : '' ?>"
                </blockquote>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ... code suivant ... -->
                </div>

                <!-- Section Export & Rapports -->
                <div class="glass-card p-10 rounded-2xl card-hover animate-fade-in">
                    <div class="flex flex-col lg:flex-row items-center justify-between gap-8">
                        <div class="flex items-center space-x-8">
                            <div class="w-20 h-20 bg-success-gradient rounded-2xl flex items-center justify-center shadow-2xl animate-float">
                                <i class="fas fa-file-export text-white text-3xl"></i>
                            </div>
                            <div>
                                <h3 class="text-3xl font-bold text-corporate mb-2">Export & Rapports</h3>
                                <p class="text-text-secondary text-lg mb-4">Générez vos rapports détaillés au format PDF professionnel</p>
                                <div class="flex items-center space-x-6 text-corporate-emerald">
                                    <div class="flex items-center">
                                        <i class="fas fa-shield-alt mr-2"></i>
                                        <span class="font-medium">Sécurisé</span>
                                    </div>
                                    <div class="flex items-center">
                                        <i class="fas fa-clock mr-2"></i>
                                        <span class="font-medium">Temps réel</span>
                                    </div>
                                    <div class="flex items-center">
                                        <i class="fas fa-certificate mr-2"></i>
                                        <span class="font-medium">Format pro</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex flex-col sm:flex-row gap-4">
                            <a href="export_pdf.php" 
                               class="inline-flex items-center px-10 py-5 bg-success-gradient text-white rounded-2xl font-bold hover:opacity-90 transition-all duration-300 shadow-2xl hover:shadow-3xl hover:scale-105 group">
                                <i class="fas fa-file-pdf mr-4 text-2xl group-hover:rotate-12 transition-transform"></i>
                                <span class="text-lg">Télécharger Rapport PDF</span>
                                <i class="fas fa-download ml-4 text-xl group-hover:translate-y-1 transition-transform"></i>
                            </a>
                            
                            <button class="inline-flex items-center px-8 py-5 bg-white/20 text-white rounded-2xl font-semibold hover:bg-white/30 transition-all duration-300 shadow-xl hover:shadow-2xl border border-white/30 hover:scale-105 group">
                                <i class="fas fa-cog mr-3 text-lg group-hover:rotate-180 transition-transform duration-500"></i>
                                <span>Paramètres Export</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Section Actions Rapides -->
                <div class="glass-card p-10 rounded-2xl card-hover animate-fade-in">
                    <div class="text-center mb-8">
                        <h3 class="text-3xl font-bold text-corporate mb-2">Actions Rapides</h3>
                        <p class="text-text-secondary">Accédez rapidement aux fonctions principales</p>
                    </div>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                        <!-- Nouvelle Réservation -->
                        <a href="reservations.php" class="group">
                            <div class="glass-morphism p-6 rounded-2xl text-center hover:bg-white/10 transition-all duration-300 hover:scale-105 border border-white/10">
                                <div class="w-16 h-16 bg-corporate-gradient rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-xl group-hover:scale-110 transition-transform">
                                    <i class="fas fa-plus text-white text-2xl"></i>
                                </div>
                                <h4 class="text-text-primary font-bold text-lg mb-2">Nouvelle Réservation</h4>
                                <p class="text-text-secondary text-sm">Ajouter une réservation client</p>
                            </div>
                        </a>
                        
                        <!-- Ajouter Plat -->
                        <a href="ajouter_plat.php" class="group">
                            <div class="glass-morphism p-6 rounded-2xl text-center hover:bg-white/10 transition-all duration-300 hover:scale-105 border border-white/10">
                                <div class="w-16 h-16 bg-warning-gradient rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-xl group-hover:scale-110 transition-transform">
                                    <i class="fas fa-utensils text-white text-2xl"></i>
                                </div>
                                <h4 class="text-text-primary font-bold text-lg mb-2">Nouveau Plat</h4>
                                <p class="text-text-secondary text-sm">Enrichir le menu</p>
                            </div>
                        </a>
                        
                        <!-- Voir Commandes -->
                        <a href="commandes.php" class="group">
                            <div class="glass-morphism p-6 rounded-2xl text-center hover:bg-white/10 transition-all duration-300 hover:scale-105 border border-white/10">
                                <div class="w-16 h-16 bg-success-gradient rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-xl group-hover:scale-110 transition-transform">
                                    <i class="fas fa-shopping-cart text-white text-2xl"></i>
                                </div>
                                <h4 class="text-text-primary font-bold text-lg mb-2">Commandes</h4>
                                <p class="text-text-secondary text-sm">Gérer les commandes</p>
                            </div>
                        </a>
                        
                        <!-- Paramètres -->
                        <a href="settings.php" class="group">
                            <div class="glass-morphism p-6 rounded-2xl text-center hover:bg-white/10 transition-all duration-300 hover:scale-105 border border-white/10">
                                <div class="w-16 h-16 bg-gradient-to-br from-corporate-violet to-corporate-rose rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-xl group-hover:scale-110 transition-transform">
                                    <i class="fas fa-cogs text-white text-2xl"></i>
                                </div>
                                <h4 class="text-text-primary font-bold text-lg mb-2">Configuration</h4>
                                <p class="text-text-secondary text-sm">Paramètres système</p>
                            </div>
                        </a>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Ajoute ce script dans la partie <body> ou juste avant </body> -->
<script src="https://js.pusher.com/7.2/pusher.min.js"></script>
<script>
    // Remplace par ta clé publique Pusher
    const pusher = new Pusher('YOUR_APP_KEY', {
        cluster: 'eu'
    });

    // Abonnement au canal
    const channel = pusher.subscribe('admin-channel');

    // Écoute l'événement "new-order"
    channel.bind('new-order', function(data) {
        // Affiche la notification
        alert(data.message + ' - ' + data.client + ' à ' + data.heure);
        // Ou mets à jour une section de ta page :
        document.getElementById('notifications').innerHTML =
          `<div class="p-4 bg-yellow-100 border border-yellow-400 text-yellow-700 rounded mb-2">
              📢 ${data.message} - ${data.client} à ${data.heure}
           </div>`;
    });
</script>

    <!-- JavaScript Amélioré -->
    <script>
        // Mise à jour de l'heure en temps réel avec animation
        function updateClock() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('fr-FR', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
            const clockElement = document.getElementById('live-clock');
            if (clockElement && clockElement.textContent !== timeString) {
                clockElement.style.transform = 'scale(1.1)';
                clockElement.textContent = timeString;
                setTimeout(() => {
                    clockElement.style.transform = 'scale(1)';
                }, 200);
            }
        }
        setInterval(updateClock, 1000);
        updateClock();

        // Gestion du menu mobile
        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenuButton = document.getElementById('mobile-menu-button');
            const closeSidebarButton = document.getElementById('close-sidebar');
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('mobile-menu-overlay');

            function openSidebar() {
                if (sidebar) {
                    sidebar.classList.remove('-translate-x-full');
                    sidebar.classList.add('animate-slide-up');
                }
                if (overlay) {
                    overlay.classList.remove('hidden');
                    overlay.classList.add('animate-fade-in');
                }
                document.body.classList.add('overflow-hidden');
            }

            function closeSidebar() {
                if (sidebar) {
                    sidebar.classList.add('-translate-x-full');
                    sidebar.classList.remove('animate-slide-up');
                }
                if (overlay) {
                    overlay.classList.add('hidden');
                    overlay.classList.remove('animate-fade-in');
                }
                document.body.classList.remove('overflow-hidden');
            }

            if (mobileMenuButton) mobileMenuButton.addEventListener('click', openSidebar);
            if (closeSidebarButton) closeSidebarButton.addEventListener('click', closeSidebar);
            if (overlay) overlay.addEventListener('click', closeSidebar);

            // Fermeture au clavier
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') closeSidebar();
            });

            // Fermeture automatique sur grand écran
            window.addEventListener('resize', () => {
                if (window.innerWidth >= 1024) closeSidebar();
            });

            // Animation d'entrée des cartes
            const cards = document.querySelectorAll('.card-hover');
            const observer = new IntersectionObserver((entries) => {
                entries.forEach((entry, index) => {
                    if (entry.isIntersecting) {
                        setTimeout(() => {
                            entry.target.style.opacity = '1';
                            entry.target.style.transform = 'translateY(0)';
                        }, index * 100);
                    }
                });
            });

            cards.forEach(card => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(30px)';
                card.style.transition = 'all 0.6s ease';
                observer.observe(card);
            });

            // Auto-refresh notifications
            setInterval(() => {
                fetch('check_new_notifications.php')
                    .then(response => response.json())
                    .then(data => {
                        const badge = document.querySelector('.notification-badge span');
                        if (data.new > 0) {
                            badge.innerText = data.new > 9 ? '9+' : data.new;
                            badge.parentElement.style.display = 'flex';
                        } else {
                            badge.parentElement.style.display = 'none';
                        }
                    });
            }, 30000);
        });

        // Animations au scroll
        window.addEventListener('scroll', () => {
            const scrolled = window.pageYOffset;
            const parallax = document.querySelectorAll('.animate-float');
            
            parallax.forEach((element, index) => {
                const speed = 0.5 + (index * 0.1);
                element.style.transform = `translateY(${scrolled * speed}px)`;
            });
        });

        setInterval(() => {
            fetch('check_notifications.php')
            .then(res => res.json())
            .then(data => {
                if(data.nouvelles > 0){
                    document.getElementById('notif-count').innerText = data.nouvelles;
                    document.getElementById('notif-count').classList.remove('hidden');
                }
            });
        }, 10000);
    </script>
    
    <!-- Chart.js Professionnel -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const ctx = document.getElementById('revenusChart').getContext('2d');
        
        // Gradients corporates
        const gradientFill = ctx.createLinearGradient(0, 0, 0, 400);
        gradientFill.addColorStop(0, 'rgba(3, 105, 161, 0.3)');
        gradientFill.addColorStop(0.5, 'rgba(15, 118, 110, 0.2)');
        gradientFill.addColorStop(1, 'rgba(5, 150, 105, 0.1)');
        
        const gradientStroke = ctx.createLinearGradient(0, 0, 0, 400);
        gradientStroke.addColorStop(0, 'rgba(3, 105, 161, 1)');
        gradientStroke.addColorStop(0.5, 'rgba(15, 118, 110, 1)');
        gradientStroke.addColorStop(1, 'rgba(5, 150, 105, 1)');

        const revenusChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode($labels) ?>,
                datasets: [{
                    label: 'Revenus (FCFA)',
                    data: <?= json_encode($data) ?>,
                    backgroundColor: gradientFill,
                    borderColor: gradientStroke,
                    borderWidth: 4,
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: gradientStroke,
                    pointBorderWidth: 4,
                    pointRadius: 8,
                    pointHoverRadius: 12,
                    pointHoverBackgroundColor: '#ffffff',
                    pointHoverBorderColor: gradientStroke,
                    pointHoverBorderWidth: 6,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(30, 41, 59, 0.95)',
                        titleColor: '#f8fafc',
                        bodyColor: '#f8fafc',
                        borderColor: 'rgba(3, 105, 161, 0.8)',
                        borderWidth: 2,
                        cornerRadius: 16,
                        displayColors: false,
                        padding: 16,
                        titleFont: {
                            size: 16,
                            weight: 'bold'
                        },
                        bodyFont: {
                            size: 14,
                            weight: '500'
                        },
                        callbacks: {
                            title: function(context) {
                                return `📅 Période: ${context[0].label}`;
                            },
                            label: function(context) {
                                return `💰 Revenus: ${new Intl.NumberFormat('fr-FR').format(context.parsed.y)} FCFA`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        border: {
                            display: false
                        },
                        ticks: {
                            color: 'rgba(203, 213, 225, 0.8)',
                            font: {
                                size: 13,
                                weight: '600'
                            },
                            padding: 15
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(255, 255, 255, 0.08)',
                            borderDash: [10, 5],
                            lineWidth: 1
                        },
                        border: {
                            display: false
                        },
                        ticks: {
                            color: 'rgba(203, 213, 225, 0.8)',
                            font: {
                                size: 13,
                                weight: '600'
                            },
                            padding: 20,
                            callback: function(value) {
                                return new Intl.NumberFormat('fr-FR', {
                                    notation: 'compact',
                                    compactDisplay: 'short'
                                }).format(value) + ' FCFA';
                            }
                        }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                elements: {
                    line: {
                        tension: 0.4
                    }
                },
                animation: {
                    duration: 2000,
                    easing: 'easeInOutQuart'
                }
            }
        });

        // Animation d'apparition du graphique
        setTimeout(() => {
            revenusChart.update('active');
        }, 500);
    </script>
<script>
function uploadProfilePhoto(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        
        // Vérifier le type de fichier
        if (!file.type.match('image.*')) {
            alert('Veuillez sélectionner une image valide');
            return;
        }
        
        // Vérifier la taille (max 2MB)
        if (file.size > 2 * 1024 * 1024) {
            alert('La taille de l\'image ne doit pas dépasser 2MB');
            return;
        }
        
        const formData = new FormData();
        formData.append('profile_photo', file);
        
        // Afficher un indicateur de chargement
        const button = document.querySelector('button[onclick*="photo-upload"]');
        const originalIcon = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin text-white text-xs"></i>';
        button.disabled = true;
        
        fetch('upload_profile_photo.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Actualiser l'image de profil
                const profileImages = document.querySelectorAll('img[alt="Photo de profil"]');
                profileImages.forEach(img => {
                    img.src = data.photo_url + '?t=' + new Date().getTime(); // Cache busting
                });
                
                // Actualiser aussi les divs d'initiales s'il y en a
                const initialeDivs = document.querySelectorAll('.bg-gradient-to-r.from-corporate-violet.to-corporate-blue');
                initialeDivs.forEach(div => {
                    if (div.querySelector('span')) {
                        div.innerHTML = `<img src="${data.photo_url}?t=${new Date().getTime()}" alt="Photo de profil" class="w-full h-full object-cover rounded-2xl">`;
                    }
                });
                
                alert('Photo de profil mise à jour avec succès !');
            } else {
                alert('Erreur lors de l\'upload : ' + data.message);
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            alert('Erreur lors de l\'upload de la photo');
        })
        .finally(() => {
            // Restaurer le bouton
            button.innerHTML = originalIcon;
            button.disabled = false;
        });
    }
}
</script>
    <!-- Effets visuels professionnels -->
     <style>
.profile-img {
    object-fit: cover;
    width: 100%;
    height: 100%;
}

.profile-container {
    overflow: hidden;
}
</style>
    <style>
        .glass-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, transparent 30%, rgba(255, 255, 255, 0.05) 50%, transparent 70%);
            transform: translateX(-100%);
            transition: transform 0.6s;
            pointer-events: none;
        }
        
        .glass-card:hover::before {
            transform: translateX(100%);
        }
        
        /* Animations professionnelles */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes slideUp {
            from { transform: translateY(100px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-15px); }
        }
        
        @keyframes wiggle {
            0%, 100% { transform: rotate(-3deg); }
            50% { transform: rotate(3deg); }
        }
        
        @keyframes glow {
            0% { box-shadow: 0 0 5px rgba(3, 105, 161, 0.3); }
            100% { box-shadow: 0 0 20px rgba(3, 105, 161, 0.6), 0 0 30px rgba(15, 118, 110, 0.4); }
        }
    </style>
    <!-- JavaScript complet pour les animations des cartes -->
<script>
// Animation au chargement des cartes
document.addEventListener('DOMContentLoaded', function() {
    // Animation des dashboard cards avec délai progressif
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

    // Animation des éléments au scroll avec Intersection Observer
    const animatedElements = document.querySelectorAll('.animate-fade-in, .animate-slide-up');
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry, index) => {
            if (entry.isIntersecting) {
                setTimeout(() => {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                    entry.target.classList.add('animated');
                }, index * 100);
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    animatedElements.forEach(element => {
        element.style.opacity = '0';
        element.style.transform = 'translateY(30px)';
        element.style.transition = 'all 0.6s ease';
        observer.observe(element);
    });

    // Effet de parallaxe sur les icônes au scroll
    let ticking = false;
    window.addEventListener('scroll', () => {
        if (!ticking) {
            window.requestAnimationFrame(() => {
                const scrolled = window.pageYOffset;
                const iconWrappers = document.querySelectorAll('.icon-wrapper');
                
                iconWrappers.forEach((icon, index) => {
                    const speed = 0.5 + (index * 0.05);
                    const yPos = -(scrolled * speed / 20);
                    icon.style.transform = `translateY(${yPos}px)`;
                });
                ticking = false;
            });
            ticking = true;
        }
    }, { passive: true });

    // Animation des barres de progression
    setTimeout(() => {
        const progressBars = document.querySelectorAll('.dashboard-card .bg-blue-500, .dashboard-card .bg-gradient-to-r, .dashboard-card [class*="rounded-full h-"]');
        progressBars.forEach(bar => {
            const targetWidth = bar.style.width;
            if (targetWidth) {
                bar.style.width = '0%';
                setTimeout(() => {
                    bar.style.transition = 'width 1.5s ease-out';
                    bar.style.width = targetWidth;
                }, 100);
            }
        });
    }, 800);

    // Effet hover sur les badges numérotés
    const numberBadges = document.querySelectorAll('.number-badge');
    numberBadges.forEach(badge => {
        badge.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-3px) scale(1.1) rotate(5deg)';
        });
        badge.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1) rotate(0deg)';
        });
    });

    // Animation des chiffres (compteur animé)
    const animateNumbers = () => {
        const numberElements = document.querySelectorAll('.dashboard-card .text-3xl, .dashboard-card .text-4xl, .dashboard-card .text-5xl');
        
        numberElements.forEach(element => {
            const text = element.textContent.trim();
            const numberMatch = text.match(/[\d\s,]+/);
            
            if (numberMatch) {
                const finalNumber = parseInt(numberMatch[0].replace(/[\s,]/g, ''));
                if (!isNaN(finalNumber) && finalNumber > 0) {
                    const duration = 2000;
                    const steps = 60;
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
                        
                        const formatted = Math.floor(current).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
                        element.textContent = text.replace(/[\d\s,]+/, formatted);
                    }, duration / steps);
                }
            }
        });
    };

    setTimeout(animateNumbers, 500);

    // Effet de brillance sur les cartes au survol
    dashboardCards.forEach(card => {
        card.addEventListener('mousemove', (e) => {
            const rect = card.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;

            const centerX = rect.width / 2;
            const centerY = rect.height / 2;

            const angleX = (y - centerY) / 20;
            const angleY = (centerX - x) / 20;

            card.style.transform = `perspective(1000px) rotateX(${angleX}deg) rotateY(${angleY}deg) translateY(-5px)`;
        });

        card.addEventListener('mouseleave', () => {
            card.style.transform = 'perspective(1000px) rotateX(0) rotateY(0) translateY(0)';
        });
    });

    // Animation des icônes au hover des cartes
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

    // Animation des éléments du Hall of Fame
    const hallOfFameItems = document.querySelectorAll('.glass-morphism');
    hallOfFameItems.forEach((item, index) => {
        setTimeout(() => {
            item.style.transition = 'all 0.5s ease';
            item.style.opacity = '1';
            item.style.transform = 'translateX(0)';
        }, 300 + (index * 100));
    });

    // Effet de pulsation sur les badges de notification
    const notificationBadges = document.querySelectorAll('.notification-badge, .animate-pulse');
    notificationBadges.forEach(badge => {
        setInterval(() => {
            badge.style.transform = 'scale(1.1)';
            setTimeout(() => {
                badge.style.transform = 'scale(1)';
            }, 200);
        }, 2000);
    });

    console.log('✅ Animations chargées avec succès');
    console.log('Dashboard cards trouvées:', dashboardCards.length);
});

// Animation de chargement de la page
window.addEventListener('load', () => {
    document.body.style.opacity = '0';
    setTimeout(() => {
        document.body.style.transition = 'opacity 0.5s ease';
        document.body.style.opacity = '1';
    }, 100);
});
</script>

<!-- Styles additionnels pour les badges numérotés -->
<style>
.number-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
    color: white;
    border-radius: 10px;
    font-size: 16px;
    font-weight: 700;
    box-shadow: 0 4px 6px rgba(59, 130, 246, 0.4);
    border: 2px solid rgba(255, 255, 255, 0.1);
    transition: all 0.3s ease;
}

.number-badge:hover {
    transform: translateY(-2px) scale(1.05);
    box-shadow: 0 6px 12px rgba(59, 130, 246, 0.5);
}

.number-badge.alt-1 {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    box-shadow: 0 4px 6px rgba(16, 185, 129, 0.4);
}

.number-badge.alt-2 {
    background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
    box-shadow: 0 4px 6px rgba(139, 92, 246, 0.4);
}

.number-badge.alt-3 {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    box-shadow: 0 4px 6px rgba(245, 158, 11, 0.4);
}
</style>
</body>
</html>