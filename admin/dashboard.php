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
    SELECT client_nom, note, commentaire, date_envoi
    FROM avis
    ORDER BY date_envoi DESC
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

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - Administration</title>
    
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
                                    class="relative w-12 h-12 bg-warning-gradient rounded-2xl flex items-center justify-center shadow-xl hover:shadow-2xl transition-all duration-300 hover:scale-110 focus:outline-none focus:ring-4 focus:ring-warning/30 group"
                                    aria-label="Notifications"
                                    type="button"
                                >
                                    <i class="fas fa-bell text-white text-lg group-hover:animate-wiggle"></i>
                                    <?php if (!empty($nb_nouvelles_notifications) && $nb_nouvelles_notifications > 0): ?>
                                        <div class="absolute -top-2 -right-2 w-6 h-6 bg-error rounded-full flex items-center justify-center notification-badge">
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
                                    class="absolute right-0 mt-4 w-96 glass-card rounded-3xl shadow-2xl py-4 z-50 border border-white/20 max-h-96 overflow-hidden"
                                    x-cloak
                                >
                                    <!-- Header Notifications -->
                                    <div class="px-6 py-4 border-b border-white/10 flex items-center justify-between">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-8 h-8 bg-warning-gradient rounded-xl flex items-center justify-center">
                                                <i class="fas fa-bell text-white text-sm"></i>
                                            </div>
                                            <h3 class="font-bold text-text-primary">Notifications</h3>
                                            <?php if (!empty($nb_nouvelles_notifications) && $nb_nouvelles_notifications > 0): ?>
                                                <span class="px-3 py-1 bg-error text-white text-xs font-bold rounded-full"><?= $nb_nouvelles_notifications ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($nb_nouvelles_notifications) && $nb_nouvelles_notifications > 0): ?>
                                            <form method="post" class="inline">
                                                <button type="submit" name="mark_all_read" class="text-xs text-corporate-amber hover:text-amber-300 font-medium">
                                                    Tout marquer lu
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Liste Notifications -->
                                    <div class="max-h-64 overflow-y-auto scrollbar-hidden">
                                        <?php if (empty($notifications)): ?>
                                            <div class="p-8 text-center">
                                                <div class="w-16 h-16 bg-white/10 rounded-full flex items-center justify-center mx-auto mb-4">
                                                    <i class="fas fa-bell-slash text-white/50 text-2xl"></i>
                                                </div>
                                                <p class="text-text-secondary">Aucune notification</p>
                                            </div>
                                        <?php else: ?>
                                            <?php foreach ($notifications as $notification): ?>
                                                <div class="p-4 hover:bg-white/5 transition-colors border-b border-white/5 last:border-b-0">
                                                    <div class="flex items-start space-x-3">
                                                        <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0
                                                            <?php 
                                                                switch ($notification['type'] ?? '') {
                                                                    case 'success': echo 'bg-success'; break;
                                                                    case 'warning': echo 'bg-warning'; break;
                                                                    case 'error': echo 'bg-error'; break;
                                                                    default: echo 'bg-info';
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
                                                                class="text-white text-sm"
                                                            ></i>
                                                        </div>
                                                        <div class="flex-1 min-w-0">
                                                            <p class="font-semibold text-text-primary <?= empty($notification['vue']) ? 'text-corporate-amber' : '' ?>">
                                                                <?= htmlspecialchars($notification['titre'] ?? 'Sans titre') ?>
                                                                <?php if (empty($notification['vue'])): ?>
                                                                    <span class="w-2 h-2 bg-corporate-amber rounded-full inline-block ml-2 animate-pulse"></span>
                                                                <?php endif; ?>
                                                            </p>
                                                            <p class="text-sm text-text-secondary mt-1"><?= htmlspecialchars($notification['message'] ?? '') ?></p>
                                                            <div class="flex items-center justify-between mt-2">
                                                                <p class="text-xs text-white/50">
                                                                    <?= !empty($notification['date_creation']) ? date('d/m/Y à H:i', strtotime($notification['date_creation'])) : 'Date inconnue' ?>
                                                                </p>
                                                                <?php if (empty($notification['vue'])): ?>
                                                                    <form method="post" class="inline">
                                                                        <input type="hidden" name="notification_id" value="<?= (int)($notification['id'] ?? 0) ?>">
                                                                        <button type="submit" name="mark_read" class="text-xs text-info hover:text-blue-300">
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
                                    
                                    <!-- Lien Voir Tout -->
                                    <div class="px-6 py-4 border-t border-white/10">
                                        <a href="notifications.php" class="block text-center text-info hover:text-blue-300 font-medium text-sm">
                                            Voir toutes les notifications
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <!-- Menu Profil -->
                            <div class="relative">
                                <button 
                                    @click="profileOpen = !profileOpen"
                                    class="w-14 h-14 bg-gradient-to-r from-corporate-violet to-corporate-blue rounded-2xl flex items-center justify-center shadow-2xl hover:shadow-3xl transition-all duration-300 hover:scale-105 focus:outline-none focus:ring-4 focus:ring-corporate-violet/30 group animate-glow"
                                    type="button"
                                >
                                    <span class="text-white font-bold text-lg group-hover:scale-110 transition-transform">
                                        <?= strtoupper(substr($admin_name ?? 'A', 0, 1)) ?>
                                    </span>
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
                                    class="absolute right-0 mt-4 w-72 glass-card rounded-3xl shadow-2xl py-4 z-50 border border-white/20"
                                    x-cloak
                                >
                                    <!-- Header Profil -->
                                    <div class="px-8 py-6 border-b border-white/10 flex items-center space-x-4">
                                        <div class="w-12 h-12 bg-gradient-to-r from-corporate-violet to-corporate-blue rounded-2xl flex items-center justify-center shadow-lg">
                                            <span class="text-white font-bold text-lg"><?= strtoupper(substr($admin_name ?? 'A', 0, 1)) ?></span>
                                        </div>
                                        <div>
                                            <p class="font-bold text-text-primary text-lg"><?= htmlspecialchars($admin_name ?? 'Admin') ?></p>
                                            <?php if (!empty($admin_email)): ?>
                                                <p class="text-sm text-text-secondary"><?= htmlspecialchars($admin_email) ?></p>
                                            <?php endif; ?>
                                            <div class="flex items-center mt-1">
                                                <div class="w-2 h-2 bg-success rounded-full animate-pulse"></div>
                                                <span class="text-xs text-success ml-2 font-medium">En ligne</span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Menu Items -->
                                    <div class="py-2">
                                        <a href="profile.php" class="flex items-center px-8 py-4 text-text-secondary hover:bg-white/10 hover:text-text-primary transition-all duration-200 group">
                                            <div class="w-10 h-10 bg-corporate-blue/20 rounded-xl flex items-center justify-center mr-4 group-hover:bg-corporate-blue/30 transition-colors">
                                                <i class="fas fa-user text-corporate-blue"></i>
                                            </div>
                                            <span class="font-medium">Mon profil</span>
                                            <i class="fas fa-chevron-right ml-auto text-xs opacity-0 group-hover:opacity-100 group-hover:translate-x-1 transition-all"></i>
                                        </a>
                                        <a href="settings.php" class="flex items-center px-8 py-4 text-text-secondary hover:bg-white/10 hover:text-text-primary transition-all duration-200 group">
                                            <div class="w-10 h-10 bg-corporate-teal/20 rounded-xl flex items-center justify-center mr-4 group-hover:bg-corporate-teal/30 transition-colors">
                                                <i class="fas fa-cog text-corporate-teal"></i>
                                            </div>
                                            <span class="font-medium">Paramètres</span>
                                            <i class="fas fa-chevron-right ml-auto text-xs opacity-0 group-hover:opacity-100 group-hover:translate-x-1 transition-all"></i>
                                        </a>
                                        <a href="changer_email.php" class="flex items-center px-8 py-4 text-text-secondary hover:bg-white/10 hover:text-text-primary transition-all duration-200 group">
                                            <div class="w-10 h-10 bg-corporate-violet/20 rounded-xl flex items-center justify-center mr-4 group-hover:bg-corporate-violet/30 transition-colors">
                                                <i class="fas fa-envelope text-corporate-violet"></i>
                                            </div>
                                            <span class="font-medium">Changer email</span>
                                            <i class="fas fa-chevron-right ml-auto text-xs opacity-0 group-hover:opacity-100 group-hover:translate-x-1 transition-all"></i>
                                        </a>
                                    </div>
                                    
                                    <!-- Déconnexion -->
                                    <div class="border-t border-white/10 pt-2">
                                        <a href="logout.php" class="flex items-center px-8 py-4 text-error hover:bg-error/10 hover:text-red-300 transition-all duration-200 group">
                                            <div class="w-10 h-10 bg-error/20 rounded-xl flex items-center justify-center mr-4 group-hover:bg-error/30 transition-colors">
                                                <i class="fas fa-sign-out-alt text-error"></i>
                                            </div>
                                            <span class="font-medium">Déconnexion</span>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </header>
<div id="notifications" class="space-y-2 p-2"></div>

            <!-- Section Notifications -->
            <div class="bg-white/5 shadow-md rounded-lg p-4 mx-4 mt-4">
                <h2 class="text-xl font-bold mb-4 text-text-primary">🔔 Notifications</h2>
                <ul>
                    <?php foreach ($notifications as $notif): ?>
                        <li class="mb-2 p-3 rounded bg-corporate-blue/10 border-l-4 border-corporate-blue">
                            <span class="text-text-primary"><?= htmlspecialchars($notif['message']) ?></span><br>
                            <small class="text-text-secondary"><?= date('d/m/Y H:i', strtotime($notif['date'])) ?></small>
                        </li>
                    <?php endforeach; ?>
                    <?php if (empty($notifications)): ?>
                        <li class="text-text-secondary">Aucune nouvelle notification</li>
                    <?php endif; ?>
                </ul>
            </div>

            <!-- Contenu Principal -->
            <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8 space-y-8 scrollbar-hidden">
                <!-- Cartes KPI Professionnelles -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 animate-slide-up">
                    <!-- KPI Réservations -->
                    <div class="glass-card p-8 rounded-2xl card-hover group overflow-hidden relative">
                        <div class="absolute inset-0 bg-gradient-to-br from-corporate-blue/5 to-corporate-teal/5 opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                        <div class="relative z-10">
                            <div class="flex items-center justify-between mb-6">
                                <div class="w-16 h-16 bg-corporate-gradient rounded-2xl flex items-center justify-center shadow-2xl group-hover:scale-110 transition-transform duration-300">
                                    <i class="fas fa-calendar-check text-white text-2xl"></i>
                                </div>
                                <div class="text-right">
                                    <div class="w-12 h-2 bg-corporate-gradient rounded-full"></div>
                                </div>
                            </div>
                            <div class="space-y-3">
                                <h3 class="text-text-secondary font-semibold text-lg">Réservations Totales</h3>
                                <p class="text-5xl font-black text-corporate">
                                    <?= htmlspecialchars($reservations) ?>
                                </p>
                                <div class="flex items-center text-success">
                                    <div class="w-6 h-6 bg-success/20 rounded-lg flex items-center justify-center mr-2">
                                        <i class="fas fa-trending-up text-xs"></i>
                                    </div>
                                    <span class="font-semibold text-sm">+12.5% ce mois</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- KPI Plats -->
                    <div class="glass-card p-8 rounded-2xl card-hover group overflow-hidden relative">
                        <div class="absolute inset-0 bg-gradient-to-br from-corporate-amber/5 to-warning/5 opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                        <div class="relative z-10">
                            <div class="flex items-center justify-between mb-6">
                                <div class="w-16 h-16 bg-warning-gradient rounded-2xl flex items-center justify-center shadow-2xl group-hover:scale-110 transition-transform duration-300">
                                    <i class="fas fa-utensils text-white text-2xl"></i>
                                </div>
                                <div class="text-right">
                                    <div class="w-12 h-2 bg-warning-gradient rounded-full"></div>
                                </div>
                            </div>
                            <div class="space-y-3">
                                <h3 class="text-text-secondary font-semibold text-lg">Plats au Menu</h3>
                                <p class="text-5xl font-black text-corporate">
                                    <?= htmlspecialchars($plats) ?>
                                </p>
                                <div class="flex items-center text-info">
                                    <div class="w-6 h-6 bg-info/20 rounded-lg flex items-center justify-center mr-2">
                                        <i class="fas fa-star text-xs"></i>
                                    </div>
                                    <span class="font-semibold text-sm">Menu diversifié</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- KPI Revenus -->
                    <div class="glass-card p-8 rounded-2xl card-hover group overflow-hidden relative">
                        <div class="absolute inset-0 bg-gradient-to-br from-corporate-emerald/5 to-success/5 opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                        <div class="relative z-10">
                            <div class="flex items-center justify-between mb-6">
                                <div class="w-16 h-16 bg-success-gradient rounded-2xl flex items-center justify-center shadow-2xl group-hover:scale-110 transition-transform duration-300">
                                    <i class="fas fa-coins text-white text-2xl animate-spin-slow"></i>
                                </div>
                                <div class="text-right">
                                    <div class="w-12 h-2 bg-success-gradient rounded-full"></div>
                                </div>
                            </div>
                            <div class="space-y-3">
                                <h3 class="text-text-secondary font-semibold text-lg">Chiffre d'Affaires</h3>
                                <p class="text-4xl font-black text-corporate">
                                    <?= number_format($revenus, 0, ',', ' ') ?> <span class="text-2xl">FCFA</span>
                                </p>
                                <div class="flex items-center text-success">
                                    <div class="w-6 h-6 bg-success/20 rounded-lg flex items-center justify-center mr-2">
                                        <i class="fas fa-chart-line text-xs"></i>
                                    </div>
                                    <span class="font-semibold text-sm">Croissance stable</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- KPI Taux de confirmation -->
                    <div class="glass-card p-8 rounded-2xl card-hover group overflow-hidden relative">
                        <div class="absolute inset-0 bg-gradient-to-br from-corporate-violet/5 to-corporate-rose/5 opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                        <div class="relative z-10">
                            <div class="flex items-center justify-between mb-6">
                                <div class="w-16 h-16 bg-gradient-to-br from-corporate-violet to-corporate-rose rounded-2xl flex items-center justify-center shadow-2xl group-hover:scale-110 transition-transform duration-300">
                                    <i class="fas fa-check-circle text-white text-2xl"></i>
                                </div>
                                <div class="text-right">
                                    <div class="w-12 h-2 bg-gradient-to-r from-corporate-violet to-corporate-rose rounded-full"></div>
                                </div>
                            </div>
                            <div class="space-y-4">
                                <h3 class="text-text-secondary font-semibold text-lg">Taux Confirmation</h3>
                                <p class="text-5xl font-black text-corporate">
                                    <?= $taux_confirmation ?>%
                                </p>
                                <div class="w-full bg-white/10 rounded-full h-3 overflow-hidden">
                                    <div class="h-full bg-gradient-to-r from-corporate-violet to-corporate-rose rounded-full transition-all duration-2000 ease-out shadow-lg" 
                                         style="width: <?= $taux_confirmation ?>%"></div>
                                </div>
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
                            <a href="plats.php" class="inline-flex items-center px-8 py-4 bg-warning-gradient text-white rounded-2xl font-semibold hover:opacity-90 transition-all duration-300 shadow-2xl hover:shadow-3xl hover:scale-105">
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
                            <?php foreach($avis as $avis_item): ?>
                            <div class="group relative">
                                <div class="p-6 glass-morphism rounded-2xl hover:bg-white/10 transition-all duration-300 border border-white/10">
                                    <div class="flex items-center justify-between mb-4">
                                        <div class="flex items-center space-x-4">
                                            <div class="w-12 h-12 bg-warning-gradient rounded-2xl flex items-center justify-center shadow-lg">
                                                <span class="text-white font-bold"><?= strtoupper(substr($avis_item['client_nom'], 0, 1)) ?></span>
                                            </div>
                                            <div>
                                                <p class="font-bold text-text-primary"><?= htmlspecialchars($avis_item['client_nom']) ?></p>
                                                <p class="text-text-secondary text-sm"><?= date('d/m/Y', strtotime($avis_item['date_envoi'])) ?></p>
                                            </div>
                                        </div>
                                        
                                        <div class="flex items-center space-x-1 bg-corporate-amber/20 px-3 py-1 rounded-xl">
                                            <?php for($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star text-sm <?= $i <= $avis_item['note'] ? 'text-corporate-amber' : 'text-white/30' ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                    
                                    <blockquote class="text-text-secondary leading-relaxed italic font-medium">
                                        "<?= htmlspecialchars(substr($avis_item['commentaire'], 0, 150)) ?><?= strlen($avis_item['commentaire']) > 150 ? '...' : '' ?>"
                                    </blockquote>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
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

    <!-- Effets visuels professionnels -->
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
</body>
</html>